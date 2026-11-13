// Website bundle: Alpine plus the small components the member area needs.
// The Echo/Reverb client in app.js is not loaded here, to keep pages light.
import Alpine from 'alpinejs';
import joinWizard from './components/join-wizard';
import memberCard from './components/member-card';
import dpMaker from './components/dp-maker';
import heroSlider from './components/hero-slider';
import areaMap from './components/area-map';
import fieldTracker from './components/field-tracker';

Alpine.data('joinWizard', joinWizard);
Alpine.data('memberCard', memberCard);
Alpine.data('dpMaker', dpMaker);
Alpine.data('heroSlider', heroSlider);
Alpine.data('areaMap', areaMap);
Alpine.data('fieldTracker', fieldTracker);

// Counts down to `target`. `initial` is the server-rendered value, so the numbers
// are right before this runs and nothing jumps when it starts.
Alpine.data('countdown', (target, initial = {}) => ({
    weeks: 0, days: 0, totalDays: 0, hours: 0, minutes: 0, seconds: 0, done: false,
    ...initial,
    init() {
        const end = new Date(target).getTime();
        const tick = () => {
            let s = Math.max(0, Math.floor((end - Date.now()) / 1000));
            this.done = s === 0;
            this.totalDays = Math.floor(s / 86400);
            this.weeks = Math.floor(this.totalDays / 7);
            this.days = this.totalDays % 7;
            s %= 86400;
            this.hours = Math.floor(s / 3600); s %= 3600;
            this.minutes = Math.floor(s / 60);
            this.seconds = s % 60;
            if (this.done) clearInterval(this.timer);
        };
        tick();
        this.timer = setInterval(tick, 1000);
    },
    destroy() { clearInterval(this.timer); },
    pad: (n) => String(n).padStart(2, '0'),
}));

Alpine.data('copyText', (text) => ({
    copied: false,
    async copy() {
        try {
            await navigator.clipboard.writeText(text);
        } catch {
            const t = document.createElement('textarea');
            t.value = text; document.body.appendChild(t); t.select();
            document.execCommand('copy'); t.remove();
        }
        this.copied = true;
        setTimeout(() => (this.copied = false), 2000);
    },
}));

window.Alpine = Alpine;
Alpine.start();
