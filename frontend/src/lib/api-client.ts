/**
 * Secure API Client Configuration
 * Uses environment variables exclusively - NO hardcoded URLs
 * Implements security best practices for API communication
 */

// Validate that required env vars exist at build time
const getApiBaseUrl = (): string => {
  const apiBase = import.meta.env.PUBLIC_API_BASE;
  
  if (!apiBase) {
    throw new Error(
      'Missing required environment variable: PUBLIC_API_BASE. ' +
      'Please set it in your .env file.'
    );
  }

  // Validate URL format (must be HTTPS in production)
  if (!apiBase.startsWith('http://') && !apiBase.startsWith('https://')) {
    throw new Error(
      `Invalid API base URL: ${apiBase}. Must start with http:// or https://`
    );
  }

  // Remove trailing slash if present
  return apiBase.replace(/\/$/, '');
};

export const API_BASE_URL = getApiBaseUrl();

/**
 * Secure fetch wrapper with error handling
 * Prevents accidental URL exposure in error messages
 */
export async function secureApiCall<T>(
  endpoint: string,
  options: RequestInit = {}
): Promise<T> {
  try {
    // Ensure endpoint starts with /
    const normalizedEndpoint = endpoint.startsWith('/') ? endpoint : `/${endpoint}`;
    const fullUrl = `${API_BASE_URL}${normalizedEndpoint}`;

    // Security headers
    const headers = {
      'Content-Type': 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
      ...options.headers,
    };

    const response = await fetch(fullUrl, {
      ...options,
      headers,
    });

    if (!response.ok) {
      // Don't expose full URL in error messages
      const errorMessage = response.status === 401 
        ? 'Authentication failed' 
        : `API request failed with status ${response.status}`;
      throw new Error(errorMessage);
    }

    return await response.json() as T;
  } catch (error) {
    // Log error safely without exposing sensitive info
    console.error('[API Error]', error instanceof Error ? error.message : 'Unknown error');
    throw error;
  }
}

/**
 * Build authenticated request with JWT token
 */
export async function authenticatedApiCall<T>(
  endpoint: string,
  token: string,
  options: RequestInit = {}
): Promise<T> {
  return secureApiCall<T>(endpoint, {
    ...options,
    headers: {
      'Authorization': `Bearer ${token}`,
      ...options.headers,
    },
  });
}
