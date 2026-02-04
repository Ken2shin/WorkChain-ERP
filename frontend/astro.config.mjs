import { defineConfig } from 'astro/config';
import alpinejs from '@astrojs/alpinejs';
import tailwind from '@astrojs/tailwind';

export default defineConfig({
  // 1. Mantenemos 'static' para generar HTML real (Necesario para Laravel)
  output: 'static',

  // 2. CORRECCIÓN: Eliminamos 'outDir' para que Astro genere la carpeta 'dist' por defecto.
  // Esto permite que Docker encuentre los archivos en la etapa de copia.
  
  // 3. ORGANIZACIÓN: Guardamos los assets en una subcarpeta
  build: {
    assets: '_astro',
  },

  // 4. SEGURIDAD
  emptyOutDir: false,

  // 5. INTEGRACIONES (Tus originales)
  integrations: [
    alpinejs(),
    tailwind({
      applyBaseStyles: true,
    })
  ],

  // 6. SEGURIDAD & VITE
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