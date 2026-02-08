/**
 * Security Validator
 * ------------------
 * Validates that no sensitive information is exposed.
 * Prevents accidental hardcoding of URLs or secrets.
 * Adapted for Supabase & Production Security.
 */

import { STORAGE_KEYS } from './constants';

/**
 * Pattern validation to prevent hardcoded URLs in code
 * This runs at development time to catch issues
 */
export const SECURITY_PATTERNS = {
  // Detect hardcoded URLs (security issue)
  hardcodedUrl: /https?:\/\/(?!{|YOUR_|CHANGE_|PLACEHOLDER_|localhost|127\.0\.0\.1)/i,
  
  // Detect hardcoded API keys (generic patterns)
  hardcodedApiKey: /api[_-]?key\s*[:=]\s*['"]((?!{)[^'"]+)['"]/i,
  
  // Detect hardcoded tokens (Bearer, JWT, etc)
  hardcodedToken: /token\s*[:=]\s*['"]((?!{)[^'"]{20,})['"]/i,
  
  // Detect hardcoded secrets
  hardcodedSecret: /secret\s*[:=]\s*['"]((?!{)[^'"]{10,})['"]/i,
  
  // Detect hardcoded passwords
  hardcodedPassword: /password\s*[:=]\s*['"]((?!{)[^'"]{8,})['"]/i,
};

/**
 * Validate that no hardcoded secrets are present in configuration
 * * @param config - Configuration object to validate
 * @returns Array of security issues found
 */
export function validateConfiguration(config: Record<string, any>): string[] {
  const issues: string[] = [];

  for (const [key, value] of Object.entries(config)) {
    if (typeof value === 'string') {
      // Check for hardcoded URLs (Allowing Supabase URL via env var is fine, checking for literals)
      if (SECURITY_PATTERNS.hardcodedUrl.test(value) && 
          !value.includes('{') && 
          !value.includes(import.meta.env.PUBLIC_SUPABASE_URL || 'skip')) {
        issues.push(`[SECURITY] Potential hardcoded URL found in ${key}`);
      }

      // Check for hardcoded API keys
      if (SECURITY_PATTERNS.hardcodedApiKey.test(value)) {
        issues.push(`[SECURITY] Hardcoded API key found in ${key}`);
      }

      // Check for hardcoded tokens
      if (SECURITY_PATTERNS.hardcodedToken.test(value)) {
        issues.push(`[SECURITY] Hardcoded token found in ${key}`);
      }
    }
  }

  return issues;
}

/**
 * Sanitize error messages to prevent exposure of sensitive data
 * Vital for logging errors to external services (Sentry, LogRocket)
 * * @param error - Original error message
 * @returns Sanitized error message safe to display
 */
export function sanitizeErrorMessage(error: string): string {
  if (!error) return '';

  // Remove URLs
  let sanitized = error.replace(/https?:\/\/[^\s]+/g, '[URL]');
  
  // Remove potential long tokens (hex/base64 strings > 50 chars)
  sanitized = sanitized.replace(/[A-Za-z0-9+/=]{50,}/g, '[TOKEN]');
  
  // Remove IP addresses
  sanitized = sanitized.replace(/\b\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}\b/g, '[IP]');
  
  // Remove file paths (Unix/Windows)
  sanitized = sanitized.replace(/(\/|\\)[^\s]+\.(php|jsx?|tsx?|env|json)/g, '[PATH]');
  
  return sanitized;
}

/**
 * Validate JWT token format (basic check)
 * Prevents storing invalid tokens
 * * @param token - Token to validate
 * @returns True if token appears valid
 */
export function isValidJwtToken(token: string): boolean {
  if (!token || typeof token !== 'string') return false;

  try {
    // JWT format: header.payload.signature
    const parts = token.split('.');
    
    if (parts.length !== 3) {
      return false;
    }

    // Each part should be valid base64 or base64url
    for (const part of parts) {
      if (!isBase64Url(part)) {
        return false;
      }
    }

    return true;
  } catch {
    return false;
  }
}

/**
 * Check if string is valid base64URL (Standard for JWTs)
 * Adjusted to support Supabase tokens which might not have padding
 * * @param str - String to check
 * @returns True if valid base64
 */
function isBase64Url(str: string): boolean {
  // Regex for Base64URL (Alphanumeric + '-' + '_')
  const base64UrlPattern = /^[A-Za-z0-9\-_]+$/;
  return base64UrlPattern.test(str);
}

/**
 * Validate environment at startup
 * Ensures no hardcoded sensitive data is present and Supabase is configured
 */
export function validateSecurityAtStartup(): void {
  // 1. Validate Supabase Configuration instead of generic API_BASE
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
  }

  // 3. Window Object Inspection (Prevent global leaks)
  if (typeof window !== 'undefined') {
    // We explicitly cast window to any to scan properties
    const win = window as any;
    const suspiciousKeys = ['SECRET', 'TOKEN', 'API_KEY', 'PASSWORD', 'PRIVATE_KEY'];
    
    // Scan only own properties to avoid prototype chain noise
    const globalProps = Object.getOwnPropertyNames(win);
    
    for (const key of globalProps) {
      const upperKey = key.toUpperCase();
      for (const suspicious of suspiciousKeys) {
        if (upperKey.includes(suspicious) && typeof win[key] === 'string') {
            // Ignore standard known libraries tokens if needed, but warn generally
            if (key !== 'Supabase' && key !== 'Alpine') {
                console.warn(`⚠️ [SECURITY] Potential secret exposed globally in window.${key}`);
            }
        }
      }
    }
  }
}

/**
 * Create a proxy/check for localStorage that prevents storage of secrets
 * * @param key - Storage key
 * @param value - Value to store
 * @returns True if storage was safe
 */
export function isSafeToStore(key: string, value: string): boolean {
  // 1. Allowlist: Permite explícitamente las claves de Supabase y App
  const allowedKeys = [
    STORAGE_KEYS.AUTH_TOKEN,   // 'access_token'
    STORAGE_KEYS.REFRESH_TOKEN,// 'refresh_token'
    STORAGE_KEYS.USER_DATA,    // 'user'
    STORAGE_KEYS.CURRENT_TENANT,
    STORAGE_KEYS.THEME_PREFERENCE,
    'sb-access-token',         // Supabase default sometimes
    'supabase.auth.token'      // Supabase default sometimes
  ];

  if (allowedKeys.includes(key)) {
    return true;
  }

  // 2. Blocklist: Patterns check
  if (SECURITY_PATTERNS.hardcodedApiKey.test(value)) {
    console.error(`🛑 [SECURITY] Blocked attempt to store API key in localStorage.${key}`);
    return false;
  }

  if (SECURITY_PATTERNS.hardcodedSecret.test(value)) {
    console.error(`🛑 [SECURITY] Blocked attempt to store secret in localStorage.${key}`);
    return false;
  }

  if (SECURITY_PATTERNS.hardcodedPassword.test(value)) {
    console.error(`🛑 [SECURITY] Blocked attempt to store password in localStorage.${key}`);
    return false;
  }

  return true;
}