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
