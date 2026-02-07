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
  tenant_id: string;
}

// Configuración de llaves para evitar errores de tipeo
const STORAGE_KEYS = {
  TOKEN: 'access_token', // Alineado con tu Login
  USER: 'user',
  TENANT: 'current_tenant'
};

/**
 * Inicializa el store global de Alpine
 */
export function initializeAlpineStore(alpine: Alpine) {
  alpine.store('app', {
    loading: false,
    // Usamos sessionStorage para coincidir con tu Login
    authenticated: !!sessionStorage.getItem(STORAGE_KEYS.TOKEN),
    user: JSON.parse(sessionStorage.getItem(STORAGE_KEYS.USER) || 'null'),
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
          this.removeToast(id);
        }, duration);
      }
      return id;
    },

    /**
     * Remueve un toast específico
     */
    removeToast(id: string) {
      this.toasts = this.toasts.filter((t: Toast) => t.id !== id);
    },

    clearToasts() {
      this.toasts = [];
    },

    /**
     * Establece el usuario autenticado
     */
    setUser(user: User | null) {
      this.user = user;
      this.authenticated = !!user;
      
      if (user) {
        sessionStorage.setItem(STORAGE_KEYS.USER, JSON.stringify(user));
      } else {
        sessionStorage.removeItem(STORAGE_KEYS.USER);
      }
    },

    /**
     * Cierra sesión
     */
    logout() {
      sessionStorage.removeItem(STORAGE_KEYS.TOKEN);
      sessionStorage.removeItem(STORAGE_KEYS.USER);
      sessionStorage.removeItem(STORAGE_KEYS.TENANT);
      
      this.user = null;
      this.authenticated = false;
      
      window.location.href = '/'; // O a /login
    },
  });
}

/**
 * Obtiene la URL de la API desde variables de entorno
 */
export function getApiBaseUrl(): string {
  // Soporte para Vite/Astro
  const baseUrl = import.meta.env.PUBLIC_API_URL || import.meta.env.PUBLIC_API_BASE;

  if (!baseUrl) {
    console.warn('[Store] PUBLIC_API_URL no definida. Usando localhost por defecto.');
    return 'http://localhost:8000'; // Fallback seguro para desarrollo
  }

  return baseUrl.replace(/\/$/, '');
}

/**
 * Realiza una llamada API segura
 * Maneja automáticamente JSON vs FormData y el Token
 */
export async function fetchApi(
  endpoint: string,
  options: RequestInit = {}
): Promise<Response> {
  const baseUrl = getApiBaseUrl();
  const url = `${baseUrl}${endpoint.startsWith('/') ? endpoint : '/' + endpoint}`;
  const token = sessionStorage.getItem(STORAGE_KEYS.TOKEN);

  // Gestión inteligente de Headers
  const headers = new Headers(options.headers || {});

  // Solo agregamos Content-Type: json si NO estamos enviando un archivo (FormData)
  // y si el usuario no ha especificado ya otro Content-Type.
  if (!headers.has('Content-Type') && !(options.body instanceof FormData)) {
    headers.set('Content-Type', 'application/json');
  }

  if (token) {
    headers.set('Authorization', `Bearer ${token}`);
  }

  try {
    const response = await fetch(url, {
      ...options,
      headers,
    });

    if (response.status === 401) {
      console.warn('[API] Sesión expirada (401)');
      // Opcional: Llamar a logout() o redirigir
      sessionStorage.removeItem(STORAGE_KEYS.TOKEN);
      window.location.href = '/'; 
    }

    return response;
  } catch (error) {
    console.error('[API] Error de red:', error);
    throw error;
  }
}