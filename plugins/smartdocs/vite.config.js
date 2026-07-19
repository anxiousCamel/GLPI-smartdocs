import { defineConfig } from 'vite';
import { resolve } from 'path';

export default defineConfig({
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
        assetFileNames: (assetInfo) => {
          const info = assetInfo.name.split('.');
          const ext = info[info.length - 1];
          return `assets/[name][extname]`;
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
