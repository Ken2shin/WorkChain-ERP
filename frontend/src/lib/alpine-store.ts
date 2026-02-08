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
  name?: string;
  email: string;
  role: string;
  tenant_id: string;
}

// Configuración de llaves
const STORAGE_KEYS = {
  TOKEN: 'access_token',
  USER: 'user',
  TENANT: 'current_tenant'
};

/**
 * Inicializa el store global de Alpine
 */
export function initializeAlpineStore(alpine: Alpine) {
  alpine.store('app', {
    loading: false,
    // Leemos el estado inicial desde sessionStorage (que rellenamos en el Login)
    authenticated: !!sessionStorage.getItem(STORAGE_KEYS.TOKEN),
    user: JSON.parse(sessionStorage.getItem(STORAGE_KEYS.USER) || 'null'),
    sidebarOpen: true,
    toasts: [] as Toast[],

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

    removeToast(id: string) {
      this.toasts = this.toasts.filter((t: Toast) => t.id !== id);
    },

    clearToasts() {
      this.toasts = [];
    },

    setUser(user: User | null) {
      this.user = user;
      this.authenticated = !!user;
      
      if (user) {
        sessionStorage.setItem(STORAGE_KEYS.USER, JSON.stringify(user));
      } else {
        sessionStorage.removeItem(STORAGE_KEYS.USER);
      }
    },

    logout() {
      sessionStorage.removeItem(STORAGE_KEYS.TOKEN);
      sessionStorage.removeItem(STORAGE_KEYS.USER);
      sessionStorage.removeItem(STORAGE_KEYS.TENANT);
      
      this.user = null;
      this.authenticated = false;
      
      // Opcional: Limpiar sesión de Supabase también
      // import { supabase } from './supabase'; supabase.auth.signOut();
      
      window.location.href = '/'; 
    },
  });
}