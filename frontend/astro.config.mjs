import { defineConfig } from 'astro/config';
import alpinejs from '@astrojs/alpinejs';
import tailwind from '@astrojs/tailwind';
import node from '@astrojs/node'; // Requerido para procesar el Login en el servidor

export default defineConfig({
  // 1. CAMBIO CRÍTICO: Usamos 'server' para que las API Routes (POST) funcionen.
  // El modo 'static' no permite recibir datos de formularios.
  output: 'server',

  // 2. ADAPTADOR: Necesario para que Render pueda ejecutar el código de Node.js
  adapter: node({
    mode: 'standalone',
  }),

  // 3. ORGANIZACIÓN: Mantenemos tu estructura de assets
  build: {
    assets: '_astro',
  },

  // 4. SEGURIDAD: Mantenemos tu preferencia
  emptyOutDir: false,

  // 5. INTEGRACIONES (Tus originales intactas)
  integrations: [
    alpinejs(),
    tailwind({
      applyBaseStyles: true,
    })
  ],

  // 6. SEGURIDAD & VITE (Tus configuraciones originales)
  vite: {
    build: {
      minify: 'esbuild',
      cssMinify: true,
    },
    ssr: {
      noExternal: ['alpinejs'],
    },
    // Optimizamos la carga de variables de entorno para Render
    define: {
      'process.env': process.env
    }
  },
});