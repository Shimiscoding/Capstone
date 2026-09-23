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
        origin: 'http://10.14.2.39:5173',
        hmr: {
            host: '10.14.2.39',
            port: 5173,
        },
        cors: {
            origin: 'http://10.14.2.39:8000',
        },
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
