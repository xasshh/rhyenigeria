// The coordinators' live map (staff/live.blade.php). Loaded only on that page.
// Positions, activities and the state outline come from the scoped feed; a
// canvasser's route is fetched (and audit-logged) only when someone opens it.
import Alpine from 'alpinejs';
import * as maplibregl from 'maplibre-gl';
import 'maplibre-gl/dist/maplibre-gl.css';
// MapLibre 6 draws in a web worker; publish the worker file with the build and point MapLibre at it.
import workerUrl from 'maplibre-gl/dist/maplibre-gl-worker.mjs?url';

maplibregl.setWorkerUrl(workerUrl);

const STATUS_COLOURS = { active: '#2e7d3f', quiet: '#f2b705', off: '#9aa69e' };
const KIND_COLOURS = { door_to_door: '#2563eb', event_check_in: '#c026d3', report: '#ea580c' };
const NIGERIA = [[2.6, 4.2], [14.7, 13.9]];

const bbox = (geometry) => {
    let minX = 180, minY = 90, maxX = -180, maxY = -90;
    const walk = (c) => (typeof c[0] === 'number'
        ? (minX = Math.min(minX, c[0]), minY = Math.min(minY, c[1]), maxX = Math.max(maxX, c[0]), maxY = Math.max(maxY, c[1]))
        : c.forEach(walk));
    walk(geometry.coordinates);
    return [[minX, minY], [maxX, maxY]];
};

Alpine.data('liveMap', (config) => ({
    map: null,
    data: { canvassers: [], activities: [], totals: {} },
    loading: true,
    error: '',
    selected: null,
    trailFor: null,
    filter: 'on_shift',
    updatedAt: null,

    init() {
        this.map = new maplibregl.Map({
            container: this.$refs.map,
            style: config.style,
            bounds: config.outline ? bbox(config.outline) : NIGERIA,
            fitBoundsOptions: { padding: 30 },
            attributionControl: { compact: true },
        });
        this.map.addControl(new maplibregl.NavigationControl({ showCompass: false }), 'top-right');
        this.map.addControl(new maplibregl.FullscreenControl(), 'top-right');
        this.map.on('load', () => {
            this.addLayers();
            this.refresh();
            this.timer = setInterval(() => document.visibilityState === 'visible' && this.refresh(), config.refreshSeconds * 1000);
        });
    },

    destroy() { clearInterval(this.timer); this.map?.remove(); },

    addLayers() {
        const m = this.map;
        const empty = { type: 'FeatureCollection', features: [] };
        if (config.outline) {
            m.addSource('outline', { type: 'geojson', data: { type: 'Feature', geometry: config.outline, properties: {} } });
            m.addLayer({ id: 'outline-fill', type: 'fill', source: 'outline', paint: { 'fill-color': '#2e7d3f', 'fill-opacity': 0.04 } });
            m.addLayer({ id: 'outline-line', type: 'line', source: 'outline', paint: { 'line-color': '#2e7d3f', 'line-width': 2, 'line-dasharray': [2, 1] } });
        }
        m.addSource('trail', { type: 'geojson', data: empty });
        m.addLayer({ id: 'trail', type: 'line', source: 'trail', layout: { 'line-cap': 'round', 'line-join': 'round' }, paint: { 'line-color': '#0f2a1c', 'line-width': 4, 'line-opacity': 0.75 } });
        m.addSource('activities', { type: 'geojson', data: empty });
        m.addLayer({
            id: 'activities', type: 'circle', source: 'activities',
            paint: {
                'circle-radius': 6, 'circle-stroke-width': 2, 'circle-stroke-color': '#fff',
                'circle-color': ['match', ['get', 'kind'], ...Object.entries(KIND_COLOURS).flat(), '#64748b'],
            },
        });
        m.addSource('people', { type: 'geojson', data: empty });
        m.addLayer({
            id: 'people-halo', type: 'circle', source: 'people',
            paint: { 'circle-radius': ['case', ['get', 'selected'], 18, 13], 'circle-color': ['get', 'colour'], 'circle-opacity': 0.25 },
        });
        m.addLayer({
            id: 'people', type: 'circle', source: 'people',
            paint: { 'circle-radius': 8, 'circle-color': ['get', 'colour'], 'circle-stroke-width': 3, 'circle-stroke-color': '#fff' },
        });
        m.addLayer({
            id: 'people-label', type: 'symbol', source: 'people',
            layout: { 'text-field': ['get', 'name'], 'text-size': 12, 'text-offset': [0, 1.5], 'text-anchor': 'top', 'text-font': ['Noto Sans Regular'] },
            paint: { 'text-color': '#0f2a1c', 'text-halo-color': '#fff', 'text-halo-width': 1.5 },
        });

        m.on('click', 'people', (e) => this.select(Number(e.features[0].properties.id)));
        m.on('click', 'activities', (e) => this.popupActivity(e.features[0], e.lngLat));
        ['people', 'activities'].forEach((id) => {
            m.on('mouseenter', id, () => (m.getCanvas().style.cursor = 'pointer'));
            m.on('mouseleave', id, () => (m.getCanvas().style.cursor = ''));
        });
    },

    async refresh() {
        try {
            const res = await fetch(config.feed, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
            if (!res.ok) throw new Error(String(res.status));
            this.data = await res.json();
            this.error = '';
            this.updatedAt = new Date();
            this.draw();
        } catch {
            this.error = 'Could not refresh the map. Retrying…';
        } finally {
            this.loading = false;
        }
    },

    get list() {
        return this.data.canvassers.filter((c) => this.filter === 'all' || c.on_shift);
    },

    draw() {
        const people = this.data.canvassers.filter((c) => c.position).map((c) => ({
            type: 'Feature',
            geometry: { type: 'Point', coordinates: [c.position.longitude, c.position.latitude] },
            properties: { id: c.id, name: c.name, colour: STATUS_COLOURS[c.status], selected: c.id === this.selected },
        }));
        this.map.getSource('people')?.setData({ type: 'FeatureCollection', features: people });
        const acts = this.data.activities
            .filter((a) => !this.selected || a.user_id === this.selected)
            .map((a) => ({ type: 'Feature', geometry: { type: 'Point', coordinates: [a.longitude, a.latitude] }, properties: { ...a } }));
        this.map.getSource('activities')?.setData({ type: 'FeatureCollection', features: acts });
    },

    async select(id) {
        this.selected = this.selected === id ? null : id;
        this.draw();
        const empty = { type: 'FeatureCollection', features: [] };
        this.map.getSource('trail')?.setData(empty);
        if (!this.selected) return;
        // On phones the list sits under the map: bring the map back into view.
        if (window.innerWidth < 1024) this.$refs.map.scrollIntoView({ behavior: 'smooth', block: 'center' });
        const person = this.data.canvassers.find((c) => c.id === id);
        if (person?.position) this.map.flyTo({ center: [person.position.longitude, person.position.latitude], zoom: Math.max(this.map.getZoom(), 14) });
        try {
            const res = await fetch(config.trail.replace('__ID__', id), { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
            const trail = await res.json();
            if (this.selected === id) this.map.getSource('trail')?.setData(trail);
        } catch { /* the route is optional */ }
    },

    popupActivity(feature, lngLat) {
        const p = feature.properties;
        const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
        const detail = p.kind === 'door_to_door' ? `${esc(p.households)} households · ${esc(p.outcome)}`
            : p.kind === 'event_check_in' ? esc(p.event) : esc(p.note);
        const photo = p.photo ? `<a href="${esc(p.photo)}" target="_blank" rel="noopener" class="mt-2 block font-semibold text-[#2e7d3f]">View photo</a>` : '';
        new maplibregl.Popup({ offset: 10, maxWidth: '260px' }).setLngLat(lngLat).setHTML(
            `<p class="text-xs text-neutral-500">${esc(p.time)}${p.ward ? ' · ' + esc(p.ward) : ''}</p>
             <p class="font-semibold">${esc(p.label)}</p><p class="text-sm">${detail}</p>
             <p class="mt-1 text-xs text-neutral-500">by ${esc(p.canvasser)}</p>${photo}`,
        ).addTo(this.map);
    },

    seen(c) {
        if (c.seen_minutes_ago === null) return 'No location yet today';
        if (c.seen_minutes_ago < 1) return 'Seen just now';
        if (c.seen_minutes_ago < 60) return `Seen ${c.seen_minutes_ago} min ago`;
        return `Seen ${Math.floor(c.seen_minutes_ago / 60)} h ago`;
    },

    colour(status) { return STATUS_COLOURS[status]; },
    kindColour(kind) { return KIND_COLOURS[kind]; },
    memberUrl(id) { return config.member.replace('__ID__', id); },
}));

// The members map (staff/members-map.blade.php): one pin per member who shared where they are,
// clustered when zoomed out. Shares this bundle so MapLibre is set up once.
const PIN_COLOURS = { member: '#2563eb', canvasser: '#2e7d3f' };

Alpine.data('membersMap', (config) => ({
    map: null,
    loading: true,
    error: '',
    shown: 0,
    truncated: false,
    show: { member: true, canvasser: true },
    features: [],

    init() {
        this.map = new maplibregl.Map({
            container: this.$refs.map,
            style: config.style,
            bounds: config.outline ? bbox(config.outline) : NIGERIA,
            fitBoundsOptions: { padding: 30 },
            attributionControl: { compact: true },
        });
        this.map.addControl(new maplibregl.NavigationControl({ showCompass: false }), 'top-right');
        this.map.addControl(new maplibregl.FullscreenControl(), 'top-right');
        this.map.on('load', () => { this.addLayers(); this.load(); });
        this.$watch('show', () => this.draw(), { deep: true });
    },

    destroy() { this.map?.remove(); },

    addLayers() {
        const m = this.map;
        if (config.outline) {
            m.addSource('outline', { type: 'geojson', data: { type: 'Feature', geometry: config.outline, properties: {} } });
            m.addLayer({ id: 'outline-fill', type: 'fill', source: 'outline', paint: { 'fill-color': '#2e7d3f', 'fill-opacity': 0.04 } });
            m.addLayer({ id: 'outline-line', type: 'line', source: 'outline', paint: { 'line-color': '#2e7d3f', 'line-width': 2, 'line-dasharray': [2, 1] } });
        }
        m.addSource('pins', { type: 'geojson', data: { type: 'FeatureCollection', features: [] }, cluster: true, clusterRadius: 45, clusterMaxZoom: 14 });
        m.addLayer({
            id: 'clusters', type: 'circle', source: 'pins', filter: ['has', 'point_count'],
            paint: {
                'circle-color': ['step', ['get', 'point_count'], '#7fbf8e', 25, '#2e7d3f', 250, '#0f2a1c'],
                'circle-radius': ['step', ['get', 'point_count'], 16, 25, 22, 250, 30],
                'circle-stroke-width': 3, 'circle-stroke-color': '#fff', 'circle-opacity': 0.9,
            },
        });
        m.addLayer({
            id: 'cluster-count', type: 'symbol', source: 'pins', filter: ['has', 'point_count'],
            layout: { 'text-field': ['get', 'point_count_abbreviated'], 'text-size': 12, 'text-font': ['Noto Sans Bold'] },
            paint: { 'text-color': '#fff' },
        });
        m.addLayer({
            id: 'pins', type: 'circle', source: 'pins', filter: ['!', ['has', 'point_count']],
            paint: {
                'circle-radius': 7, 'circle-stroke-width': 2, 'circle-stroke-color': '#fff',
                'circle-color': ['case', ['get', 'canvasser'], PIN_COLOURS.canvasser, PIN_COLOURS.member],
            },
        });

        m.on('click', 'clusters', async (e) => {
            const f = e.features[0];
            const zoom = await m.getSource('pins').getClusterExpansionZoom(f.properties.cluster_id);
            m.easeTo({ center: f.geometry.coordinates, zoom });
        });
        m.on('click', 'pins', (e) => this.popup(e.features[0]));
        ['clusters', 'pins'].forEach((id) => {
            m.on('mouseenter', id, () => (m.getCanvas().style.cursor = 'pointer'));
            m.on('mouseleave', id, () => (m.getCanvas().style.cursor = ''));
        });
    },

    async load() {
        try {
            const res = await fetch(config.pins, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
            if (!res.ok) throw new Error(String(res.status));
            const data = await res.json();
            this.features = data.features;
            this.truncated = data.truncated;
            this.draw();
        } catch {
            this.error = 'Could not load the members map. Reload the page to try again.';
        } finally {
            this.loading = false;
        }
    },

    draw() {
        const features = this.features.filter((f) => this.show[f.properties.canvasser ? 'canvasser' : 'member']);
        this.shown = features.length;
        this.map.getSource('pins')?.setData({ type: 'FeatureCollection', features });
    },

    popup(feature) {
        const p = feature.properties;
        const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
        new maplibregl.Popup({ offset: 10, maxWidth: '260px' }).setLngLat(feature.geometry.coordinates).setHTML(
            `<p class="font-semibold">${esc(p.name)}</p>
             <p class="text-xs text-neutral-500">${p.canvasser ? 'Canvasser' : 'Member'}${p.area ? ' · ' + esc(p.area) : ''}</p>
             ${p.pinned ? `<p class="text-xs text-neutral-500">Pinned ${esc(p.pinned)}</p>` : ''}
             <a href="${esc(config.member.replace('__ID__', p.id))}" class="mt-2 block font-semibold text-[#2e7d3f]">Open profile</a>`,
        ).addTo(this.map);
    },

    colour(kind) { return PIN_COLOURS[kind]; },
}));

// The election command centre's live map (admin/command.blade.php): a dark, tiltable map of the
// theatre with members by area in 3D, today's sign-ups pulsing, members' pinned locations as a
// heat layer, and the field team and their reports live. Area figures arrive from the command
// centre's own feed as a `cc-feed` window event; positions are polled here.
const CC_SCALE = ['#0f3a26', '#13573a', '#178050', '#1fb26c', '#2ee59d'];
const CC_AGENT = { active: '#2ee59d', quiet: '#f2b705', off: '#7a8a82' };
const CC_KIND = { door_to_door: '#38bdf8', event_check_in: '#e879f9', report: '#fb923c' };
const ccEsc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

Alpine.data('commandMap', (config) => {
    let map = null;
    let bounds = null;
    let geo = null;
    let areas = {};
    let hoverId = null;
    let patrolTimer = null;
    let patrolIndex = 0;
    let frame = null;

    const national = config.areaNoun !== 'LGA';
    const maxHeight = national ? 90000 : 14000;
    const point = (lng, lat, properties) => ({ type: 'Feature', geometry: { type: 'Point', coordinates: [lng, lat] }, properties });

    return {
        ready: false,
        failed: false,
        three: true,
        patrol: false,
        layers: { heat: true, agents: true, reports: true, pulses: true, incidents: true },
        incidentCount: 0,
        live: { agents: [], activities: [], totals: {} },
        heatCount: 0,
        tip: null,
        picked: null,
        detail: null,
        hud: '',

        init() {
            try {
                map = new maplibregl.Map({
                    container: this.$refs.canvas,
                    style: config.style,
                    bounds: NIGERIA,
                    pitch: 50,
                    bearing: -15,
                    maxPitch: 70,
                    attributionControl: { compact: true },
                });
            } catch {
                this.failed = true;
                return;
            }
            map.addControl(new maplibregl.NavigationControl({ visualizePitch: true }), 'top-right');
            map.on('error', () => {});
            map.on('move', () => this.updateHud());
            ['dragstart', 'wheel', 'touchstart'].forEach((e) => map.on(e, (ev) => ev.originalEvent && this.stopPatrol()));

            window.addEventListener('cc-feed', (e) => this.paint(e.detail));
            window.addEventListener('cc-incidents', (e) => this.plotIncidents(e.detail));
            window.addEventListener('cc-fly', (e) => this.flyTo(e.detail));
            map.on('load', async () => {
                try {
                    const res = await fetch(config.geo, { headers: { Accept: 'application/json' } });
                    geo = await res.json();
                } catch {
                    this.failed = true;
                    return;
                }
                bounds = bbox({ coordinates: geo.areas.features.map((f) => f.geometry.coordinates) });
                this.addLayers();
                this.home(false);
                this.ready = true;
                this.paint(window.__ccAreas ?? []);
                this.plotIncidents(window.__ccIncidents ?? []);
                this.loadLive();
                this.loadPins();
                setInterval(() => document.hidden || this.loadLive(), 15000);
                setInterval(() => document.hidden || this.loadPins(), 300000);
                this.animate();
            });
        },

        addLayers() {
            const empty = { type: 'FeatureCollection', features: [] };
            map.addSource('areas', { type: 'geojson', data: geo.areas });
            map.addSource('labels', { type: 'geojson', data: geo.labels });
            map.addSource('pulses', { type: 'geojson', data: empty });
            map.addSource('heat', { type: 'geojson', data: empty });
            map.addSource('reports', { type: 'geojson', data: empty });
            map.addSource('agents', { type: 'geojson', data: empty });
            map.addSource('incidents', { type: 'geojson', data: empty });

            const fillColour = ['case', ['>', ['coalesce', ['feature-state', 'total'], 0], 0],
                ['interpolate', ['linear'], ['coalesce', ['feature-state', 'level'], 0], 0, CC_SCALE[0], 0.25, CC_SCALE[1], 0.5, CC_SCALE[2], 0.75, CC_SCALE[3], 1, CC_SCALE[4]],
                '#0b1f15'];
            map.addLayer({ id: 'areas-flat', type: 'fill', source: 'areas', layout: { visibility: 'none' }, paint: { 'fill-color': fillColour, 'fill-opacity': ['interpolate', ['linear'], ['zoom'], 9, ['case', ['boolean', ['feature-state', 'hover'], false], 0.95, 0.72], 12.5, 0.1] } });
            map.addLayer({
                id: 'areas-3d', type: 'fill-extrusion', source: 'areas',
                paint: {
                    'fill-extrusion-color': ['case', ['boolean', ['feature-state', 'hover'], false], '#7dffc8', fillColour],
                    'fill-extrusion-height': ['*', ['coalesce', ['feature-state', 'level'], 0], maxHeight],
                    'fill-extrusion-base': 0,
                    // Fade the blocks out close up, so the streets underneath show.
                    'fill-extrusion-opacity': ['interpolate', ['linear'], ['zoom'], 9, 0.82, 12.5, 0.12],
                    'fill-extrusion-height-transition': { duration: 1200 },
                },
            });
            map.addLayer({ id: 'areas-line', type: 'line', source: 'areas', paint: { 'line-color': '#2ee59d', 'line-opacity': ['case', ['boolean', ['feature-state', 'hover'], false], 1, 0.45], 'line-width': ['case', ['boolean', ['feature-state', 'hover'], false], 2.5, 1] } });
            map.addLayer({
                id: 'heat', type: 'heatmap', source: 'heat',
                paint: {
                    'heatmap-radius': ['interpolate', ['linear'], ['zoom'], 5, 8, 10, 22, 14, 40],
                    'heatmap-intensity': ['interpolate', ['linear'], ['zoom'], 5, 0.6, 12, 1.6],
                    'heatmap-opacity': 0.75,
                    'heatmap-color': ['interpolate', ['linear'], ['heatmap-density'], 0, 'rgba(0,0,0,0)', 0.2, '#0e7490', 0.45, '#22d3ee', 0.7, '#a5f3fc', 1, '#ffffff'],
                },
            });
            map.addLayer({ id: 'pulses', type: 'circle', source: 'pulses', paint: { 'circle-color': 'rgba(0,0,0,0)', 'circle-stroke-color': '#f2b705', 'circle-stroke-width': 2, 'circle-radius': 10, 'circle-stroke-opacity': 0.8, 'circle-pitch-alignment': 'map' } });
            map.addLayer({ id: 'pulses-core', type: 'circle', source: 'pulses', paint: { 'circle-color': '#f2b705', 'circle-radius': 4, 'circle-blur': 0.3 } });
            map.addLayer({
                id: 'labels', type: 'symbol', source: 'labels',
                layout: { 'text-field': ['upcase', ['get', 'name']], 'text-font': ['Noto Sans Regular'], 'text-size': ['interpolate', ['linear'], ['zoom'], 6, 9, 10, 13], 'text-letter-spacing': 0.12, 'text-allow-overlap': false },
                paint: { 'text-color': '#bff7dc', 'text-halo-color': '#020805', 'text-halo-width': 1.6 },
            });
            map.addLayer({ id: 'reports', type: 'circle', source: 'reports', paint: { 'circle-radius': 5, 'circle-color': ['match', ['get', 'kind'], ...Object.entries(CC_KIND).flat(), '#94a3b8'], 'circle-stroke-width': 1.5, 'circle-stroke-color': '#020805' } });
            map.addLayer({ id: 'incidents-halo', type: 'circle', source: 'incidents', paint: { 'circle-radius': 18, 'circle-color': '#ff4d4f', 'circle-opacity': 0.3, 'circle-blur': 0.3 } });
            map.addLayer({ id: 'incidents', type: 'circle', source: 'incidents', paint: { 'circle-radius': ['case', ['==', ['get', 'urgency'], 'critical'], 9, 7], 'circle-color': ['match', ['get', 'urgency'], 'critical', '#ff4d4f', 'serious', '#f2b705', '#cbd5e1'], 'circle-stroke-width': 2.5, 'circle-stroke-color': '#fff' } });
            map.addLayer({ id: 'agents-halo', type: 'circle', source: 'agents', paint: { 'circle-radius': 14, 'circle-color': ['get', 'colour'], 'circle-opacity': 0.25, 'circle-blur': 0.4 } });
            map.addLayer({ id: 'agents', type: 'circle', source: 'agents', paint: { 'circle-radius': 6.5, 'circle-color': ['get', 'colour'], 'circle-stroke-width': 2, 'circle-stroke-color': '#020805' } });

            ['areas-3d', 'areas-flat'].forEach((id) => {
                map.on('mousemove', id, (e) => this.hover(e));
                map.on('mouseleave', id, () => this.unhover());
                map.on('click', id, (e) => {
                    if (map.queryRenderedFeatures(e.point, { layers: ['agents', 'reports', 'incidents'] }).length) return;
                    this.pick(e.features[0].id);
                });
            });
            map.on('click', 'agents', (e) => this.agentPopup(e.features[0]));
            map.on('click', 'incidents', (e) => window.dispatchEvent(new CustomEvent('cc-incident-open', { detail: { id: e.features[0].properties.id } })));
            map.on('click', 'reports', (e) => this.reportPopup(e.features[0]));
            ['agents', 'reports', 'incidents'].forEach((id) => {
                map.on('mouseenter', id, () => (map.getCanvas().style.cursor = 'pointer'));
                map.on('mouseleave', id, () => (map.getCanvas().style.cursor = ''));
            });
        },

        /** Colour and raise each area by its members (square root, so one big area does not flatten the rest). */
        paint(list) {
            window.__ccAreas = list;
            if (!this.ready) return;
            areas = Object.fromEntries(list.map((a) => [a.id, a]));
            const max = Math.sqrt(Math.max(1, ...list.map((a) => a.total)));
            geo.areas.features.forEach((f) => {
                const a = areas[f.id];
                map.setFeatureState({ source: 'areas', id: f.id }, { total: a?.total ?? 0, level: a ? Math.sqrt(a.total) / max : 0 });
            });
            map.getSource('pulses').setData({
                type: 'FeatureCollection',
                features: geo.labels.features.filter((f) => (areas[f.id]?.today ?? 0) > 0).map((f) => ({ ...f, properties: { ...f.properties, today: areas[f.id].today } })),
            });
            if (this.picked) this.picked = { ...this.picked, ...(areas[this.picked.id] ?? {}) };
        },

        async loadLive() {
            try {
                const res = await fetch(config.live, { headers: { Accept: 'application/json' } });
                if (!res.ok) return;
                this.live = await res.json();
            } catch {
                return;
            }
            map.getSource('agents').setData({ type: 'FeatureCollection', features: this.live.agents.map((a) => point(a.lng, a.lat, { ...a, colour: CC_AGENT[a.status] ?? CC_AGENT.off })) });
            map.getSource('reports').setData({ type: 'FeatureCollection', features: this.live.activities.map((a) => point(a.lng, a.lat, a)) });
        },

        async loadPins() {
            try {
                const res = await fetch(config.pins, { headers: { Accept: 'application/json' } });
                if (!res.ok) return;
                const data = await res.json();
                this.heatCount = data.features.length;
                map.getSource('heat').setData({ type: 'FeatureCollection', features: data.features });
            } catch {
                /* the heat layer just stays as it was */
            }
        },

        /** Today's sign-up rings breathe; active agents' halos pulse. */
        animate() {
            const still = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            let last = 0;
            const step = (t) => {
                frame = requestAnimationFrame(step);
                if (still || document.hidden || t - last < 50) return;
                last = t;
                const phase = (t % 2200) / 2200;
                map.setPaintProperty('pulses', 'circle-radius', 6 + phase * 26);
                map.setPaintProperty('pulses', 'circle-stroke-opacity', 0.9 * (1 - phase));
                const beat = (Math.sin(t / 380) + 1) / 2;
                map.setPaintProperty('incidents-halo', 'circle-radius', 10 + phase * 22);
                map.setPaintProperty('incidents-halo', 'circle-opacity', 0.45 * (1 - phase));
                map.setPaintProperty('agents-halo', 'circle-radius', ['case', ['==', ['get', 'status'], 'active'], 10 + beat * 10, 10]);
            };
            frame = requestAnimationFrame(step);
        },

        /** Open incidents: where the reporter was, or (without a location) near the middle of their LGA. */
        plotIncidents(list) {
            window.__ccIncidents = list;
            if (!this.ready) return;
            this.incidentCount = list.length;
            const features = list.map((i) => {
                if (i.lat !== null && i.lng !== null) return point(i.lng, i.lat, i);
                const label = geo.labels.features.find((f) => f.id === i.lga_id);
                if (!label) return null;
                const [lng, lat] = label.geometry.coordinates;
                const jitter = ((i.id * 9301 + 49297) % 233280) / 233280 - 0.5;
                return point(lng + jitter * 0.06, lat + (jitter * 7919 % 1) * 0.04, i);
            }).filter(Boolean);
            map.getSource('incidents').setData({ type: 'FeatureCollection', features });
        },

        flyTo({ lat, lng, lgaId }) {
            if (lat !== undefined && lat !== null) {
                map.flyTo({ center: [lng, lat], zoom: 14, pitch: 55, duration: 2200 });
            } else if (lgaId) {
                this.pick(lgaId);
            }
        },

        hover(e) {
            const f = e.features[0];
            if (hoverId !== null && hoverId !== f.id) map.setFeatureState({ source: 'areas', id: hoverId }, { hover: false });
            hoverId = f.id;
            map.setFeatureState({ source: 'areas', id: f.id }, { hover: true });
            map.getCanvas().style.cursor = 'crosshair';
            const a = areas[f.id] ?? {};
            this.tip = { name: f.properties.name, total: a.total ?? 0, today: a.today ?? 0, agents: a.agents ?? 0, x: e.point.x, y: e.point.y };
        },

        unhover() {
            if (hoverId !== null) map.setFeatureState({ source: 'areas', id: hoverId }, { hover: false });
            hoverId = null;
            this.tip = null;
            map.getCanvas().style.cursor = '';
        },

        /** Fly to one area and show what is inside it. */
        async pick(id, fly = true) {
            const feature = geo.areas.features.find((f) => f.id === id);
            if (!feature) return;
            const a = areas[id] ?? {};
            this.picked = { id, name: feature.properties.name, total: a.total ?? 0, today: a.today ?? 0, agents: a.agents ?? 0 };
            this.detail = null;
            if (fly) map.fitBounds(bbox(feature.geometry), { padding: 60, pitch: this.three ? 55 : 0, bearing: map.getBearing(), duration: 1600, maxZoom: national ? 8 : 11 });
            try {
                const res = await fetch(config.area.replace('__ID__', id), { headers: { Accept: 'application/json' } });
                if (res.ok && this.picked?.id === id) this.detail = await res.json();
            } catch {
                /* the figures above are enough */
            }
        },

        closePick() {
            this.picked = null;
            this.detail = null;
            this.home();
        },

        callHere() {
            if (!this.picked) return;
            window.dispatchEvent(new CustomEvent('cc-lga', { detail: { id: this.picked.id, name: this.picked.name } }));
            document.getElementById('call-desk')?.scrollIntoView({ behavior: 'smooth' });
        },

        home(animate = true) {
            if (!bounds) return;
            map.fitBounds(bounds, { padding: 30, pitch: this.three ? 50 : 0, bearing: this.three ? -15 : 0, duration: animate ? 1400 : 0 });
        },

        toggle3d() {
            this.three = !this.three;
            map.setLayoutProperty('areas-3d', 'visibility', this.three ? 'visible' : 'none');
            map.setLayoutProperty('areas-flat', 'visibility', this.three ? 'none' : 'visible');
            map.easeTo({ pitch: this.three ? 50 : 0, bearing: this.three ? map.getBearing() || -15 : 0, duration: 900 });
        },

        toggleLayer(name) {
            this.layers[name] = !this.layers[name];
            const ids = { incidents: ['incidents', 'incidents-halo'], heat: ['heat'], agents: ['agents', 'agents-halo'], reports: ['reports'], pulses: ['pulses', 'pulses-core'] }[name];
            ids.forEach((id) => map.setLayoutProperty(id, 'visibility', this.layers[name] ? 'visible' : 'none'));
        },

        /** Patrol: the camera tours the busiest areas on its own until someone touches the map. */
        togglePatrol() {
            if (this.patrol) {
                this.stopPatrol();
                return;
            }
            this.patrol = true;
            this.picked = null;
            const next = () => {
                const ranked = [...geo.labels.features].sort((x, y) => ((areas[y.id]?.today ?? 0) - (areas[x.id]?.today ?? 0)) || ((areas[y.id]?.total ?? 0) - (areas[x.id]?.total ?? 0))).slice(0, 8);
                const f = ranked[patrolIndex++ % ranked.length];
                if (!f) return;
                map.flyTo({ center: f.geometry.coordinates, zoom: national ? 6.4 : 9.6, pitch: 60, bearing: (map.getBearing() + 55) % 360, duration: 5200, essential: true });
                this.pick(f.id, false);
            };
            next();
            patrolTimer = setInterval(next, 8000);
        },

        stopPatrol() {
            if (!this.patrol) return;
            this.patrol = false;
            clearInterval(patrolTimer);
        },

        updateHud() {
            const c = map.getCenter();
            const alt = Math.round(40075 * Math.cos((c.lat * Math.PI) / 180) / 2 ** map.getZoom());
            this.hud = `${Math.abs(c.lat).toFixed(3)}°${c.lat >= 0 ? 'N' : 'S'}  ${Math.abs(c.lng).toFixed(3)}°${c.lng >= 0 ? 'E' : 'W'}  ·  ALT ${alt.toLocaleString('en-NG')} KM  ·  TILT ${Math.round(map.getPitch())}°`;
        },

        agentPopup(feature) {
            const p = feature.properties;
            const seen = p.seen === null || p.seen === undefined || p.seen === 'null' ? 'not seen today' : p.seen < 1 ? 'seen just now' : `seen ${p.seen} min ago`;
            const html = `<p class="cc-pop-title">${ccEsc(p.name)}</p>
                <p class="cc-pop-sub">${ccEsc(p.status === 'active' ? 'Active' : p.status === 'quiet' ? 'On shift, quiet' : 'Off shift')} · ${ccEsc(seen)}</p>
                ${p.area && p.area !== 'null' ? `<p class="cc-pop-sub">${ccEsc(p.area)}</p>` : ''}
                <p class="cc-pop-sub">${ccEsc(p.households)} households · ${ccEsc(p.activities)} reports today</p>
                ${p.phone && p.phone !== 'null' ? `<a class="cc-pop-btn" href="tel:${ccEsc(p.phone)}" data-dial="${ccEsc(p.id)}">☎ Call</a>` : ''}`;
            const popup = new maplibregl.Popup({ offset: 12, maxWidth: '260px', className: 'cc-pop' }).setLngLat(feature.geometry.coordinates).setHTML(html).addTo(map);
            popup.getElement().querySelector('[data-dial]')?.addEventListener('click', () => {
                window.dispatchEvent(new CustomEvent('cc-dial', { detail: { payload: { user_id: Number(p.id) } } }));
            });
        },

        reportPopup(feature) {
            const p = feature.properties;
            new maplibregl.Popup({ offset: 10, maxWidth: '240px', className: 'cc-pop' }).setLngLat(feature.geometry.coordinates).setHTML(
                `<p class="cc-pop-title">${ccEsc(p.label)}</p><p class="cc-pop-sub">${ccEsc(p.who)} · ${ccEsc(p.time)}</p>${p.ward && p.ward !== 'null' ? `<p class="cc-pop-sub">${ccEsc(p.ward)}</p>` : ''}${Number(p.households) ? `<p class="cc-pop-sub">${ccEsc(p.households)} households</p>` : ''}`,
            ).addTo(map);
        },

        fmt(n) {
            return new Intl.NumberFormat('en-NG').format(n ?? 0);
        },

        destroy() {
            cancelAnimationFrame(frame);
            clearInterval(patrolTimer);
            map?.remove();
        },
    };
});
