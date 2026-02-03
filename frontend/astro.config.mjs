import { defineConfig } from 'astro/config';
import node from '@astrojs/node';
import alpinejs from '@astrojs/alpinejs';
import tailwind from '@astrojs/tailwind'; // ¡Añadido!

export default defineConfig({
  // 1. SALIDA: 'server' permite manejar lógica de backend/SaaS en tiempo real
  output: 'server', 

  // 2. ADAPTADOR: Configurado para el puerto que usa Koyeb/Docker
  adapter: node({
    mode: 'standalone', 
  }),

  // 3. INTEGRACIONES: Agregamos Tailwind para que reconozca tu globals.css
  integrations: [
    alpinejs(),
    tailwind({
      applyBaseStyles: false, // Evita conflictos con tu globals.css manual
    })
  ],

  // 4. CONFIGURACIÓN DE VITE
  vite: {
    ssr: {
      noExternal: ['alpinejs'],
    },
    // Eliminamos 'allow: [..]' para evitar fallos de permisos en el contenedor Linux
  },

  // 5. SEGURIDAD
  security: {
    checkOrigin: true,
  },
});