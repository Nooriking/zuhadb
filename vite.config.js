import react from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';
import path from 'node:path';
import { defineConfig } from 'vite';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.tsx'],
            ssr: 'resources/js/ssr.jsx',
            refresh: ['resources/views/**', 'routes/**'],
        }),
        react(),
        tailwindcss(),
    ],
    resolve: {
        alias: {
            '@': path.resolve('resources/js'),
            'ziggy-js': path.resolve('vendor/tightenco/ziggy'),
            // recharts imports both of these; package is CJS-only
            'use-sync-external-store/with-selector.js': path.resolve(
                'resources/js/shims/use-sync-external-store-with-selector.js',
            ),
            'use-sync-external-store/with-selector': path.resolve(
                'resources/js/shims/use-sync-external-store-with-selector.js',
            ),
            'use-sync-external-store/shim/with-selector.js': path.resolve(
                'resources/js/shims/use-sync-external-store-with-selector.js',
            ),
            'use-sync-external-store/shim/with-selector': path.resolve(
                'resources/js/shims/use-sync-external-store-with-selector.js',
            ),
        },
    },
    esbuild: {
        jsx: 'automatic',
    },
    server: {
        host: '127.0.0.1',
        port: 5173,
        strictPort: true,
        hmr: {
            host: '127.0.0.1',
        },
        watch: {
            ignored: ['**/storage/**', '**/vendor/**', '**/node_modules/**', '**/public/build/**'],
        },
    },
    optimizeDeps: {
        include: [
            'react',
            'react-dom',
            '@inertiajs/react',
            'axios',
            'recharts',
            'use-sync-external-store/with-selector',
        ],
        exclude: ['sweetalert2'],
    },
    build: {
        target: 'es2020',
        cssCodeSplit: true,
        sourcemap: false,
        reportCompressedSize: false,
        chunkSizeWarningLimit: 700,
        rollupOptions: {
            output: {
                manualChunks(id) {
                    if (!id.includes('node_modules') && !id.includes('vendor/tightenco/ziggy')) {
                        return;
                    }
                    if (id.includes('recharts') || id.includes('d3-') || id.includes('victory')) {
                        return 'charts';
                    }
                    if (id.includes('sweetalert2')) {
                        return 'swal';
                    }
                    if (id.includes('@inertiajs') || id.includes('ziggy') || id.includes('tightenco')) {
                        return 'inertia';
                    }
                    if (id.includes('react-dom') || id.includes('/react/') || id.includes('\\react\\')) {
                        return 'react-vendor';
                    }
                    if (id.includes('lucide-react')) {
                        return 'icons';
                    }
                    if (id.includes('@radix-ui') || id.includes('@headlessui')) {
                        return 'ui';
                    }
                },
            },
        },
    },
});
