import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';

export default defineConfig({
    plugins: [
        react(),
        laravel({
            input: [
                'resources/css/app.css',
                'resources/css/admin.css',
                'resources/css/staff.css',
                'resources/css/kasir.css',
                'resources/css/transaksi.css',
                'resources/js/app.jsx',
                'resources/js/dashboard-chart.jsx',
                'resources/js/statistik-chart.jsx',
                'resources/js/date-picker.js',
            ],
            refresh: true,
        }),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
