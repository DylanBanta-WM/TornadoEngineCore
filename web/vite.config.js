import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import fs from 'fs';

export default defineConfig({
    plugins: [
        laravel({
            // local/local.css: the district's own styles (Administration → Overrides).
            input: ['resources/css/app.css', 'resources/js/app.js', ...(fs.existsSync('local/local.css') ? ['local/local.css'] : [])],
            refresh: true,
        }),
    ],
});
