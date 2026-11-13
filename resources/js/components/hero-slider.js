// Homepage header slider: auto-advances, pauses on hover, focus and touch,
// swipes on phones, works with the keyboard, and stays still for people who
// ask for reduced motion (they use the arrows and dots instead).
export default ({ count, interval = 7000 }) => ({
    index: 0,
    count,
    hovering: false,
    focused: false,
    touching: false,
    stopped: false,
    reduced: false,
    timer: null,
    touchX: null,
    touchY: null,

    init() {
        this.reduced = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false;
        this.stopped = this.reduced;
        if (this.count > 1) {
            this.timer = setInterval(() => {
                if (!this.paused && !document.hidden) this.go(this.index + 1);
            }, interval);
        }
    },

    destroy() { clearInterval(this.timer); },

    get paused() { return this.stopped || this.hovering || this.focused || this.touching; },

    go(i) { this.index = (i + this.count) % this.count; },
    next() { this.go(this.index + 1); },
    prev() { this.go(this.index - 1); },

    /** The visible pause/play button: lets anyone stop the motion for good. */
    toggle() { this.stopped = !this.stopped; },

    key(e) {
        if (e.key === 'ArrowRight') { this.next(); e.preventDefault(); }
        if (e.key === 'ArrowLeft') { this.prev(); e.preventDefault(); }
    },

    touchStart(e) {
        this.touching = true;
        this.touchX = e.touches[0].clientX;
        this.touchY = e.touches[0].clientY;
    },

    touchEnd(e) {
        const dx = e.changedTouches[0].clientX - this.touchX;
        const dy = e.changedTouches[0].clientY - this.touchY;
        // Horizontal swipes only; vertical drags are the page scrolling.
        if (Math.abs(dx) > 45 && Math.abs(dx) > Math.abs(dy) * 1.2) {
            dx < 0 ? this.next() : this.prev();
        }
        // Give the reader a moment before the slider moves on again.
        setTimeout(() => (this.touching = false), 4000);
    },
});
