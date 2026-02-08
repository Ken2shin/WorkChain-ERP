/**
 * Application Constants & Configuration
 * -------------------------------------
 * Security: Uses environment variables exclusively.
 * Architecture: Optimized for Supabase + Alpine.js
 * Performance: Includes constants for caching and timeouts.
 */

// 1. SUPABASE TABLES (Mapeo de Rutas Lógicas a Tablas Reales)
// Usamos estos valores con el cliente api.get('tabla')
export const DB_TABLES = {
  TENANTS: 'tenants',
  USERS: 'profiles', // Supabase suele usar 'profiles' para datos de usuario
  INVENTORY: 'inventory_items',
  SALES: 'sales_orders',
  PURCHASES: 'purchase_orders',
  HR_EMPLOYEES: 'employees',
  PROJECTS: 'projects',
  LOGISTICS: 'shipments',
  FINANCE: 'transactions',
  DOCUMENTS: 'documents',
  AUDIT_LOGS: 'audit_logs',
} as const;

// 2. APP ROUTES (Rutas del Frontend para redirecciones)
export const APP_ROUTES = {
  LOGIN: '/login',
  DASHBOARD: '/dashboard',
  UNAUTHORIZED: '/403',
  NOT_FOUND: '/404',
  SERVER_ERROR: '/500',
} as const;

// 3. STORAGE KEYS (Sincronizado con login.astro y alpine-store.ts)
// IMPORTANTE: Usamos 'access_token' para compatibilidad con Supabase Auth
export const STORAGE_KEYS = {
  AUTH_TOKEN: 'access_token',
  REFRESH_TOKEN: 'refresh_token',
  USER_DATA: 'user',
  CURRENT_TENANT: 'current_tenant',
  THEME_PREFERENCE: 'theme',
  SIDEBAR_STATE: 'sidebar_open',
} as const;

// 4. TIMEOUTS & PERFORMANCE (Ajustado para alto tráfico)
export const TIMEOUTS = {
  API_REQUEST: 15000,      // 15s (Fail fast es mejor en alto tráfico)
  SESSION_CHECK: 60000,    // Verificar sesión cada 1 min
  DEBOUNCE_SEARCH: 300,    // 300ms para inputs de búsqueda
  TOAST_DURATION: 4000,    // 4s para notificaciones
  LONG_POLLING: 10000,     // 10s para actualizaciones en tiempo real (si no usas sockets)
} as const;

// 5. HTTP STATUS CODES (Referencia semántica)
export const HTTP_STATUS = {
  OK: 200,
  CREATED: 201,
  NO_CONTENT: 204,
  BAD_REQUEST: 400,
  UNAUTHORIZED: 401,
  FORBIDDEN: 403,
  NOT_FOUND: 404,
  CONFLICT: 409, // Útil para duplicados
  SERVER_ERROR: 500,
  SERVICE_UNAVAILABLE: 503,
} as const;

// 6. ERROR MESSAGES (User-Friendly & Production Safe)
export const ERROR_MESSAGES = {
  NETWORK_ERROR: 'No se pudo conectar con el servidor. Verifica tu conexión a internet.',
  AUTH_FAILED: 'Credenciales incorrectas o sesión expirada.',
  SESSION_EXPIRED: 'Tu sesión ha caducado por seguridad. Por favor inicia sesión nuevamente.',
  UNAUTHORIZED: 'No tienes permisos suficientes para realizar esta acción.',
  SERVER_ERROR: 'Ocurrió un error interno. El equipo técnico ha sido notificado.',
  VALIDATION_ERROR: 'Por favor verifica los datos ingresados.',
  RECORD_EXISTS: 'Este registro ya existe en la base de datos.',
} as const;

// 7. SECURITY CONFIGURATION
export const SECURITY_CONFIG = {
  // Supabase maneja la expiración, pero esto sirve para lógica local
  TOKEN_EXPIRATION_HOURS: 1, 
  
  // Encriptación local (si decides implementar cifrado de datos sensibles en cliente)
  ENCRYPTION_ENABLED: import.meta.env.PROD,
  
  // Política de contraseñas (Frontend validation)
  PASSWORD_MIN_LENGTH: 8,
  
  // Forzar HTTPS
  HTTPS_ONLY: import.meta.env.PROD,
} as const;

/**
 * Get a value from environment variables safely
 * @param key - Environment variable name
 * @param defaultValue - Default if not set
 */
export function getEnvVariable(key: string, defaultValue: string = ''): string {
  // Soporte para Astro/Vite
  const value = import.meta.env[key];
  
  if (!value && !defaultValue) {
    if (import.meta.env.DEV) {
      console.warn(`[Config] Variable de entorno faltante: ${key}`);
    }
    return '';
  }
  
  return value || defaultValue;
}

/**
 * VALIDATE ENVIRONMENT (Critical for Production)
 * Verifica que Supabase esté configurado antes de iniciar la app.
 */
export function validateEnvironment(): boolean {
  const requiredVars = [
    'PUBLIC_SUPABASE_URL',
    'PUBLIC_SUPABASE_ANON_KEY'
  ];
  
  const missing: string[] = [];

  for (const varName of requiredVars) {
    if (!import.meta.env[varName]) {
      missing.push(varName);
    }
  }

  if (missing.length > 0) {
    console.error(
      '🚨 FATAL ERROR: Faltan variables de entorno críticas:',
      missing.join(', ')
    );
    return false;
  }

  return true;
}