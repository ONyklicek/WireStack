import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        tailwindcss(),
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            publicDirectory: 'vendor/orchestra/testbench-core/laravel/public',
            hotFile: 'vendor/orchestra/testbench-core/laravel/public/hot',
            buildDirectory: 'build',
            refresh: [
                'resources/views/**',
                'workbench/app/**',
                'workbench/resources/views/**',
                'workbench/routes/**',
                'packages/core/resources/views/**',
                'packages/core/src/**',
                'packages/forms/resources/views/**',
                'packages/forms/src/**',
                'packages/table/resources/views/**',
                'packages/table/src/**',
                'packages/sortable/resources/views/**',
                'packages/sortable/src/**',
            ],
        }),
    ],
    server: {
        host: '127.0.0.1',
        port: 5173,
        strictPort: true,
    },
});
