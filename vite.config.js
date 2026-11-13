import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js', 'resources/js/site.js', 'resources/js/charts.js', 'resources/js/live-map.js'],
            refresh: true,
            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                }),
                // Landing page (from the RHYE-2026 Figma file).
                bunny('DM Sans', { weights: [400, 500, 700] }),
                bunny('Lato', { weights: [400] }),
                bunny('Yellowtail', { weights: [400] }),
                // Headlines on the sign-in/join screens and the homepage (RHYE sign-in & join PDF).
                bunny('Bricolage Grotesque', { weights: [700, 800] }),
            ],
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
