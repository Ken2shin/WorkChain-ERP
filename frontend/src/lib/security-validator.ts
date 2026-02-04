/**
 * Security Validator
 * Validates that no sensitive information is exposed
 * Prevents accidental hardcoding of URLs or secrets
 */

/**
 * Pattern validation to prevent hardcoded URLs in code
 * This runs at development time to catch issues
 */
export const SECURITY_PATTERNS = {
  // Detect hardcoded URLs (security issue)
  hardcodedUrl: /https?:\/\/(?!{|YOUR_|CHANGE_|PLACEHOLDER_)/i,
  
  // Detect hardcoded API keys
  hardcodedApiKey: /api[_-]?key\s*[:=]\s*['"]((?!{)[^'"]+)['"]/i,
  
  // Detect hardcoded tokens
  hardcodedToken: /token\s*[:=]\s*['"]((?!{)[^'"]{20,})['"]/i,
  
  // Detect hardcoded secrets
  hardcodedSecret: /secret\s*[:=]\s*['"]((?!{)[^'"]{10,})['"]/i,
  
  // Detect hardcoded passwords
  hardcodedPassword: /password\s*[:=]\s*['"]((?!{)[^'"]{8,})['"]/i,
};

/**
 * Validate that no hardcoded secrets are present in configuration
 * 
 * @param config - Configuration object to validate
 * @returns Array of security issues found
 */
export function validateConfiguration(config: Record<string, any>): string[] {
  const issues: string[] = [];

  for (const [key, value] of Object.entries(config)) {
    if (typeof value === 'string') {
      // Check for hardcoded URLs
      if (SECURITY_PATTERNS.hardcodedUrl.test(value) && 
          !value.includes('{') && 
          !value.includes('localhost')) {
        issues.push(`[SECURITY] Hardcoded URL found in ${key}`);
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
 * 
 * @param error - Original error message
 * @returns Sanitized error message safe to display
 */
export function sanitizeErrorMessage(error: string): string {
  // Remove URLs
  let sanitized = error.replace(/https?:\/\/[^\s]+/g, '[URL]');
  
  // Remove tokens (long hex/base64 strings)
  sanitized = sanitized.replace(/[A-Za-z0-9+/=]{50,}/g, '[TOKEN]');
  
  // Remove IP addresses
  sanitized = sanitized.replace(/\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}/g, '[IP]');
  
  // Remove file paths
  sanitized = sanitized.replace(/\/[^\s]+\.(php|jsx?|tsx?)/g, '[PATH]');
  
  return sanitized;
}

/**
 * Validate JWT token format (basic check)
 * Prevents storing invalid tokens
 * 
 * @param token - Token to validate
 * @returns True if token appears valid
 */
export function isValidJwtToken(token: string): boolean {
  try {
    // JWT format: header.payload.signature
    const parts = token.split('.');
    
    if (parts.length !== 3) {
      return false;
    }

    // Each part should be valid base64
    for (const part of parts) {
      if (!isBase64(part)) {
        return false;
      }
    }

    return true;
  } catch {
    return false;
  }
}

/**
 * Check if string is valid base64
 * 
 * @param str - String to check
 * @returns True if valid base64
 */
function isBase64(str: string): boolean {
  try {
    return btoa(atob(str)) === str;
  } catch {
    return false;
  }
}

/**
 * Validate environment at startup
 * Ensures no hardcoded sensitive data is present
 */
export function validateSecurityAtStartup(): void {
  // Validate that API_BASE_URL uses environment variable
  const apiBase = import.meta.env.PUBLIC_API_BASE;
  
  if (!apiBase) {
    console.error('[SECURITY] PUBLIC_API_BASE is not set in environment variables');
    return;
  }

  if (apiBase.includes('localhost') && import.meta.env.PROD) {
    console.warn('[SECURITY] Localhost detected in production environment');
  }

  if (!apiBase.startsWith('http')) {
    console.error('[SECURITY] Invalid API base URL format');
  }

  // Validate that no secrets are in window object
  if (typeof window !== 'undefined') {
    const suspiciousKeys = ['SECRET', 'TOKEN', 'API_KEY', 'PASSWORD'];
    
    for (const key in window) {
      for (const suspiciousKey of suspiciousKeys) {
        if (key.includes(suspiciousKey) && typeof (window as any)[key] === 'string') {
          console.warn(`[SECURITY] Potential secret exposed in window.${key}`);
        }
      }
    }
  }
}

/**
 * Create a proxy for localStorage that prevents storage of secrets
 * 
 * @param key - Storage key
 * @param value - Value to store
 * @returns True if storage was safe
 */
export function isSafeToStore(key: string, value: string): boolean {
  // Allow auth tokens (they're expected)
  if (key === 'auth_token' || key === 'refresh_token') {
    return true;
  }

  // Check for suspicious patterns
  if (SECURITY_PATTERNS.hardcodedApiKey.test(value)) {
    console.error(`[SECURITY] Attempted to store API key in localStorage.${key}`);
    return false;
  }

  if (SECURITY_PATTERNS.hardcodedSecret.test(value)) {
    console.error(`[SECURITY] Attempted to store secret in localStorage.${key}`);
    return false;
  }

  if (SECURITY_PATTERNS.hardcodedPassword.test(value)) {
    console.error(`[SECURITY] Attempted to store password in localStorage.${key}`);
    return false;
  }

  return true;
}
