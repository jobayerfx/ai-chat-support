import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';
import { VitePWA } from 'vite-plugin-pwa';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.jsx'],
            refresh: true,
        }),
        react(),
        VitePWA({
            strategies: 'injectManifest',
            srcDir: 'resources',
            filename: 'sw.js',
            registerType: 'autoUpdate',
            workbox: {
                globPatterns: ['**/*.{js,css,html,ico,png,svg}'],
                runtimeCaching: [
                    {
                        urlPattern: /^https:\/\/.*\/api\/.*/,
                        handler: 'NetworkOnly',
                        options: {
                            cacheName: 'api-cache',
                        },
                    },
                ],
            },
            includeAssets: ['favicon.ico', 'apple-touch-icon.png', 'masked-icon.svg'],
            manifest: {
                name: 'AI Chat Support',
                short_name: 'AI Chat',
                description: 'AI-powered customer support chat system',
                theme_color: '#4F46E5',
                background_color: '#ffffff',
                display: 'standalone',
                icons: [
                    {
                        src: 'pwa-192x192.png',
                        sizes: '192x192',
                        type: 'image/png',
                    },
                    {
                        src: 'pwa-512x512.png',
                        sizes: '512x512',
                        type: 'image/png',
                    },
                ],
            },
            devOptions: {
                enabled: false,
            },
        }),
    ],
    build: {
        rollupOptions: {
            input: {
                app: 'resources/js/app.jsx',
                sw: 'resources/sw.js',
                spaSw: 'resources/js/sw.js',
            },
        },
        // PWA optimizations
        manifest: true,
        sourcemap: false,
        minify: 'terser',
        terserOptions: {
            compress: {
                drop_console: process.env.NODE_ENV === 'production',
                drop_debugger: true,
            },
        },
    },
    server: {
        host: '127.0.0.1',
        port: 5173,
        hmr: {
            host: '127.0.0.1',
        },
    },
    // PWA specific optimizations
    define: {
        __PWA_ENV__: JSON.stringify(process.env.NODE_ENV),
    },
});
