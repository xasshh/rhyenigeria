// Draws the membership card on a canvas (2x for sharp text) and downloads it.
import { downloadCanvas, loadImage, roundRect, fitText, svgToImage } from './canvas-utils';

export default (card) => ({
    busy: false,
    async download() {
        if (this.busy) return;
        this.busy = true;
        try {
            await document.fonts?.ready;
            const W = 1012, H = 638, S = 2;
            const c = document.createElement('canvas');
            c.width = W; c.height = H;
            const ctx = c.getContext('2d');

            // Background.
            roundRect(ctx, 0, 0, W, H, 40); ctx.save(); ctx.clip();
            const g = ctx.createLinearGradient(0, 0, W, H);
            g.addColorStop(0, '#0c7a39'); g.addColorStop(1, '#109648');
            ctx.fillStyle = g; ctx.fillRect(0, 0, W, H);
            ctx.fillStyle = 'rgba(255,255,255,0.07)';
            ctx.beginPath(); ctx.arc(W - 40, 30, 190, 0, Math.PI * 2); ctx.fill();
            ctx.fillStyle = 'rgba(242,183,5,0.18)';
            ctx.beginPath(); ctx.arc(150, H + 20, 170, 0, Math.PI * 2); ctx.fill();
            ctx.fillStyle = '#f2b705'; ctx.fillRect(0, H - 12, W, 12);
            ctx.restore();

            // Logo on a white plate.
            ctx.fillStyle = '#fff'; roundRect(ctx, 24 * S, 22 * S, 132 * S, 54 * S, 12 * S); ctx.fill();
            try {
                // Fit the logo inside the plate, keeping its shape.
                const logo = await loadImage(card.logo);
                const scale = Math.min((116 * S) / logo.width, (46 * S) / logo.height);
                const w = logo.width * scale, h = logo.height * scale;
                ctx.drawImage(logo, 24 * S + (132 * S - w) / 2, 22 * S + (54 * S - h) / 2, w, h);
            } catch { /* no logo */ }

            ctx.fillStyle = 'rgba(255,255,255,0.8)'; ctx.textAlign = 'right';
            ctx.font = `600 ${10 * S}px DM Sans, sans-serif`;
            ctx.fillText('MEMBERSHIP CARD', W - 24 * S, 38 * S);
            ctx.fillText('RENEWED HOPE YOUTH ENGAGEMENT', W - 24 * S, 52 * S);
            ctx.textAlign = 'left';

            // Text block.
            const x = 24 * S, maxW = 290 * S;
            ctx.fillStyle = 'rgba(255,255,255,0.7)'; ctx.font = `600 ${10 * S}px DM Sans, sans-serif`;
            ctx.fillText('MEMBER', x, 104 * S);
            ctx.fillStyle = '#fff'; fitText(ctx, card.name, maxW, 22 * S);
            ctx.fillText(card.name, x, 130 * S);
            ctx.fillStyle = 'rgba(255,255,255,0.7)'; ctx.font = `600 ${10 * S}px DM Sans, sans-serif`;
            ctx.fillText('MEMBER NO.', x, 156 * S);
            ctx.fillStyle = '#f2b705'; ctx.font = `700 ${17 * S}px ui-monospace, Menlo, monospace`;
            ctx.fillText(card.number, x, 178 * S);
            ctx.fillStyle = 'rgba(255,255,255,0.9)'; ctx.font = `500 ${12 * S}px DM Sans, sans-serif`;
            card.place.forEach((line, i) => { fitText(ctx, line, maxW, 12 * S, 500); ctx.fillText(line, x, (204 + i * 17) * S); });

            // QR on white.
            const q = 112 * S, qx = W - 24 * S - q, qy = 92 * S;
            ctx.fillStyle = '#fff'; roundRect(ctx, qx - 8 * S, qy - 8 * S, q + 16 * S, q + 16 * S, 14 * S); ctx.fill();
            const svg = this.$root.querySelector('[data-qr] svg') || document.querySelector('[data-qr] svg');
            if (svg) ctx.drawImage(await svgToImage(svg), qx, qy, q, q);

            // Footer.
            ctx.strokeStyle = 'rgba(255,255,255,0.25)'; ctx.lineWidth = S;
            ctx.beginPath(); ctx.moveTo(x, 262 * S); ctx.lineTo(W - 24 * S, 262 * S); ctx.stroke();
            ctx.fillStyle = 'rgba(255,255,255,0.8)'; ctx.font = `500 ${11 * S}px DM Sans, sans-serif`;
            ctx.fillText(`Joined ${card.joined}`, x, 284 * S);
            ctx.textAlign = 'right'; ctx.fillStyle = '#fff'; ctx.font = `700 ${12 * S}px DM Sans, sans-serif`;
            ctx.fillText(card.role, W - 24 * S, 284 * S);
            if (card.slogan) {
                ctx.textAlign = 'center'; ctx.fillStyle = '#f2b705'; ctx.font = `italic 600 ${11 * S}px DM Sans, sans-serif`;
                ctx.fillText(card.slogan, W / 2, 284 * S);
            }

            await downloadCanvas(c, `${card.number}.png`);
        } finally {
            this.busy = false;
        }
    },
});
