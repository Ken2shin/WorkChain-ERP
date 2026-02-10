/**
 * src/lib/alpine-store.ts
 * Alpine.js Global Store - Kaze-Quantum ERP (Secure Edition)
 * * CAMBIOS APLICADOS:
 * - Tipado estricto para evitar errores TS(7006) y TS(2304).
 * - Arquitectura Zero-Storage (Solo RAM + Cookies).
 */

import type { Alpine } from 'alpinejs';

// --- Interfaces y Tipos ---

export interface Toast {
  id: string;
  message: string;
  type: 'success' | 'error' | 'warning' | 'info';
  duration?: number;
}

interface ConnectionStatus {
  supabase: 'connected' | 'error' | 'checking';
  backend: 'connected' | 'error' | 'checking';
  lastCheck: number;
}

export interface UserProfile {
  id: string;
  name: string;
  email: string;
  role: string;
  tenant_id: string;
  avatar_url?: string;
}

// Interfaz pública del Store para usar en otros archivos
export interface AppStore {
  loading: boolean;
  authenticated: boolean;
  user: UserProfile | null;
  currentTenant: string | null;
  sidebarOpen: boolean;
  toasts: Toast[];
  connectionStatus: ConnectionStatus;
  securityContext: {
    cryptoReady: boolean;
    tlsVerified: boolean;
    corsAllowed: boolean;
  };
  init: () => Promise<void>;
  checkSession: () => Promise<void>;
  addToast: (msg: string, type?: Toast['type'], duration?: number) => string;
  removeToast: (id: string) => void;
  clearToasts: () => void;
  setUser: (user: UserProfile | null) => void;
  logout: () => Promise<void>;
  checkSupabaseConnection: () => Promise<boolean>;
  checkBackendConnection: () => Promise<boolean>;
  getDiagnostics: () => any;
}

// --- Funciones de Utilidad (API Client Seguro) ---

/**
 * Obtiene la URL base de la API
 * Exportada para ser usada en login.astro
 */
export function getApiBaseUrl(): string {
  const baseUrl = import.meta.env.PUBLIC_API_BASE;
  if (!baseUrl) {
    console.error('[SecOps] CRÍTICO: PUBLIC_API_BASE no configurada.');
    return '';
  }
  return baseUrl.replace(/\/$/, '');
}

/**
 * Cliente HTTP Seguro (Zero-Token-Leakage)
 * Exportada para uso global
 */
export async function fetchApi(
  endpoint: string,
  options: RequestInit = {}
): Promise<Response> {
  const baseUrl = getApiBaseUrl();
  const url = `${baseUrl}${endpoint.startsWith('/') ? endpoint : '/' + endpoint}`;

  // Configuración de seguridad para Cookies HttpOnly
  const secureOptions: RequestInit = {
    ...options,
    credentials: 'include', // CRÍTICO: Envía/Recibe cookies del backend
    headers: {
      'Content-Type': 'application/json',
      'X-Requested-With': 'XMLHttpRequest', // Protección CSRF extra
      ...options.headers,
    },
  };

  try {
    const response = await fetch(url, secureOptions);

    // Si el backend rechaza la cookie (expirada o inválida)
    if (response.status === 401) {
      console.warn('[SecOps] Sesión inválida o expirada.');
      if (window.location.pathname !== '/login') {
        window.location.href = '/login';
      }
    }

    return response;
  } catch (error) {
    console.error('[SecOps] Error de red segura:', error);
    throw error;
  }
}

// --- Inicialización del Store ---

export function initializeAlpineStore(alpine: Alpine) {
  alpine.store('app', {
    // Estado Inicial
    loading: true,
    authenticated: false,
    user: null,
    currentTenant: null,
    sidebarOpen: true,
    toasts: [] as Toast[], // Tipado explícito inicial
    connectionStatus: {
      supabase: 'checking',
      backend: 'checking',
      lastCheck: Date.now(),
    },
    securityContext: {
      cryptoReady: true,
      tlsVerified: window.location.protocol === 'https:',
      corsAllowed: true,
    },

    async init() {
      console.log('🚀 [Kaze-Quantum] Iniciando entorno seguro...');
      await this.checkSession();
    },

    async checkSession() {
      try {
        // Consultamos /auth/me esperando que la cookie HttpOnly esté presente
        const response = await fetchApi('/auth/me', { method: 'GET' });
        
        if (response.ok) {
          const userData = await response.json();
          this.setUser(userData);
          this.connectionStatus.backend = 'connected';
        } else {
          this.authenticated = false;
          this.user = null;
        }
      } catch (e) {
        console.error('[Store] Fallo al verificar sesión inicial');
        this.connectionStatus.backend = 'error';
      } finally {
        this.loading = false;
      }
    },

    addToast(message: string, type: Toast['type'] = 'info', duration = 3000) {
      const id = crypto.randomUUID();
      this.toasts.push({ id, message, type, duration });
      if (duration > 0) {
        setTimeout(() => this.removeToast(id), duration);
      }
      return id;
    },

    removeToast(id: string) {
      // CORRECCIÓN TS(7006): Tipar explícitamente 't'
      this.toasts = this.toasts.filter((t: Toast) => t.id !== id);
    },

    clearToasts() {
      this.toasts = [];
    },

    setUser(user: UserProfile | null) {
      this.user = user;
      this.authenticated = !!user;
      if (user) {
        this.currentTenant = user.tenant_id;
      }
    },

    async logout() {
      try {
        await fetchApi('/auth/logout', { method: 'POST' });
      } catch (e) {
        console.error('Error notificando logout al servidor', e);
      } finally {
        this.user = null;
        this.authenticated = false;
        this.currentTenant = null;
        window.location.href = '/login';
      }
    },

    async checkSupabaseConnection(): Promise<boolean> {
      try {
        this.connectionStatus.supabase = 'checking';
        const { supabase } = await import('./supabase'); // Importación dinámica
        const { error } = await supabase.from('tenants').select('count', { count: 'exact', head: true });
        
        const isConnected = !error;
        this.connectionStatus.supabase = isConnected ? 'connected' : 'error';
        this.connectionStatus.lastCheck = Date.now();
        return isConnected;
      } catch (err) {
        this.connectionStatus.supabase = 'error';
        return false;
      }
    },

    async checkBackendConnection(): Promise<boolean> {
      try {
        this.connectionStatus.backend = 'checking';
        const baseUrl = getApiBaseUrl();
        const response = await fetch(`${baseUrl}/health`); 
        const isConnected = response.ok;
        this.connectionStatus.backend = isConnected ? 'connected' : 'error';
        return isConnected;
      } catch (err) {
        this.connectionStatus.backend = 'error';
        return false;
      }
    },

    getDiagnostics() {
      return {
        timestamp: new Date().toISOString(),
        status: this.connectionStatus,
        secureStorage: 'RAM Only (Active)',
        cookiesDetected: document.cookie.length > 0,
        tls: this.securityContext.tlsVerified
      };
    }
  });
}

export function validateEnvironment(): boolean {
  const baseUrl = import.meta.env.PUBLIC_API_BASE;
  if (!baseUrl) {
    console.error('❌ Falta PUBLIC_API_BASE');
    return false;
  }
  return true;
}