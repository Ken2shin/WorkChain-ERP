/// <reference types="astro/client" />

// 1. SEGURIDAD DE VARIABLES DE ENTORNO
// Aquí definimos qué variables PUBLIC_ son obligatorias.
// Si intentas usar una que no está aquí, el código te avisará antes de romperse.
interface ImportMetaEnv {
  readonly PUBLIC_API_BASE: string;
  readonly PUBLIC_SUPABASE_URL: string;
  readonly PUBLIC_SUPABASE_ANON_KEY: string;
  // Si usas Stripe en el frontend, descomenta la siguiente línea:
  // readonly PUBLIC_STRIPE_KEY: string;
}

interface ImportMeta {
  readonly env: ImportMetaEnv;
}

// 2. SEGURIDAD PARA LIBRERÍAS EXTERNAS (AlpineJS)
// Esto soluciona el error "Could not find a declaration file for module 'alpinejs'"
declare module 'alpinejs' {
  export interface Alpine {
    data(name: string, callback: (...args: any[]) => any): void;
    start(): void;
    store(name: string, value: any): void;
  }
  const alpine: Alpine;
  export default alpine;
}

// 3. SEGURIDAD GLOBAL (Window)
// Esto permite usar window.API_BASE y window.Alpine sin que TypeScript grite error.
interface Window {
  API_BASE: string;
  Alpine: any;
}