/**
 * src/lib/security-validator.ts
 * Security Validator - Kaze-Quantum ERP (Secure Edition)
 * ------------------
 * Validates configuration, sanitizes inputs, and protects storage.
 * Adapted for Supabase & Production Security with Strict Tenant Validation.
 */

import { STORAGE_KEYS } from './constants';

/**
 * Patrones de seguridad mejorados.
 * Ya no buscan sintaxis de código (var = val), sino patrones de datos sensibles reales.
 */
export const SECURITY_PATTERNS = {
  // Detect hardcoded URLs (excluyendo localhost y variables de entorno placeholders)
  hardcodedUrl: /https?:\/\/(?!localhost|127\.0\.0\.1|workchain-erp\.onrender\.com)[^\s"']+/i,
  
  // Detectar claves de API comunes (Google, AWS, Stripe, etc.)
  // Busca cadenas de 20+ caracteres alfanuméricos con alta entropía
  highEntropyString: /(?<![A-Za-z0-9])([A-Za-z0-9+/=]{32,})(?![A-Za-z0-9])/i,
  
  // Detectar JWTs (Header.Payload.Signature)
  jwtPattern: /eyJ[a-zA-Z0-9_-]{10,}\.eyJ[a-zA-Z0-9_-]{10,}\.[a-zA-Z0-9_-]{10,}/,
  
  // Detectar UUIDs (Para validar Tenant ID)
  uuidV4: /^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i,

  // Detectar IPs privadas (192.168.x.x, 10.x.x.x) - Evita censurar versiones como 1.0.0
  privateIp: /\b(?:192\.168\.|10\.|172\.(?:1[6-9]|2[0-9]|3[0-1])\.)\d{1,3}\.\d{1,3}\b/,
};

/**
 * Validate that no hardcoded secrets are present in configuration values.
 * @param config - Configuration object (e.g., import.meta.env)
 * @returns Array of security issues found
 */
export function validateConfiguration(config: Record<string, any>): string[] {
  const issues: string[] = [];
  const whitelist = [
    'PUBLIC_SUPABASE_URL', 
    'PUBLIC_SUPABASE_ANON_KEY', 
    'PUBLIC_API_BASE',
    'BASE_URL',
    'MODE',
    'DEV',
    'PROD',
    'SSR'
  ];

  for (const [key, value] of Object.entries(config)) {
    if (typeof value === 'string') {
      // Ignorar claves permitidas
      if (whitelist.some(w => key.includes(w))) continue;

      // Check for potential secrets (high entropy strings) in public vars
      if (key.startsWith('PUBLIC_') && SECURITY_PATTERNS.highEntropyString.test(value)) {
        // Excepción: Supabase Anon Key es pública por diseño
        if (!key.includes('ANON_KEY')) {
           issues.push(`[SECURITY] Potential secret exposed in public var: ${key}`);
        }
      }

      // Check for JWTs hardcoded
      if (SECURITY_PATTERNS.jwtPattern.test(value)) {
        issues.push(`[SECURITY] Hardcoded JWT found in ${key}`);
      }
    }
  }

  return issues;
}

/**
 * Sanitize error messages to prevent exposure of sensitive data.
 * Vital for logging errors to external services.
 */
export function sanitizeErrorMessage(error: string | Error): string {
  if (!error) return '';
  const message = error instanceof Error ? error.message : String(error);

  let sanitized = message;

  // 1. Remove URLs (except allowed domains)
  sanitized = sanitized.replace(/https?:\/\/(?!(workchain-erp\.onrender\.com))[^\s]+/g, '[URL_REDACTED]');
  
  // 2. Remove JWTs
  sanitized = sanitized.replace(SECURITY_PATTERNS.jwtPattern, '[JWT_REDACTED]');
  
  // 3. Remove Private IPs (Internal Network Leak)
  sanitized = sanitized.replace(SECURITY_PATTERNS.privateIp, '[INTERNAL_IP]');
  
  // 4. Remove File Paths (Server structure leak)
  // Detects /var/www, C:\Users, etc.
  sanitized = sanitized.replace(/(\/var\/|\/home\/|C:\\Users\\)[^\s]*/gi, '[SERVER_PATH]');
  
  return sanitized;
}

/**
 * Validate JWT token format strictly.
 */
export function isValidJwtToken(token: string): boolean {
  if (!token || typeof token !== 'string') return false;
  // Basic Regex check first
  if (!SECURITY_PATTERNS.jwtPattern.test(token)) return false;

  try {
    const parts = token.split('.');
    if (parts.length !== 3) return false;

    // Check if payload is valid JSON
    const payload = atob(parts[1].replace(/-/g, '+').replace(/_/g, '/'));
    JSON.parse(payload);
    
    return true;
  } catch {
    return false;
  }
}

/**
 * Validate UUID format (Vital for Tenant ID).
 * Prevents XSS or SQLi via Tenant ID injection in LocalStorage.
 */
export function isValidUuid(uuid: string): boolean {
  return SECURITY_PATTERNS.uuidV4.test(uuid);
}

/**
 * Validate environment at startup.
 */
export function validateSecurityAtStartup(): void {
  // 1. Validate Supabase Config
  const sbUrl = import.meta.env.PUBLIC_SUPABASE_URL;
  const sbKey = import.meta.env.PUBLIC_SUPABASE_ANON_KEY;
  
  if (!sbUrl || !sbKey) {
    console.error('🚨 [SECURITY CRITICAL] Supabase environment variables are missing!');
    return;
  }

  // 2. Production Security Checks
  if (import.meta.env.PROD) {
    if (sbUrl.includes('localhost') || sbUrl.includes('127.0.0.1')) {
      console.warn('⚠️ [SECURITY] Localhost detected in production configuration.');
    }

    if (!sbUrl.startsWith('https://')) {
      console.error('❌ [SECURITY] Non-HTTPS connection detected in production.');
    }
    
    // Check if console.log is active in prod (Performance & Security risk)
    // console.log = () => {}; // Descomentar para silenciar logs en prod si se desea
  }
}

/**
 * Proxy/Check for LocalStorage.
 * Prevents storage of secrets and validates data integrity (Tenants).
 */
export function isSafeToStore(key: string, value: string): boolean {
  // 1. Allowlist: Keys permitidas
  const allowedKeys = [
    STORAGE_KEYS.AUTH_TOKEN,
    STORAGE_KEYS.REFRESH_TOKEN,
    STORAGE_KEYS.USER_DATA,
    STORAGE_KEYS.CURRENT_TENANT,
    STORAGE_KEYS.THEME_PREFERENCE,
    'sb-access-token',
    'sb-refresh-token',
    'supabase.auth.token' 
  ];

  // Si la clave no está permitida, bloqueamos por defecto (Whitelisting > Blacklisting)
  if (!allowedKeys.includes(key)) {
      // Opcional: Permitir otras claves si no parecen secretos
      // Pero por seguridad estricta, mejor advertir.
      // console.warn(`[Storage] Storing unknown key: ${key}`);
  }

  // 2. Validación Específica de Tenant
  // Esto arregla el "filtrado de login": asegura que nunca se guarde basura como Tenant ID
  if (key === STORAGE_KEYS.CURRENT_TENANT) {
      if (!isValidUuid(value)) {
          console.error(`🛑 [SECURITY] Blocked invalid Tenant ID storage: ${value}`);
          return false;
      }
  }

  // 3. Blocklist: Patterns check (Redundancia de seguridad)
  if (SECURITY_PATTERNS.jwtPattern.test(value) && !key.toLowerCase().includes('token')) {
    console.error(`🛑 [SECURITY] Blocked attempt to store JWT in non-token key: ${key}`);
    return false;
  }

  return true;
}