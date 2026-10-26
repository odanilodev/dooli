import { fileURLToPath, URL } from 'node:url';
import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';

function rewritePublicCertificate(req, _res, next) {
  const path = (req.url || '').split('?')[0];

  if (/^\/v\/[A-Za-z0-9-]+\/?$/.test(path)) {
    req.url = '/verificar.html';
  }

  next();
}

function publicCertificateRoute() {
  return {
    name: 'public-certificate-route',
    configureServer(server) {
      server.middlewares.use(rewritePublicCertificate);
    },
    configurePreviewServer(server) {
      server.middlewares.use(rewritePublicCertificate);
    },
  };
}

export default defineConfig({
  base: '/',
  plugins: [vue(), publicCertificateRoute()],
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
    // Local custom tenant domains (e.g. ourovelho.test) are mapped via hosts.
    allowedHosts: true,
    port: 5173,
    watch: {
      usePolling: true,
    },
  },
});
