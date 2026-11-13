// Choropleth for the homepage (states) and coordinator dashboards (LGAs).
// Shapes are drawn on the server as SVG links; this only colours them by member
// count and shows a label on hover, focus or tap. On touch screens the first tap
// shows the label and the second follows the link.
const SCALE = ['#d6ecd9', '#a8d8b1', '#6fbf80', '#3a9a52', '#1c6b33'];
const EMPTY = '#e9eee9';

export default ({ url, noun = 'members' }) => ({
    counts: {},
    total: 0,
    max: 0,
    loaded: false,
    failed: false,
    tip: null,
    selected: null,
    lastPointer: 'mouse',

    async init() {
        try {
            const res = await fetch(url, { headers: { Accept: 'application/json' } });
            if (!res.ok) throw new Error(String(res.status));
            const body = await res.json();
            this.counts = body.counts || {};
            this.total = body.total || 0;
        } catch {
            this.failed = true;
        }
        this.max = Math.max(0, ...Object.values(this.counts));
        this.loaded = true;
        this.paint();
    },

    count(key) { return Number(this.counts[key] || 0); },

    shade(n) {
        if (!n || !this.max) return EMPTY;
        // Square root keeps a few big states from washing out everyone else.
        const i = Math.min(SCALE.length - 1, Math.floor(Math.sqrt(n / this.max) * SCALE.length));
        return SCALE[i];
    },

    get legend() {
        if (!this.max) return [];
        return SCALE.map((colour, i) => ({
            colour,
            from: Math.ceil(this.max * (i / SCALE.length) ** 2) || 1,
        }));
    },

    paint() {
        this.$root.querySelectorAll('[data-area]').forEach((el) => {
            const n = this.count(el.dataset.area);
            el.querySelector('path').style.fill = this.shade(n);
            el.setAttribute('aria-label', `${el.dataset.name}: ${n.toLocaleString()} ${noun}. ${el.dataset.action || ''}`.trim());
        });
    },

    label(el) {
        const n = this.count(el.dataset.area);
        return { name: el.dataset.name, text: `${n.toLocaleString()} ${n === 1 ? noun.replace(/s$/, '') : noun}`, action: el.dataset.action };
    },

    place(el, e) {
        const box = this.$refs.frame.getBoundingClientRect();
        let x, y;
        if (e && 'clientX' in e && e.clientX) {
            x = e.clientX - box.left; y = e.clientY - box.top;
        } else {
            const r = el.getBoundingClientRect();
            x = r.left + r.width / 2 - box.left; y = r.top + r.height / 2 - box.top;
        }
        this.tip = { ...this.label(el), x: Math.min(Math.max(x, 80), box.width - 80), y, above: y > 90 };
    },

    over(e) {
        const el = e.target.closest('[data-area]');
        if (!el) return;
        this.lastPointer = e.pointerType || 'mouse';
        if (this.lastPointer === 'mouse') this.place(el, e);
    },

    move(e) {
        const el = e.target.closest('[data-area]');
        if (el && (e.pointerType || 'mouse') === 'mouse') this.place(el, e);
    },

    leave() { if (this.lastPointer === 'mouse') this.tip = null; },

    focus(e) {
        const el = e.target.closest('[data-area]');
        if (el) this.place(el);
    },

    tap(e) {
        const el = e.target.closest('[data-area]');
        if (!el || this.lastPointer === 'mouse') return;
        // Touch: first tap shows the label, the second tap follows the link.
        if (this.selected !== el.dataset.area) {
            e.preventDefault();
            this.selected = el.dataset.area;
            this.place(el, e);
        }
    },
});
