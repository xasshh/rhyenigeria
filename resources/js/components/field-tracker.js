// Field work page for approved canvassers. While a shift is running and this
// page is open, the phone's GPS position is recorded and sent in small
// batches. Points wait in the phone's storage if the signal drops, and go up
// when it comes back. Nothing is recorded outside a shift.
const QUEUE_KEY = 'rhye-field-queue';
const MIN_MOVE_M = 15;          // keep a fix if the phone moved this far...
const MAX_GAP_MS = 60_000;      // ...or this long has passed since the last kept fix
const FLUSH_MS = 30_000;
const FLUSH_AT = 10;

const uuid = () => (crypto.randomUUID ? crypto.randomUUID()
    : 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
        const r = (Math.random() * 16) | 0;
        return (c === 'x' ? r : (r & 0x3) | 0x8).toString(16);
    }));

const metres = (a, b) => {
    const R = 6371000, rad = (d) => (d * Math.PI) / 180;
    const dLat = rad(b.latitude - a.latitude), dLng = rad(b.longitude - a.longitude);
    const h = Math.sin(dLat / 2) ** 2 + Math.cos(rad(a.latitude)) * Math.cos(rad(b.latitude)) * Math.sin(dLng / 2) ** 2;
    return 2 * R * Math.asin(Math.sqrt(h));
};

export default (config) => ({
    onShift: config.onShift,
    startedAt: config.startedAt,
    busy: false,
    gps: { status: 'idle', accuracy: null, at: null, message: '' },
    queue: [],
    sent: 0,
    flushing: false,
    lastKept: null,
    lastFix: null,
    watchId: null,
    timer: null,
    wakeLock: null,
    activities: config.activities || [],
    form: { kind: 'door_to_door', households: '', outcome: '', event_id: '', note: '' },
    photo: null,
    errors: {},
    formMessage: '',

    init() {
        try { this.queue = JSON.parse(localStorage.getItem(QUEUE_KEY) || '[]'); } catch { this.queue = []; }
        if (this.onShift) this.startTracking();
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible' && this.onShift) { this.keepAwake(); this.flush(); }
        });
        window.addEventListener('online', () => this.flush());
        // Leaving the page stops the GPS; say so rather than let it look like it is still running.
        window.addEventListener('beforeunload', (e) => {
            if (this.onShift) { this.flush(true); e.preventDefault(); e.returnValue = ''; }
        });
    },

    destroy() { this.stopTracking(); },

    get shiftLength() {
        if (!this.startedAt) return '';
        const mins = Math.max(0, Math.floor((Date.now() - new Date(this.startedAt).getTime()) / 60000));
        return mins < 60 ? `${mins} min` : `${Math.floor(mins / 60)} h ${mins % 60} min`;
    },

    async send(url, method, body, isForm = false) {
        const res = await fetch(url, {
            method,
            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': config.csrf, ...(isForm ? {} : { 'Content-Type': 'application/json' }) },
            body: isForm ? body : (body ? JSON.stringify(body) : undefined),
            credentials: 'same-origin',
        });
        const data = await res.json().catch(() => ({}));
        return { ok: res.ok, status: res.status, data };
    },

    async start() {
        if (this.busy) return;
        if (!('geolocation' in navigator)) { this.gps = { ...this.gps, status: 'error', message: 'This phone or browser cannot share its location.' }; return; }
        this.busy = true;
        try {
            const { ok, data } = await this.send(config.urls.start, 'POST');
            if (!ok) { this.gps.message = data.message || 'Could not start the shift. Try again.'; return; }
            this.onShift = true;
            this.startedAt = data.shift.started_at;
            this.startTracking();
        } catch { this.gps.message = 'No connection. Check your data and try again.'; }
        finally { this.busy = false; }
    },

    async end() {
        if (this.busy) return;
        this.busy = true;
        await this.flush(true);
        try {
            await this.send(config.urls.end, 'DELETE');
            this.onShift = false;
            this.startedAt = null;
            this.stopTracking();
            this.gps = { status: 'idle', accuracy: null, at: null, message: 'Shift ended. Location sharing is off.' };
        } catch { this.gps.message = 'No connection. Your shift will end when you are back online.'; }
        finally { this.busy = false; }
    },

    startTracking() {
        if (this.watchId !== null || !('geolocation' in navigator)) return;
        this.gps = { ...this.gps, status: 'searching', message: 'Finding your location…' };
        this.watchId = navigator.geolocation.watchPosition(
            (pos) => this.onFix(pos),
            (err) => {
                this.gps = { ...this.gps, status: 'error', message: err.code === 1
                    ? 'Location is blocked. Allow location for this site in your browser settings, then reload.'
                    : 'Cannot get a GPS fix right now. Move to an open space.' };
            },
            { enableHighAccuracy: true, maximumAge: 10_000, timeout: 30_000 },
        );
        this.timer = setInterval(() => this.flush(), FLUSH_MS);
        this.keepAwake();
    },

    stopTracking() {
        if (this.watchId !== null) navigator.geolocation.clearWatch(this.watchId);
        this.watchId = null;
        clearInterval(this.timer);
        this.wakeLock?.release?.().catch(() => {});
        this.wakeLock = null;
    },

    async keepAwake() {
        // Phones stop GPS when the screen locks; ask to keep it on during a shift.
        try { if ('wakeLock' in navigator && !this.wakeLock) this.wakeLock = await navigator.wakeLock.request('screen'); } catch { /* not allowed */ }
    },

    onFix(pos) {
        const fix = {
            latitude: Number(pos.coords.latitude.toFixed(6)),
            longitude: Number(pos.coords.longitude.toFixed(6)),
            accuracy: Math.round(pos.coords.accuracy),
            at: pos.timestamp || Date.now(),
        };
        this.lastFix = fix;
        this.gps = { status: 'ok', accuracy: fix.accuracy, at: fix.at, message: '' };
        const keep = !this.lastKept || metres(this.lastKept, fix) >= MIN_MOVE_M || fix.at - this.lastKept.at >= MAX_GAP_MS;
        if (!keep) return;
        this.lastKept = fix;
        this.queue.push({ uuid: uuid(), latitude: fix.latitude, longitude: fix.longitude, accuracy: fix.accuracy, recorded_at: new Date(fix.at).toISOString() });
        this.save();
        if (this.queue.length >= FLUSH_AT) this.flush();
    },

    save() { try { localStorage.setItem(QUEUE_KEY, JSON.stringify(this.queue.slice(-2000))); } catch { /* storage full */ } },

    async flush(beacon = false) {
        if (!this.queue.length || this.flushing) return;
        const batch = this.queue.slice(0, 200);
        if (beacon && navigator.sendBeacon) {
            const blob = new Blob([JSON.stringify({ points: batch, _token: config.csrf })], { type: 'application/json' });
            if (navigator.sendBeacon(config.urls.locations, blob)) { this.queue.splice(0, batch.length); this.save(); }
            return;
        }
        this.flushing = true;
        try {
            const { ok, status } = await this.send(config.urls.locations, 'POST', { points: batch });
            // 200 = stored (or duplicates); 409/422 = the server will never take these, so drop them.
            if (ok || status === 409 || status === 422) { this.queue.splice(0, batch.length); this.sent += batch.length; this.save(); }
        } catch { /* offline: keep the queue */ }
        finally { this.flushing = false; }
    },

    currentPosition() {
        // A fresh fix for an activity; the last tracking fix if it is under two minutes old.
        if (this.lastFix && Date.now() - this.lastFix.at < 120_000) return Promise.resolve(this.lastFix);
        return new Promise((resolve, reject) => navigator.geolocation.getCurrentPosition(
            (p) => resolve({ latitude: p.coords.latitude, longitude: p.coords.longitude, accuracy: Math.round(p.coords.accuracy) }),
            reject, { enableHighAccuracy: true, timeout: 20_000, maximumAge: 30_000 },
        ));
    },

    pickPhoto(e) { this.photo = e.target.files[0] || null; },

    async logActivity() {
        if (this.busy) return;
        this.busy = true; this.errors = {}; this.formMessage = 'Getting your location…';
        try {
            const at = await this.currentPosition().catch(() => null);
            if (!at) { this.formMessage = 'We could not get your location. Turn on location and try again.'; return; }
            const body = new FormData();
            body.append('uuid', uuid());
            Object.entries(this.form).forEach(([k, v]) => v !== '' && v !== null && body.append(k, v));
            body.append('latitude', at.latitude); body.append('longitude', at.longitude);
            if (at.accuracy != null) body.append('accuracy', at.accuracy);
            if (this.photo) body.append('photo', this.photo);
            this.formMessage = 'Saving…';
            const { ok, status, data } = await this.send(config.urls.activities, 'POST', body, true);
            if (status === 422) { this.errors = data.errors || {}; this.formMessage = 'Please check the highlighted answers.'; return; }
            if (!ok) { this.formMessage = data.message || 'Could not save. Try again.'; return; }
            this.activities.unshift(data.activity);
            this.form = { ...this.form, households: '', outcome: '', event_id: '', note: '' };
            this.photo = null;
            this.$refs.photo && (this.$refs.photo.value = '');
            this.formMessage = 'Saved.';
            setTimeout(() => (this.formMessage = ''), 3000);
        } catch { this.formMessage = 'No connection. Try again when you have signal.'; }
        finally { this.busy = false; }
    },

    err(field) { return this.errors[field]?.[0] || ''; },
});
