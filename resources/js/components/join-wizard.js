// The four-step sign-up form. Each step is checked on the server before moving
// on; nothing is lost going back, and non-sensitive answers survive a reload.
const STEPS = ['account', 'details', 'location', 'finish'];
const STORE_KEY = 'rhye-join';
const NEVER_STORED = ['password', 'password_confirmation', 'nin', 'vin', 'website'];

const FIELD_STEP = {
    email: 0, phone: 0, password: 0,
    name: 1, gender: 1, age_range: 1, occupation: 1,
    state_id: 2, lga_id: 2, ward_id: 2, polling_unit_id: 2, has_pvc: 2, vin: 2, nin: 2, latitude: 2, longitude: 2,
};

export default (config) => ({
    step: 0,
    busy: false,
    errors: {},
    general: '',
    lgas: [], wards: [], units: [],
    loading: { lgas: false, wards: false, units: false },
    geo: { status: 'idle', message: '' },
    form: {
        email: '', phone: '', password: '', password_confirmation: '',
        name: '', gender: '', age_range: '', occupation: '',
        state_id: '', lga_id: '', ward_id: '', polling_unit_id: '', has_pvc: null, vin: '', nin: '',
        latitude: null, longitude: null,
        referrer: config.ref || '', consent: false, website: '',
        canvasser_roles: [], whatsapp_phone: '',
    },

    async init() {
        let saved = null;
        try { saved = JSON.parse(sessionStorage.getItem(STORE_KEY) || 'null'); } catch { /* storage blocked */ }
        if (saved && saved.canvasser === config.canvasser) {
            Object.assign(this.form, saved.form, config.ref ? { referrer: config.ref } : {});
            this.step = Math.min(saved.step || 0, 3);
            // Re-load the dropdowns the saved answers depend on.
            const { lga_id, ward_id, polling_unit_id } = this.form;
            if (this.form.state_id) await this.loadLgas(true);
            this.form.lga_id = lga_id;
            if (lga_id) await this.loadWards(true);
            this.form.ward_id = ward_id;
            if (ward_id) await this.loadUnits(true);
            this.form.polling_unit_id = polling_unit_id;
            // Passwords are never stored, so a reload past step 1 needs them again.
            if (this.step > 0 && !this.form.password) this.step = 0;
        }
        this.$watch('form', () => this.save(), { deep: true });
        this.$watch('step', () => this.save());
    },

    save() {
        const form = { ...this.form };
        NEVER_STORED.forEach((k) => delete form[k]);
        try { sessionStorage.setItem(STORE_KEY, JSON.stringify({ canvasser: config.canvasser, step: this.step, form })); } catch { /* ignore */ }
    },

    get stepName() { return STEPS[this.step]; },
    get wardLabel() {
        if (!this.form.lga_id) return '';
        if (this.loading.wards) return 'Loading wards…';
        return this.wards.length ? `${this.wards.length} wards in this LGA` : 'Wards for this LGA are not loaded yet. You can carry on without one.';
    },

    err(field) { return this.errors[field]?.[0] || ''; },

    async getJson(url) {
        const res = await fetch(url, { headers: { Accept: 'application/json' } });
        if (!res.ok) throw new Error(String(res.status));
        return (await res.json()).data;
    },

    async loadLgas(keep = false) {
        if (!keep) { this.form.lga_id = ''; this.form.ward_id = ''; this.form.polling_unit_id = ''; }
        this.lgas = []; this.wards = []; this.units = [];
        if (!this.form.state_id) return;
        this.loading.lgas = true;
        try { this.lgas = await this.getJson(config.urls.lgas.replace('__ID__', this.form.state_id)); }
        catch { this.general = 'Could not load the LGAs. Check your connection and choose the state again.'; }
        finally { this.loading.lgas = false; }
    },

    async loadWards(keep = false) {
        if (!keep) { this.form.ward_id = ''; this.form.polling_unit_id = ''; }
        this.wards = []; this.units = [];
        if (!this.form.lga_id) return;
        this.loading.wards = true;
        try { this.wards = await this.getJson(config.urls.wards.replace('__ID__', this.form.lga_id)); }
        catch { this.general = 'Could not load the wards. Check your connection and choose the LGA again.'; }
        finally { this.loading.wards = false; }
    },

    async loadUnits(keep = false) {
        if (!keep) this.form.polling_unit_id = '';
        this.units = [];
        if (!this.form.ward_id) return;
        this.loading.units = true;
        try { this.units = await this.getJson(config.urls.units.replace('__ID__', this.form.ward_id)); }
        catch { /* optional field: fail quietly */ }
        finally { this.loading.units = false; }
    },

    locate() {
        if (!('geolocation' in navigator)) {
            this.geo = { status: 'error', message: 'Your browser cannot share its location.' };
            return;
        }
        this.geo = { status: 'busy', message: 'Finding you…' };
        navigator.geolocation.getCurrentPosition(
            (pos) => {
                this.form.latitude = Number(pos.coords.latitude.toFixed(6));
                this.form.longitude = Number(pos.coords.longitude.toFixed(6));
                this.geo = { status: 'done', message: 'Location added. Thank you.' };
            },
            (e) => this.geo = { status: 'error', message: e.code === 1 ? 'You did not allow location access. That is fine: it is optional.' : 'We could not get your location. That is fine: it is optional.' },
            { enableHighAccuracy: false, timeout: 15000, maximumAge: 300000 },
        );
    },

    clearLocation() {
        this.form.latitude = null; this.form.longitude = null;
        this.geo = { status: 'idle', message: '' };
    },

    payload() {
        const data = { ...this.form, canvasser: config.canvasser ? 1 : 0, as: config.canvasser ? 'canvasser' : 'member' };
        if (data.has_pvc !== null) data.has_pvc = data.has_pvc ? 1 : 0;
        data.consent = data.consent ? 1 : 0;
        Object.keys(data).forEach((k) => (data[k] === null || data[k] === '') && delete data[k]);
        return data;
    },

    async post(url) {
        const res = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': config.csrf },
            body: JSON.stringify(this.payload()),
        });
        const body = await res.json().catch(() => ({}));
        return { status: res.status, body };
    },

    async next() {
        if (this.busy) return;
        this.busy = true; this.errors = {}; this.general = '';
        try {
            const last = this.step === STEPS.length - 1;
            const { status, body } = await this.post(last ? config.urls.store : config.urls.check.replace('__STEP__', this.stepName));

            if (status === 422) {
                this.errors = body.errors || {};
                const steps = Object.keys(this.errors).map((f) => FIELD_STEP[f.split('.')[0]] ?? 3);
                const first = Math.min(...steps);
                if (first < this.step) this.step = first;
                this.general = 'Please check the highlighted answers.';
                this.$nextTick(() => this.$root.querySelector('[aria-invalid="true"]')?.focus());
                return;
            }
            if (status === 419) { this.general = 'This page expired. Refresh the page and try again; your answers are kept.'; return; }
            if (status === 429) { this.general = 'Too many attempts from this network. Please wait a minute and try again.'; return; }
            if (status >= 400) { this.general = 'Something went wrong on our side. Please try again.'; return; }

            if (last) {
                try { sessionStorage.removeItem(STORE_KEY); } catch { /* ignore */ }
                window.location.assign(body.redirect);
                return;
            }
            this.step++;
            window.scrollTo({ top: 0, behavior: 'smooth' });
            this.$nextTick(() => this.$root.querySelector(`[data-step="${this.step}"] input, [data-step="${this.step}"] select`)?.focus({ preventScroll: true }));
        } catch {
            this.general = 'No connection. Check your data or Wi-Fi and tap Continue again; your answers are kept.';
        } finally {
            this.busy = false;
        }
    },

    back() {
        this.errors = {}; this.general = '';
        if (this.step > 0) this.step--;
        window.scrollTo({ top: 0, behavior: 'smooth' });
    },
});
