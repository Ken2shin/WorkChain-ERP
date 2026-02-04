/**
 * Application Constants
 * Security: Uses environment variables exclusively - NO hardcoded values
 */

// API Configuration
// These use PUBLIC_API_BASE from .env, NEVER hardcoded URLs
export const API_ENDPOINTS = {
  AUTH_LOGIN: '/auth/login',
  AUTH_VERIFY: '/auth/verify',
  AUTH_LOGOUT: '/auth/logout',
  AUTH_REFRESH: '/auth/refresh',
  DASHBOARD: '/dashboard',
  USERS: '/users',
  INVENTORY: '/inventory',
  SALES: '/sales',
  PURCHASES: '/purchases',
  HR: '/hr',
  PROJECTS: '/projects',
  LOGISTICS: '/logistics',
  FINANCE: '/finance',
  DOCUMENTS: '/documents',
} as const;

// Storage Keys (for localStorage)
export const STORAGE_KEYS = {
  AUTH_TOKEN: 'auth_token',
  REFRESH_TOKEN: 'refresh_token',
  USER_PREFERENCES: 'user_preferences',
  LAST_ROUTE: 'last_route',
} as const;

// Timeout Values (milliseconds)
export const TIMEOUTS = {
  API_REQUEST: 30000, // 30 seconds
  SESSION_TIMEOUT: 3600000, // 1 hour
  DEBOUNCE_SEARCH: 300, // 300ms
} as const;

// HTTP Status Codes for Security Decisions
export const HTTP_STATUS = {
  OK: 200,
  CREATED: 201,
  BAD_REQUEST: 400,
  UNAUTHORIZED: 401,
  FORBIDDEN: 403,
  NOT_FOUND: 404,
  SERVER_ERROR: 500,
  SERVICE_UNAVAILABLE: 503,
} as const;

// Error Messages (User-Friendly, No Technical Details)
export const ERROR_MESSAGES = {
  NETWORK_ERROR: 'Unable to connect to the server. Please check your connection.',
  AUTH_FAILED: 'Authentication failed. Please try again.',
  SESSION_EXPIRED: 'Your session has expired. Please login again.',
  UNAUTHORIZED: 'You do not have permission to perform this action.',
  SERVER_ERROR: 'A server error occurred. Please contact support.',
  VALIDATION_ERROR: 'Please check your input and try again.',
} as const;

// API Rate Limiting (from server config)
export const RATE_LIMITS = {
  MAX_REQUESTS: parseInt(import.meta.env.PUBLIC_RATE_LIMIT_REQUESTS || '60'),
  TIME_WINDOW_MINUTES: parseInt(import.meta.env.PUBLIC_RATE_LIMIT_MINUTES || '1'),
} as const;

// Security Configuration
export const SECURITY_CONFIG = {
  // Token expiration (match server config)
  TOKEN_EXPIRATION_MINUTES: 60,
  
  // Refresh token expiration
  REFRESH_TOKEN_EXPIRATION_DAYS: 7,
  
  // Enable HTTPS only in production
  HTTPS_ONLY: import.meta.env.PROD,
  
  // Secure cookies
  SECURE_COOKIES: import.meta.env.PROD,
  
  // Same-site cookie policy
  SAME_SITE: 'Strict',
} as const;

/**
 * Get a value from environment variables safely
 * Never exposes the value in error messages
 * 
 * @param key - Environment variable name
 * @param defaultValue - Default if not set
 * @returns The environment variable value
 */
export function getEnvVariable(key: string, defaultValue: string = ''): string {
  const value = import.meta.env[key];
  
  if (!value && !defaultValue) {
    console.error(`[Security] Missing environment variable: ${key}`);
    return '';
  }
  
  return value || defaultValue;
}

/**
 * Validate that all required environment variables are set
 * Runs at application startup
 */
export function validateEnvironment(): boolean {
  const requiredVars = ['PUBLIC_API_BASE'];
  const missing: string[] = [];

  for (const varName of requiredVars) {
    if (!import.meta.env[varName]) {
      missing.push(varName);
    }
  }

  if (missing.length > 0) {
    console.error(
      '[Security] Missing required environment variables:',
      missing.join(', ')
    );
    return false;
  }

  return true;
}
