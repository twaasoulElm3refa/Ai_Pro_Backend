import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import path from 'path';

export default defineConfig({
    server: {
        host: '0.0.0.0', // 🔥 يخلي vite يشتغل خارجياً (ngrok)
        port: 5173,
        strictPort: true,

        hmr: {
            host: 'localhost', // 🔥 يمنع [::1] ويستخدم localhost
            protocol: 'ws'
        }
    },

    plugins: [
        laravel({
            input: ['resources/js/app.js'],
            refresh: true,
        }),

        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
    ],

    resolve: {
        alias: {
            vue: 'vue/dist/vue.esm-bundler.js',
            '@': path.resolve(__dirname, 'resources/js'),
        },
    },

    build: {
        cssCodeSplit: true,
        sourcemap: false,
        chunkSizeWarningLimit: 800,
        rollupOptions: {
            output: {
                manualChunks(id) {
                    if (!id.includes('node_modules')) return;

                    if (id.includes('vue') || id.includes('vue-router')) {
                        return 'vue-vendor';
                    }

                    if (id.includes('axios') || id.includes('toastr')) {
                        return 'network-vendor';
                    }

                    if (id.includes('bootstrap')) {
                        return 'bootstrap-vendor';
                    }

                    return 'vendor';
                },
            },
        },
    },
});
