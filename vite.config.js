import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/js/admin/tournaments/staff-management.js',
                'resources/js/diff-viewer.js',
            ],
            refresh: true,
        }),
    ],
});
