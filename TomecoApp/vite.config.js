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
        origin: 'http://10.240.58.83:5173',
        hmr: {
            host: '10.240.58.83',
            port: 5173,
        },
        cors: {
            origin: 'http://10.240.58.83:8000',
        },
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
