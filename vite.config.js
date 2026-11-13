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
    build: {
        rollupOptions: {
            output: {
                // The live map's web worker ships as .mjs; publish it as .js so Nginx serves it as JavaScript
                // (its default MIME table has no .mjs, and browsers refuse module workers sent as octet-stream).
                assetFileNames: (asset) => (asset.names?.[0] ?? asset.name ?? '').endsWith('.mjs')
                    ? 'assets/[name]-[hash].js'
                    : 'assets/[name]-[hash][extname]',
            },
        },
    },
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
