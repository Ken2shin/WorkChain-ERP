import { defineConfig } from 'astro/config';
import node from '@astrojs/node';
import alpinejs from '@astrojs/alpinejs';

export default defineConfig({
  // 1. SALIDA: Static es el estándar moderno de Astro
  output: 'static', 

  // 2. ADAPTADOR: Modo standalone para correrlo fácil con Node
  adapter: node({
    mode: 'standalone', 
  }),

  // 3. INTEGRACIONES: AlpineJS oficial
  integrations: [alpinejs()],

  // 4. CONFIGURACIÓN DE VITE (¡Aquí está la magia para tu CSS!)
  vite: {
    server: {
      fs: {
        // Esto permite que Astro lea archivos un nivel arriba (en la carpeta WorkChain/app)
        allow: ['..'] 
      }
    },
    ssr: {
      noExternal: ['alpinejs'],
    },
  },

  // 5. SEGURIDAD
  security: {
    checkOrigin: true,
  },
});