import { defineConfig } from 'vite';
import { resolve } from 'path';

export default defineConfig({
  base: '/plugins/smartdocs/js/',
  build: {
    outDir: resolve(__dirname, 'js'),
    emptyOutDir: false,
    rollupOptions: {
      input: {
        editor: resolve(__dirname, 'js-src/editor/index.js'),
        wizard: resolve(__dirname, 'js-src/wizard/index.js'),
        scanner: resolve(__dirname, 'js-src/scanner/index.js'),
      },
      output: {
        format: 'es',
        entryFileNames: '[name].bundle.js',
        chunkFileNames: '[name]-[hash].js',
        // GLPI só serve arquivos estáticos com extensão .js/.css (ver
        // Glpi\Http\ProxyRouter::isPathAllowed) — .mjs recebe 403.
        // O worker do pdfjs-dist usa `new Worker(url, {type: 'module'})`,
        // então a extensão do arquivo não importa para o navegador.
        assetFileNames: (assetInfo) => {
          if (assetInfo.name === 'pdf.worker.min.mjs') {
            return 'assets/pdf.worker.min.js';
          }
          return 'assets/[name][extname]';
        },
      },
    },
  },
  resolve: {
    alias: {
      '@': resolve(__dirname, 'js-src'),
    },
  },
});
