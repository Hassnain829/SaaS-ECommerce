import path from 'node:path';
import { fileURLToPath } from 'node:url';

import { defineConfig, loadEnv } from 'vite';
import react from '@vitejs/plugin-react';

const __dirname = path.dirname(fileURLToPath(import.meta.url));

export default defineConfig(({ mode }) => {
  // loadEnv reads .env from this folder (not process.env — that ignores Vite .env files here).
  const env = loadEnv(mode, __dirname, '');
  const proxyTarget = (env.VITE_PROXY_TARGET || 'http://127.0.0.1:8000').replace(/\/$/, '');

  return {
    plugins: [react()],
    server: {
      port: 5177,
      proxy: {
        '/api': {
          target: proxyTarget,
          changeOrigin: true,
        },
      },
    },
  };
});
