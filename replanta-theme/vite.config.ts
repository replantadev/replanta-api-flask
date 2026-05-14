import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';
import { resolve } from 'node:path';

export default defineConfig({
  plugins: [react(), tailwindcss()],
  build: {
    outDir: 'assets/dist',
    emptyOutDir: true,
    sourcemap: true,
    rollupOptions: {
      input: {
        admin: resolve(__dirname, 'assets/src/admin/main.tsx'),
        theme: resolve(__dirname, 'assets/src/theme/main.ts'),
      },
      output: {
        entryFileNames: '[name].js',
        chunkFileNames: 'chunks/[name]-[hash].js',
        assetFileNames: (info) => {
          if (info.name?.endsWith('.css')) return '[name].css';
          return 'assets/[name]-[hash][extname]';
        },
      },
      external: [
        '@wordpress/element',
        '@wordpress/components',
        '@wordpress/i18n',
        '@wordpress/api-fetch',
        '@wordpress/icons',
      ],
    },
  },
});
