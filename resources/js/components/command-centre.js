// The election command centre (admin/command.blade.php): polls the scoped feed,
// eases the numbers towards their new values and paints the situation map.
const reduceMotion = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;
const KEYS = ['total', 'today', 'week', 'agents', 'on_shift', 'pvc'];

export default (config) => ({
    data: {},
    display: Object.fromEntries(KEYS.map((k) => [k, 0])),
    bumped: {},
    coverage: { wards: 0, wards_total: 0, areas: 0, areas_total: config.areasTotal },
    headlines: [],
    clock: '',
    syncedAt: null,
    syncedAgo: null,
    error: false,
    booting: false,
    bootLines: [],
    trendLine: '',
    trendArea: '',
    trendMax: 0,
    first: true,

    init() {
        let booted = false;
        try { booted = sessionStorage.getItem('cc-booted') === '1'; sessionStorage.setItem('cc-booted', '1'); } catch { /* private mode */ }
        if (!booted && !reduceMotion()) {
            this.bootLines = [
                '> ESTABLISHING SECURE LINK ............ OK',
                `> OPERATOR: ${config.operator.toUpperCase()}`,
                '> CLEARANCE LEVEL ...................... SUPER ADMIN',
                `> THEATRE: ${config.scope.toUpperCase()}`,
                '> SYNCING FIELD INTELLIGENCE ........... OK',
            ];
            this.booting = true;
            setTimeout(() => { this.booting = false; }, this.bootLines.length * 320 + 1100);
        }

        const tick = () => {
            this.clock = new Intl.DateTimeFormat('en-GB', { timeZone: 'Africa/Lagos', hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false }).format(new Date());
            if (this.syncedAt) {
                const s = Math.round((Date.now() - this.syncedAt) / 1000);
                this.syncedAgo = s < 5 ? 'NOW' : `${s}s AGO`;
            }
        };
        tick();
        setInterval(tick, 1000);

        this.load();
        setInterval(() => { if (!document.hidden) this.load(); }, config.refreshSeconds * 1000);
        window.addEventListener('cc-news', (e) => { this.headlines = e.detail; });
    },

    async load() {
        try {
            const res = await fetch(config.feed, { headers: { Accept: 'application/json' } });
            if (!res.ok) throw new Error(String(res.status));
            const data = await res.json();
            this.error = false;
            this.syncedAt = Date.now();
            this.apply(data);
        } catch {
            this.error = true;
        }
    },

    apply(data) {
        this.data = data;
        const s = data.summary;
        const targets = { total: s.total, today: s.today, week: s.week, agents: s.canvassers_approved, on_shift: data.field.on_shift, pvc: s.pvc };
        KEYS.forEach((k) => this.animate(k, targets[k] ?? 0));
        this.coverage = { ...data.coverage, areas_total: config.areasTotal };
        // The live map (its own component) colours the areas from the same figures.
        window.dispatchEvent(new CustomEvent('cc-feed', { detail: data.areas ?? [] }));
        this.drawTrend();
        this.first = false;
    },

    animate(key, to) {
        const from = this.display[key];
        if (from === to) return;
        if (!this.first && to > from) {
            this.bumped[key] = false;
            requestAnimationFrame(() => { this.bumped[key] = true; setTimeout(() => { this.bumped[key] = false; }, 1600); });
        }
        if (reduceMotion()) { this.display[key] = to; return; }
        const start = performance.now();
        const duration = this.first ? 1800 : 900;
        const step = (now) => {
            const t = Math.min(1, (now - start) / duration);
            const eased = t === 1 ? 1 : 1 - 2 ** (-10 * t);
            this.display[key] = Math.round(from + (to - from) * eased);
            if (t < 1) requestAnimationFrame(step);
        };
        requestAnimationFrame(step);
    },

    drawTrend() {
        const values = this.data.trend?.values ?? [];
        if (values.length < 2) return;
        const max = Math.max(1, ...values);
        this.trendMax = Math.max(...values);
        const pts = values.map((v, i) => [(i / (values.length - 1)) * 600, 170 - (v / max) * 150]);
        const line = pts.map(([x, y], i) => `${i ? 'L' : 'M'}${x.toFixed(1)},${y.toFixed(1)}`).join(' ');
        this.trendArea = `${line} L600,180 L0,180 Z`;
        // Replay the draw-in effect only when the line changed.
        if (line !== this.trendLine) {
            this.trendLine = line;
            const el = this.$refs.trendPath;
            el.style.animation = 'none';
            void el.getBoundingClientRect();
            el.style.animation = '';
        }
    },

    get strongest() {
        return [...(this.data.areas ?? [])].sort((a, b) => b.total - a.total).slice(0, 8);
    },

    get areaMax() {
        return Math.max(0, ...(this.data.areas ?? []).map((a) => a.total));
    },

    // The areas with the fewest members, including any with none at all.
    get weakest() {
        const known = new Map((this.data.areas ?? []).map((a) => [a.id, a]));
        const all = config.areaList.map((a) => known.get(a.id) ?? { ...a, total: 0 });
        return all.sort((a, b) => a.total - b.total).slice(0, 5);
    },

    get ticker() {
        const s = this.data.summary;
        if (!s) return ['Establishing link…'];
        const items = [
            `${this.fmt(s.total)} members registered across ${config.scope}`,
            `${this.fmt(s.today)} new sign-ups today · ${this.fmt(s.week)} this week`,
            `${this.fmt(this.data.field.on_shift)} agents on shift · ${this.fmt(this.data.field.households)} households reached today`,
            `Ward coverage ${this.fmt(this.coverage.wards)} of ${this.fmt(this.coverage.wards_total)}`,
        ];
        const top = this.strongest[0];
        if (top) items.push(`Leading: ${top.name.toUpperCase()} with ${this.fmt(top.total)} members`);
        this.headlines.slice(0, 3).forEach((h) => items.unshift(`${h.source ? h.source.toUpperCase() + ': ' : ''}${h.title}`));
        (this.data.recent ?? []).slice(0, 4).forEach((r) => items.push(`${r.time} · ${r.name} joined${r.place ? ` in ${r.place}` : ''}`));
        return items;
    },

    ratio(have, of) {
        const total = this.coverage[of] || 0;
        return total ? Math.min(1, (this.coverage[have] || 0) / total) : 0;
    },

    fmt(n) {
        return new Intl.NumberFormat('en-NG').format(n ?? 0);
    },

    pct(n, of) {
        return of ? `${Math.round((n / of) * 100)}%` : '0%';
    },

    fullscreen() {
        if (document.fullscreenElement) document.exitFullscreen();
        else document.documentElement.requestFullscreen?.();
    },
});

/**
 * The incident desk: reports from members and agents in the field, polled every few seconds.
 * A new report raises an alert; opening one shows its photos, description, place, reporter
 * (with call and WhatsApp) and the follow-ups, and lets the operator resolve or annotate it.
 */
export const incidentDesk = ({ url, showUrl, csrf }) => ({
    incidents: [],
    counts: { new: 0, open: 0, critical: 0, today: 0 },
    filter: 'open',
    known: null,
    alerts: [],
    detail: null,
    loadingDetail: false,
    photo: 0,
    note: '',
    saving: false,
    error: '',

    init() {
        this.load();
        setInterval(() => document.hidden || this.load(), 8000);
        window.addEventListener('cc-incident-open', (e) => this.open(e.detail.id));
        window.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && this.detail) this.close();
        });
    },

    async load() {
        try {
            const res = await fetch(`${url}?filter=${this.filter}`, { headers: { Accept: 'application/json' } });
            if (!res.ok) return;
            const body = await res.json();
            this.incidents = body.incidents;
            this.counts = body.counts;
            // Anything not seen on the previous poll is new: alert once.
            const ids = new Set(body.incidents.map((i) => i.id));
            if (this.known) {
                const fresh = body.incidents.filter((i) => !this.known.has(i.id) && i.status === 'new');
                if (fresh.length) {
                    this.alerts = [...fresh, ...this.alerts].slice(0, 3);
                    this.beep(fresh.some((i) => i.urgency === 'critical'));
                }
            }
            this.known = new Set([...(this.known ?? []), ...ids]);
            window.dispatchEvent(new CustomEvent('cc-incidents', { detail: body.incidents.filter((i) => ['new', 'seen'].includes(i.status)) }));
        } catch {
            /* next poll */
        }
    },

    setFilter(f) {
        this.filter = f;
        this.load();
    },

    async open(id) {
        this.alerts = this.alerts.filter((a) => a.id !== id);
        this.loadingDetail = true;
        this.error = '';
        this.photo = 0;
        this.note = '';
        try {
            const res = await fetch(showUrl.replace('__ID__', id), { headers: { Accept: 'application/json' } });
            if (!res.ok) throw new Error();
            this.detail = await res.json();
            document.documentElement.style.overflow = 'hidden';
        } catch {
            this.error = 'Could not open that report.';
        } finally {
            this.loadingDetail = false;
        }
        this.load();
    },

    close() {
        this.detail = null;
        document.documentElement.style.overflow = '';
    },

    async act(status) {
        if (!this.detail || this.saving) return;
        this.saving = true;
        try {
            const res = await fetch(showUrl.replace('__ID__', this.detail.id), {
                method: 'PATCH',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
                body: JSON.stringify({ ...(status ? { status } : {}), ...(this.note.trim() ? { note: this.note.trim() } : {}) }),
            });
            if (!res.ok) throw new Error();
            const id = this.detail.id;
            this.note = '';
            await this.open(id);
        } catch {
            this.error = 'That did not save. Try again.';
        } finally {
            this.saving = false;
        }
    },

    showOnMap() {
        if (!this.detail) return;
        window.dispatchEvent(new CustomEvent('cc-fly', { detail: { lat: this.detail.location?.lat, lng: this.detail.location?.lng, lgaId: this.detail.lga_id } }));
        this.close();
        document.querySelector('[aria-label="Live situation map"]')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    },

    callReporter() {
        window.dispatchEvent(new CustomEvent('cc-dial', { detail: { payload: { user_id: this.detail.reporter.id } } }));
    },

    /** A short alarm tone (browsers allow it once the page has been clicked). */
    beep(urgent) {
        try {
            const ctx = this._audio ?? (this._audio = new (window.AudioContext || window.webkitAudioContext)());
            [0, 0.25, ...(urgent ? [0.5, 0.75] : [])].forEach((t) => {
                const osc = ctx.createOscillator();
                const gain = ctx.createGain();
                osc.frequency.value = urgent ? 980 : 760;
                gain.gain.setValueAtTime(0.0001, ctx.currentTime + t);
                gain.gain.exponentialRampToValueAtTime(0.25, ctx.currentTime + t + 0.02);
                gain.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + t + 0.18);
                osc.connect(gain).connect(ctx.destination);
                osc.start(ctx.currentTime + t);
                osc.stop(ctx.currentTime + t + 0.2);
            });
        } catch {
            /* no sound, the banner still shows */
        }
    },

    urgencyClass(i) {
        return { critical: 'bg-[var(--red)] text-white', serious: 'bg-[var(--amber)] text-[#1a1200]', minor: 'bg-white/15 text-[var(--text)]' }[i.urgency];
    },
});

/**
 * The news wire: election and local headlines gathered on the server from news searches,
 * refreshed every few minutes. Stories under an hour old are marked breaking, and the
 * newest reach the FLASH ticker.
 */
export const newsWire = ({ url }) => ({
    items: [],
    topic: '',
    fetchedAt: null,
    seen: new Set(),
    first: true,
    now: Date.now(),

    init() {
        this.load();
        setInterval(() => document.hidden || this.load(), 300000);
        setInterval(() => { this.now = Date.now(); }, 60000);
    },

    async load() {
        try {
            const res = await fetch(url, { headers: { Accept: 'application/json' } });
            if (!res.ok) return;
            const body = await res.json();
            this.items = body.items.map((i) => ({ ...i, fresh: !this.first && !this.seen.has(i.url) }));
            body.items.forEach((i) => this.seen.add(i.url));
            this.fetchedAt = body.fetched_at;
            this.first = false;
            window.dispatchEvent(new CustomEvent('cc-news', { detail: this.items.filter((i) => this.breaking(i)) }));
        } catch {
            /* keep what is shown */
        }
    },

    get topics() {
        return [...new Set(this.items.map((i) => i.topic))];
    },

    get shown() {
        return this.items.filter((i) => !this.topic || i.topic === this.topic).slice(0, 18);
    },

    breaking(item) {
        return this.now - Date.parse(item.published_at) < 3600 * 1000;
    },

    ago(item) {
        const m = Math.max(0, Math.round((this.now - Date.parse(item.published_at)) / 60000));
        if (m < 60) return `${m}m ago`;
        if (m < 1440) return `${Math.round(m / 60)}h ago`;
        return `${Math.round(m / 1440)}d ago`;
    },
});

// The call desk: searchable directory of coordinators, agents and members with call and WhatsApp links.
export const callDesk = ({ url, kinds }) => ({
    kinds,
    kind: '',
    lga: '',
    q: '',
    page: 1,
    contacts: [],
    counts: {},
    total: 0,
    hasMore: false,
    loading: false,
    error: false,
    request: 0,

    init() {
        this.search();
    },

    setKind(kind) {
        this.kind = kind;
        this.search();
    },

    pickLga({ id }) {
        this.lga = String(id);
        this.search();
    },

    search() {
        this.page = 1;
        this.fetch(false);
    },

    more() {
        this.page += 1;
        this.fetch(true);
    },

    async fetch(append) {
        const ticket = ++this.request;
        this.loading = true;
        this.error = false;
        const params = new URLSearchParams(Object.entries({ kind: this.kind, lga_id: this.lga, q: this.q.trim(), page: this.page }).filter(([, v]) => v !== '' && v !== null));
        try {
            const res = await fetch(`${url}?${params}`, { headers: { Accept: 'application/json' } });
            if (!res.ok) throw new Error(String(res.status));
            const body = await res.json();
            if (ticket !== this.request) return;
            this.contacts = append ? [...this.contacts, ...body.contacts] : body.contacts;
            this.counts = body.counts;
            this.total = body.total;
            this.hasMore = body.has_more;
        } catch {
            if (ticket === this.request) this.error = true;
        } finally {
            if (ticket === this.request) this.loading = false;
        }
    },

    fmt(n) {
        return new Intl.NumberFormat('en-NG').format(n ?? 0);
    },
});

/**
 * The phone line (Africa's Talking). Polls the call log every few seconds so a call into the
 * line shows while it rings; places calls by ringing the operator first. The call desk asks
 * for a call with a `cc-ring` window event.
 */
export const phoneLine = ({ enabled, number, url, store, handledUrl, outcomeUrl, directUrl, incomingUrl, csrf, operatorPhone }) => ({
    enabled,
    number,
    calls: [],
    waiting: 0,
    ringMe: '',
    notice: '',
    noticeError: false,
    busy: false,
    timer: null,
    logOpen: false,
    log: { phone: '', answered: true, note: '' },
    logError: '',

    init() {
        try {
            this.ringMe = localStorage.getItem('cc-ring-me') || operatorPhone || '';
        } catch {
            this.ringMe = operatorPhone || '';
        }
        this.load();
        this.timer = setInterval(() => document.hidden || this.load(), 5000);
    },

    get live() {
        return this.calls.filter((c) => c.live && c.direction === 'inbound');
    },

    saveRingMe() {
        try {
            localStorage.setItem('cc-ring-me', this.ringMe.trim());
        } catch {
            /* private mode: just not remembered */
        }
    },

    async load() {
        try {
            const res = await fetch(url, { headers: { Accept: 'application/json' } });
            if (!res.ok) return;
            const body = await res.json();
            this.calls = body.calls;
            this.waiting = body.waiting;
        } catch {
            /* next poll will retry */
        }
    },

    async ring(payload, name) {
        if (!this.enabled) return;
        if (!this.ringMe.trim()) {
            this.say('Enter the phone number we should ring you on first (top of the Phone line panel).', true);
            document.getElementById('phone-line')?.scrollIntoView({ behavior: 'smooth' });
            return;
        }
        this.busy = true;
        this.say(`Placing call to ${name}…`);
        try {
            const res = await fetch(store, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
                body: JSON.stringify({ ...payload, ring_me: this.ringMe.trim() }),
            });
            const body = await res.json().catch(() => ({}));
            if (!res.ok) {
                const first = body.errors ? Object.values(body.errors)[0]?.[0] : null;
                throw new Error(first || body.message || 'The call could not be placed.');
            }
            this.saveRingMe();
            this.say(body.message);
            this.load();
        } catch (e) {
            this.say(e.message, true);
        } finally {
            this.busy = false;
        }
    },

    callBack(call) {
        this.ring({ call_id: call.id }, call.name || call.phone_display);
    },

    /** A call from the operator's own phone (tel: link): log it; the link itself still dials. */
    dialled(payload) {
        fetch(directUrl, {
            method: 'POST',
            keepalive: true,
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
            body: JSON.stringify(payload),
        })
            .then(() => this.load())
            .catch(() => {});
        this.say('Call logged. When you hang up, mark how it went in the Phone line panel.');
    },

    async send(target, method, payload) {
        const res = await fetch(target, {
            method,
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
            body: JSON.stringify(payload),
        });
        const body = await res.json().catch(() => ({}));
        if (!res.ok) {
            const first = body.errors ? Object.values(body.errors)[0]?.[0] : null;
            throw new Error(first || body.message || 'That did not save. Try again.');
        }
        return body;
    },

    async setOutcome(call, outcome) {
        try {
            await this.send(outcomeUrl.replace('__ID__', call.id), 'PATCH', { outcome });
            this.load();
        } catch (e) {
            this.say(e.message, true);
        }
    },

    async logIncoming() {
        this.logError = '';
        try {
            const body = await this.send(incomingUrl, 'POST', this.log);
            this.say(body.name ? `Logged: ${body.name} called.` : 'Call logged.');
            this.log = { phone: '', answered: true, note: '' };
            this.logOpen = false;
            this.load();
        } catch (e) {
            this.logError = e.message;
        }
    },

    async markHandled(call) {
        try {
            await this.send(handledUrl.replace('__ID__', call.id), 'PATCH', {});
        } catch (e) {
            this.say(e.message, true);
        }
        this.load();
    },

    say(text, error = false) {
        this.notice = text;
        this.noticeError = error;
        clearTimeout(this._noticeTimer);
        this._noticeTimer = setTimeout(() => (this.notice = ''), 12000);
    },

    statusLabel(c) {
        return {
            ringing: c.direction === 'inbound' ? 'Ringing' : 'Ringing you',
            connected: 'On call',
            completed: 'Answered',
            missed: 'Missed',
            voicemail: 'Voice message',
            no_answer: 'No answer',
            failed: 'You missed it',
            dialled: 'How did it go?',
            wrong_number: 'Wrong number',
            call_later: 'Call again',
        }[c.status] ?? c.status;
    },

    statusClass(c) {
        if (c.live) return 'bg-[var(--red)]/20 text-[var(--red)]';
        if (c.status === 'dialled') return 'bg-[var(--glow)]/25 text-white';
        if (c.needs_call_back) return 'bg-[var(--amber)]/20 text-[var(--amber)]';
        if (c.status === 'completed') return 'bg-[var(--glow)]/15 text-[var(--glow)]';
        return 'bg-white/10 text-[var(--dim)]';
    },

    duration(s) {
        if (!s) return '';
        return `${Math.floor(s / 60)}:${String(s % 60).padStart(2, '0')}`;
    },
});
