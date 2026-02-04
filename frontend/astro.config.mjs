import { defineConfig } from 'astro/config';
import alpinejs from '@astrojs/alpinejs';
import tailwind from '@astrojs/tailwind';

export default defineConfig({
  // 1. CAMBIO CRÍTICO: Usamos 'static' para generar archivos HTML/CSS/JS reales.
  // Esto permite que Laravel sirva el frontend sin necesitar un servidor Node.js aparte.
  output: 'static',

  // 2. LA MAGIA: Enviamos el resultado directamente a la carpeta pública de Laravel.
  // Cuando ejecutes 'npm run build', los archivos aparecerán en tu backend.
  outDir: '../laravel/public',
  
  // 3. ORGANIZACIÓN: Guardamos los assets en una subcarpeta para no ensuciar el public de Laravel
  build: {
    assets: '_astro',
  },

  // 4. SEGURIDAD: No borramos la carpeta public entera (para no borrar index.php de Laravel)
  emptyOutDir: false,

  // 5. INTEGRACIONES
  integrations: [
    alpinejs(),
    tailwind({
      applyBaseStyles: true, // true es mejor para asegurar estilos base consistentes
    })
  ],

  // 6. SEGURIDAD & VITE
  vite: {
    build: {
      // Optimizaciones para producción
      minify: 'esbuild',
      cssMinify: true,
    },
    ssr: {
      noExternal: ['alpinejs'],
    },
  },
});