import { defineConfig } from 'astro/config';
import alpinejs from '@astrojs/alpinejs';
import tailwind from '@astrojs/tailwind';
import node from '@astrojs/node';

export default defineConfig({
  // SSR necesario para formularios POST y login
  output: 'server',

  // Adaptador Node para Render
  adapter: node({
    mode: 'standalone',
  }),

  // Build limpio y consistente en producción
  build: {
    assets: '_astro',
  },

  // ⚠️ CLAVE: SIEMPRE limpiar output en producción
  emptyOutDir: true,

  integrations: [
    alpinejs(),
    tailwind({
      applyBaseStyles: true,
    }),
  ],

  vite: {
    build: {
      minify: 'esbuild',
      cssMinify: true,
    },
    ssr: {
      noExternal: ['alpinejs'],
    },
  },
});
