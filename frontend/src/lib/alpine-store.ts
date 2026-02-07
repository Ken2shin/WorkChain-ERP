/**
 * Alpine.js Global Store
 * Centraliza el estado global de la aplicación
 * Se registra en Layout.astro usando alpine:init
 */

import type { Alpine } from 'alpinejs';

// Tipos de datos
export interface Toast {
  id: string;
  message: string;
  type: 'success' | 'error' | 'warning' | 'info';
  duration?: number;
}

export interface User {
  id: string;
  name: string;
  email: string;
  role: string;
  tenant_id: string; // UUID string
}

interface AppState {
  loading: boolean;
  authenticated: boolean;
  user: User | null;
  currentTenant: string | null;
  sidebarOpen: boolean;
  toasts: Toast[];
}

/**
 * Inicializa el store global de Alpine
 * Debe ser llamado en Layout.astro con alpine:init
 */
export function initializeAlpineStore(alpine: Alpine) {
  // Store Global de Aplicación
  alpine.store('app', {
    loading: false,
    authenticated: !!localStorage.getItem('auth_token'),
    user: null,
    currentTenant: localStorage.getItem('current_tenant'),
    sidebarOpen: true,
    toasts: [] as Toast[],

    /**
     * Agrega un toast a la pila
     */
    addToast(message: string, type: 'success' | 'error' | 'warning' | 'info' = 'info', duration = 3000) {
      const id = Math.random().toString(36).substr(2, 9);
      const toast: Toast = { id, message, type, duration };

      this.toasts.push(toast);

      if (duration > 0) {
        setTimeout(() => {
          // CORRECCIÓN 1: Tipado explícito de 't'
          this.toasts = this.toasts.filter((t: Toast) => t.id !== id);
        }, duration);
      }

      return id;
    },

    /**
     * Remueve un toast específico
     */
    removeToast(id: string) {
      // CORRECCIÓN 2: Tipado explícito de 't'
      this.toasts = this.toasts.filter((t: Toast) => t.id !== id);
    },

    /**
     * Limpia todos los toasts
     */
    clearToasts() {
      this.toasts = [];
    },

    /**
     * Establece el usuario autenticado
     */
    setUser(user: User | null) {
      this.user = user;
      this.authenticated = !!user;
    },

    /**
     * Cierra sesión
     */
    logout() {
      localStorage.removeItem('auth_token');
      localStorage.removeItem('refresh_token');
      localStorage.removeItem('current_tenant');
      this.user = null;
      this.authenticated = false;
      this.currentTenant = null;
      window.location.href = '/login';
    },
  });
}

/**
 * Obtiene la URL de la API desde variables de entorno
 * SIN hardcodeo de puertos
 */
export function getApiBaseUrl(): string {
  const baseUrl = import.meta.env.PUBLIC_API_BASE;

  if (!baseUrl) {
    console.error(
      '[v0] PUBLIC_API_BASE no está configurada. Verifica tu archivo .env'
    );
    return '';
  }

  // Asegura que no haya doble slash al final
  return baseUrl.replace(/\/$/, '');
}

/**
 * Realiza una llamada API segura
 * Incluye el token JWT automáticamente
 * USA PUBLIC_API_BASE, NO puertos hardcodeados
 */
export async function fetchApi(
  endpoint: string,
  options: RequestInit = {}
): Promise<Response> {
  const baseUrl = getApiBaseUrl();
  const url = `${baseUrl}${endpoint.startsWith('/') ? endpoint : '/' + endpoint}`;

  const token = localStorage.getItem('auth_token');

  // CORRECCIÓN 3: Uso de Record<string, string> para evitar error de índice
  // Esto permite asignar 'Authorization' sin problemas.
  const headers: Record<string, string> = {
    'Content-Type': 'application/json',
    ...(options.headers as Record<string, string> || {}),
  };

  if (token) {
    headers['Authorization'] = `Bearer ${token}`;
  }

  try {
    const response = await fetch(url, {
      ...options,
      headers,
    });

    // Si es 401, el token expiró
    if (response.status === 401) {
      localStorage.removeItem('auth_token');
      window.location.href = '/login';
    }

    return response;
  } catch (error) {
    console.error('[v0] Error en llamada API:', error);
    throw error;
  }
}

/**
 * Valida que el ambiente esté correctamente configurado
 */
export function validateEnvironment(): boolean {
  const baseUrl = import.meta.env.PUBLIC_API_BASE;

  if (!baseUrl) {
    console.error('[v0] PUBLIC_API_BASE no está configurada en .env');
    return false;
  }

  // Advertencia si está usando localhost en producción
  if (baseUrl.includes('localhost') && import.meta.env.PROD) {
    console.warn(
      '[v0] Advertencia: PUBLIC_API_BASE apunta a localhost en producción'
    );
  }

  return true;
}