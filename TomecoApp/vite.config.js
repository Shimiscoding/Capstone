import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js', 'resources/js/location-monitoring.js'],
            refresh: true,
        }),
        tailwindcss(),
    ],
    server: {
        host: '0.0.0.0',
        origin: 'http://172.16.7.146:5173',
        hmr: {
            host: '172.16.7.146',
            port: 5173,
        },
        cors: {
            origin: 'http://172.16.7.146:8000',
        },
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
