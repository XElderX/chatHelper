import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import tailwind from '@tailwindcss/vite';
export default defineConfig({ plugins: [laravel({ input: ['resources/js/app.js'], refresh: true }), vue(), tailwind()], server: {host:'127.0.0.1'}, test: { environment: 'jsdom', include: ['tests/frontend/**/*.test.js'] } });
