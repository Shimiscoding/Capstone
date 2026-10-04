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
        origin: 'http://192.168.8.195:5173',
        hmr: {
            host: '192.168.8.195',
            port: 5173,
        },
        cors: {
            origin: 'http://192.168.8.195:8000',
        },
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
