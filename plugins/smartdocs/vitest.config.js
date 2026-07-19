import { defineConfig } from 'vitest/config';
import { resolve } from 'path';

export default defineConfig({
  test: {
    globals: true,
    environment: 'jsdom',
    include: ['tests/Unit/**/*.test.js'],
    coverage: {
      provider: 'v8',
      reporter: ['text', 'html', 'clover'],
      reportsDirectory: 'build/coverage-js',
    },
  },
  resolve: {
    alias: {
      '@': resolve(__dirname, 'js-src'),
    },
  },
});
