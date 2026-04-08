import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';
import { resolve } from 'path';
import { defineConfig } from 'vite';

export default defineConfig({
    resolve: {
        alias: {
            '@': resolve(__dirname, 'resources/ts')
        }
    },
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/ts/app.tsx'],
            ssr: 'resources/ts/ssr.tsx',
            refresh: true
        }),
        react({
            babel: {
                plugins: ['babel-plugin-react-compiler']
            }
        }),
        tailwindcss(),
        wayfinder({
            formVariants: true,
            command: 'php artisan wayfinder:generate --skip-actions --skip-routes --path=resources/ts',
            path: 'resources/ts',
            routes: true,
            actions: true,
            patterns: ['resources/ts/wayfinder/patterns/**/*.ts']
        })
    ]
});
