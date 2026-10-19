import { fileURLToPath, URL } from 'node:url';
import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
  base: './',
  plugins: [vue()],
  build: {
    rollupOptions: {
      input: {
        main: fileURLToPath(new URL('./index.html', import.meta.url)),
        verificar: fileURLToPath(new URL('./verificar.html', import.meta.url)),
      },
    },
  },
  server: {
    host: true,
    port: 5173,
    watch: {
      usePolling: true,
    },
  },
});
