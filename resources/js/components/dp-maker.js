// "Make your campaign DP": everything happens on this canvas in the browser.
// The chosen photo is never uploaded.
import { downloadCanvas, fitText, loadImage, roundRect, wrap } from './canvas-utils';

const SIZE = 1080;

export default (config) => ({
    messages: config.messages,
    choice: 0,
    name: config.name || '',
    photo: null,
    busy: false,
    canShare: false,
    logo: null,

    async init() {
        try { this.logo = await loadImage(config.logo); } catch { /* draw without it */ }
        this.canShare = !!(navigator.canShare && navigator.canShare({ files: [new File([''], 'x.png', { type: 'image/png' })] }));
        await document.fonts?.ready;
        this.draw();
        this.$watch('choice', () => this.draw());
        this.$watch('name', () => this.draw());
    },

    async pick(event) {
        const file = event.target.files?.[0];
        if (!file) return;
        const url = URL.createObjectURL(file);
        try { this.photo = await loadImage(url); } catch { alert('That file could not be opened. Try a JPG or PNG photo.'); }
        this.draw();
    },

    draw() {
        const c = this.$refs.canvas, ctx = c.getContext('2d');
        c.width = SIZE; c.height = SIZE;

        // Photo (cover) or placeholder.
        const bg = ctx.createLinearGradient(0, 0, SIZE, SIZE);
        bg.addColorStop(0, '#0c7a39'); bg.addColorStop(1, '#14a352');
        ctx.fillStyle = bg; ctx.fillRect(0, 0, SIZE, SIZE);
        if (this.photo) {
            const s = Math.max(SIZE / this.photo.width, SIZE / this.photo.height);
            const w = this.photo.width * s, h = this.photo.height * s;
            ctx.drawImage(this.photo, (SIZE - w) / 2, (SIZE - h) / 2 - h * 0.04, w, h);
        } else {
            ctx.fillStyle = 'rgba(255,255,255,0.18)';
            ctx.beginPath(); ctx.arc(SIZE / 2, 400, 150, 0, Math.PI * 2); ctx.fill();
            ctx.beginPath(); ctx.ellipse(SIZE / 2, 820, 290, 230, 0, Math.PI, 0); ctx.fill();
        }

        // Green fade at the bottom for the message.
        const fade = ctx.createLinearGradient(0, SIZE * 0.42, 0, SIZE);
        fade.addColorStop(0, 'rgba(12,122,57,0)'); fade.addColorStop(0.55, 'rgba(12,122,57,0.88)'); fade.addColorStop(1, 'rgba(8,80,38,0.98)');
        ctx.fillStyle = fade; ctx.fillRect(0, SIZE * 0.42, SIZE, SIZE * 0.58);

        // Frame.
        ctx.strokeStyle = '#f2b705'; ctx.lineWidth = 14;
        roundRect(ctx, 26, 26, SIZE - 52, SIZE - 52, 36); ctx.stroke();

        // Logo plate.
        ctx.fillStyle = '#fff'; roundRect(ctx, 56, 56, 260, 120, 22); ctx.fill();
        if (this.logo) {
            const w = 220, h = w * this.logo.height / this.logo.width;
            ctx.drawImage(this.logo, 76, 56 + (120 - h) / 2, w, h);
        }

        // Message.
        const m = this.messages[this.choice];
        const x = 80, maxW = SIZE - 160;
        let y = SIZE - 120;
        if (this.name.trim()) {
            ctx.fillStyle = 'rgba(255,255,255,0.92)'; fitText(ctx, this.name.trim(), maxW, 34, 500);
            ctx.fillText(this.name.trim(), x, y); y -= 64;
        }
        ctx.font = '800 76px DM Sans, sans-serif';
        const lines = wrap(ctx, m.headline, maxW - (config.year ? 0 : 0));
        ctx.fillStyle = '#fff';
        for (let i = lines.length - 1; i >= 0; i--) { ctx.fillText(lines[i], x, y); y -= 84; }
        ctx.fillStyle = '#f2b705'; ctx.font = '700 32px DM Sans, sans-serif';
        if ('letterSpacing' in ctx) ctx.letterSpacing = '6px';
        ctx.fillText(m.kicker.toUpperCase(), x, y + 18);
        if ('letterSpacing' in ctx) ctx.letterSpacing = '0px';

        if (config.year) {
            ctx.font = '800 44px DM Sans, sans-serif';
            const w = ctx.measureText(config.year).width + 56;
            ctx.fillStyle = '#f2b705'; roundRect(ctx, SIZE - 70 - w, 74, w, 76, 38); ctx.fill();
            ctx.fillStyle = '#0b2a17'; ctx.fillText(config.year, SIZE - 70 - w + 28, 128);
        }
    },

    async download() {
        this.busy = true;
        try { await downloadCanvas(this.$refs.canvas, 'rhye-campaign-dp.png'); } finally { this.busy = false; }
    },

    async share() {
        this.busy = true;
        try {
            const blob = await new Promise((r) => this.$refs.canvas.toBlob(r, 'image/png'));
            const file = new File([blob], 'rhye-campaign-dp.png', { type: 'image/png' });
            if (navigator.canShare?.({ files: [file] })) {
                await navigator.share({ files: [file], text: config.shareText });
            } else {
                await downloadCanvas(this.$refs.canvas, 'rhye-campaign-dp.png');
            }
        } catch { /* share sheet closed */ } finally { this.busy = false; }
    },
});
