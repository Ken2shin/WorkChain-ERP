/**
 * API Client para comunicación con Laravel backend
 * Maneja autenticación, errores y reintentos automáticos
 */

// 1. VALIDACIÓN DE ENTORNO
// Eliminamos cualquier referencia a localhost para evitar errores en Render
const getApiBase = (): string => {
  const url = import.meta.env.PUBLIC_API_BASE;
  
  if (!url) {
    console.error('CRITICAL ERROR: PUBLIC_API_BASE no está definida en el archivo .env');
    // Retornamos cadena vacía para que falle de forma controlada en lugar de conectar a localhost
    return ''; 
  }
  
  // Eliminar slash final si existe para evitar 'https://dominio.com//api'
  return url.replace(/\/$/, '');
};

const API_BASE = getApiBase();
const API_VERSION = 'v1';
const MAX_RETRIES = 2; 
const RETRY_DELAY = 1000;

interface ApiResponse<T = unknown> {
  success: boolean;
  data?: T;
  message?: string;
  errors?: Record<string, string[]>;
  status: number;
}

interface ApiError {
  status: number;
  message: string;
  errors?: Record<string, string[]>;
}

class ApiClient {
  private token: string | null = null;
  private refreshPromise: Promise<string> | null = null;

  /**
   * Obtener token desde sessionStorage
   */
  private getToken(): string | null {
    if (typeof window !== 'undefined') {
      return sessionStorage.getItem('access_token');
    }
    return null;
  }

  /**
   * Guardar token en sessionStorage
   */
  private setToken(token: string): void {
    if (typeof window !== 'undefined') {
      sessionStorage.setItem('access_token', token);
      this.token = token;
    }
  }

  /**
   * Limpiar token
   */
  private clearToken(): void {
    if (typeof window !== 'undefined') {
      sessionStorage.removeItem('access_token');
      sessionStorage.removeItem('refresh_token');
      sessionStorage.removeItem('user');
    }
    this.token = null;
  }

  /**
   * Realizar petición HTTP con reintentos
   */
  async request<T = unknown>(
    endpoint: string,
    options: RequestInit = {},
    attempt: number = 1
  ): Promise<ApiResponse<T>> {
    // Construcción segura de la URL
    const cleanEndpoint = endpoint.startsWith('/') ? endpoint : `/${endpoint}`;
    const url = `${API_BASE}/${API_VERSION}${cleanEndpoint}`;
    
    const token = this.getToken();

    // CORRECCIÓN DEL ERROR DE TYPESCRIPT:
    // Definimos headers como un Record<string, string> explícito para poder manipularlo
    const headers: Record<string, string> = {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
    };

    // Mezclar headers personalizados si existen
    if (options.headers) {
        // Asumimos que options.headers es un objeto simple para facilitar la mezcla
        const customHeaders = options.headers as Record<string, string>;
        Object.assign(headers, customHeaders);
    }

    // Inyectar token si existe
    if (token) {
      headers['Authorization'] = `Bearer ${token}`;
    }

    try {
      const response = await fetch(url, {
        ...options,
        headers: headers, // Pasamos el objeto ya tipado correctamente
        credentials: 'include',
      });

      // Manejo seguro del body
      let data;
      try {
        data = await response.json();
      } catch (e) {
        data = {}; 
      }

      // Lógica de Refresco de Token (401)
      if (response.status === 401 && (data.message?.includes('Unauthenticated') || data.message?.includes('token'))) {
        
        if (attempt > MAX_RETRIES) {
             this.clearToken();
             if (typeof window !== 'undefined') window.location.href = '/login';
             throw { status: 401, message: 'Session expired' };
        }

        if (this.refreshPromise) {
          await this.refreshPromise;
          return this.request<T>(endpoint, options, attempt + 1);
        }

        this.refreshPromise = this.refreshToken();
        
        try {
          await this.refreshPromise;
          return this.request<T>(endpoint, options, attempt + 1);
        } catch (error) {
          this.clearToken();
          if (typeof window !== 'undefined') {
            window.location.href = '/login';
          }
          throw error;
        } finally {
          this.refreshPromise = null;
        }
      }

      return {
        success: response.ok,
        data: data.data || data,
        message: data.message,
        errors: data.errors,
        status: response.status,
      };

    } catch (error) {
      // Reintentos por fallo de red
      if (attempt < MAX_RETRIES && error instanceof TypeError && error.message === 'Failed to fetch') {
        await new Promise(resolve => setTimeout(resolve, RETRY_DELAY * attempt));
        return this.request<T>(endpoint, options, attempt + 1);
      }

      throw {
        status: 0,
        message: error instanceof Error ? error.message : 'Connection error',
      };
    }
  }

  // --- MÉTODOS PÚBLICOS (GET, POST, PUT, DELETE) ---

  async get<T = unknown>(endpoint: string): Promise<T> {
    const response = await this.request<T>(endpoint, { method: 'GET' });
    if (!response.success) {
      throw {
        status: response.status,
        message: response.message || 'Request failed',
        errors: response.errors
      } as ApiError;
    }
    return response.data as T;
  }

  async post<T = unknown>(endpoint: string, data: unknown): Promise<T> {
    const response = await this.request<T>(endpoint, {
      method: 'POST',
      body: JSON.stringify(data),
    });
    if (!response.success) {
      throw {
        status: response.status,
        message: response.message || 'Request failed',
        errors: response.errors,
      } as ApiError;
    }
    return response.data as T;
  }

  async put<T = unknown>(endpoint: string, data: unknown): Promise<T> {
    const response = await this.request<T>(endpoint, {
      method: 'PUT',
      body: JSON.stringify(data),
    });
    if (!response.success) {
      throw {
        status: response.status,
        message: response.message || 'Request failed',
        errors: response.errors,
      } as ApiError;
    }
    return response.data as T;
  }

  async delete<T = unknown>(endpoint: string): Promise<T> {
    const response = await this.request<T>(endpoint, { method: 'DELETE' });
    if (!response.success) {
      throw {
        status: response.status,
        message: response.message || 'Request failed',
      } as ApiError;
    }
    return response.data as T;
  }

  /**
   * Login
   */
  async login(email: string, password: string, tenantId: number): Promise<{
    user: {
      id: number;
      name: string;
      email: string;
      role: string;
      tenant_id: number;
    };
    access_token: string;
    refresh_token: string;
  }> {
    // Usamos el método post interno que ya maneja la URL base
    const response = await this.post('/auth/login', {
      email,
      password,
      tenant_id: tenantId,
    });
    
    const authData = response as any;
    
    this.setToken(authData.access_token);
    
    if (typeof window !== 'undefined') {
      sessionStorage.setItem('refresh_token', authData.refresh_token);
      sessionStorage.setItem('user', JSON.stringify(authData.user));
    }

    return authData;
  }

  /**
   * Refrescar token (Método privado)
   */
  private async refreshToken(): Promise<string> {
    if (typeof window !== 'undefined') {
      const refreshToken = sessionStorage.getItem('refresh_token');
      if (!refreshToken) {
        throw new Error('No refresh token available');
      }

      try {
        const url = `${API_BASE}/${API_VERSION}/auth/refresh`;
        const response = await fetch(url, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'Authorization': `Bearer ${refreshToken}`
          },
          body: JSON.stringify({ refresh_token: refreshToken }),
        });

        const data = await response.json();
        
        if (response.ok && (data.access_token || data.data?.access_token)) {
          const newToken = data.access_token || data.data.access_token;
          this.setToken(newToken);
          return newToken;
        }

        throw new Error('Token refresh failed');
      } catch (error) {
        this.clearToken();
        throw error;
      }
    }
    throw new Error('Window not available');
  }

  /**
   * Logout
   */
  async logout(): Promise<void> {
    try {
      await this.post('/auth/logout', {});
    } catch (error) {
      console.warn('Logout server error', error);
    } finally {
      this.clearToken();
      if (typeof window !== 'undefined') {
         window.location.href = '/login';
      }
    }
  }

  async getCurrentUser(): Promise<any> {
    return this.get('/auth/me');
  }

  isAuthenticated(): boolean {
    return !!this.getToken();
  }
}

export const api = new ApiClient();
export type { ApiError };