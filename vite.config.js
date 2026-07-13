import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    publicDir: false,
    plugins: [
        laravel({
            input: [
                'resources/css/generated/core.css',
                'resources/css/generated/public.css',
                'resources/css/generated/discipleship.css',
                'resources/css/generated/developer.css',
                'resources/css/generated/worship.css',
                'resources/js/app.js',
            ],
            refresh: true,
        }),
    ],
    build: {
        sourcemap: false,
    },
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
