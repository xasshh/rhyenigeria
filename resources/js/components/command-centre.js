// The election command centre (admin/command.blade.php): polls the scoped feed,
// eases the numbers towards their new values and paints the situation map.
const reduceMotion = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;
const KEYS = ['total', 'today', 'week', 'agents', 'on_shift', 'pvc'];
// Dark-to-glow scale for the map; areas with no members stay near-black.
const SCALE = ['#0f3a26', '#13573a', '#178050', '#1fb26c', '#2ee59d'];
const EMPTY = '#0b1f15';

export default (config) => ({
    data: {},
    display: Object.fromEntries(KEYS.map((k) => [k, 0])),
    bumped: {},
    coverage: { wards: 0, wards_total: 0, areas: 0, areas_total: config.areasTotal },
    tip: null,
    picked: null,
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
        this.paintMap();
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

    paintMap() {
        const byId = Object.fromEntries((this.data.areas ?? []).map((a) => [String(a.id), a]));
        const max = Math.max(1, ...(this.data.areas ?? []).map((a) => a.total));
        this.$refs.map.querySelectorAll('path[data-id]').forEach((path) => {
            const area = byId[path.dataset.id];
            const total = area?.total ?? 0;
            // Square root so a few big areas do not wash every other one out.
            const level = total ? Math.min(SCALE.length - 1, Math.floor(Math.sqrt(total / max) * SCALE.length)) : -1;
            path.setAttribute('fill', level < 0 ? EMPTY : SCALE[level]);
            path.classList.toggle('cc-hot', (area?.today ?? 0) > 0);
        });
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

    hover(event) {
        const path = event.target.closest?.('path[data-id]');
        if (!path) { this.tip = null; return; }
        const area = (this.data.areas ?? []).find((a) => String(a.id) === path.dataset.id);
        const box = this.$refs.mapFrame.getBoundingClientRect();
        this.tip = { name: path.dataset.name, total: area?.total ?? 0, today: area?.today ?? 0, agents: area?.agents ?? 0, x: event.clientX - box.left, y: event.clientY - box.top };
    },

    // Tapping an area selects it (its figures show under the map); clicking with a mouse goes straight to its people.
    pickArea(event) {
        const path = event.target.closest?.('path[data-id]');
        if (!path) return;
        const area = (this.data.areas ?? []).find((a) => String(a.id) === path.dataset.id);
        this.picked = { id: path.dataset.id, name: path.dataset.name, total: area?.total ?? 0, today: area?.today ?? 0 };
        if (event.pointerType === 'mouse') this.callArea();
        else this.hover(event);
    },

    callArea() {
        if (!this.picked) return;
        window.dispatchEvent(new CustomEvent('cc-lga', { detail: { id: this.picked.id, name: this.picked.name } }));
        document.getElementById('call-desk')?.scrollIntoView({ behavior: reduceMotion() ? 'auto' : 'smooth' });
    },

    get strongest() {
        return [...(this.data.areas ?? [])].sort((a, b) => b.total - a.total).slice(0, 8);
    },

    get areaMax() {
        return Math.max(0, ...(this.data.areas ?? []).map((a) => a.total));
    },

    // The areas with the fewest members, including any with none at all.
    get weakest() {
        const known = new Map((this.data.areas ?? []).map((a) => [String(a.id), a]));
        const all = [...this.$refs.map.querySelectorAll('path[data-id]')].map((p) => known.get(p.dataset.id) ?? { id: Number(p.dataset.id), name: p.dataset.name, total: 0 });
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
