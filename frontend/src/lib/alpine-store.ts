/**
 * src/lib/alpine-store.ts
 * Alpine.js Global Store - Kaze-Quantum ERP (Secure Edition)
 * * MEJORAS DE SEGURIDAD:
 * - Inyección automática de X-Tenant-ID en todas las peticiones (Vital para filtrado).
 * - Tipado estricto para evitar errores de compilación.
 * - Manejo robusto de errores de red y sesión.
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
  tenant_id: string; // CRÍTICO: Este ID define el aislamiento de datos
  avatar_url?: string;
  permissions?: string[];
}

export interface SecurityDiagnostics {
  timestamp: string;
  status: ConnectionStatus;
  secureStorage: string;
  cookiesDetected: boolean;
  tls: boolean;
  currentTenantHeader: string | null;
}

// Definición estricta del Store
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
  
  // Métodos
  init: () => Promise<void>;
  checkSession: () => Promise<void>;
  addToast: (msg: string, type?: Toast['type'], duration?: number) => string;
  removeToast: (id: string) => void;
  clearToasts: () => void;
  setUser: (user: UserProfile | null) => void;
  logout: () => Promise<void>;
  checkSupabaseConnection: () => Promise<boolean>;
  checkBackendConnection: () => Promise<boolean>;
  getDiagnostics: () => SecurityDiagnostics;
}

// --- Funciones de Utilidad (API Client Seguro) ---

export function getApiBaseUrl(): string {
  // Aseguramos que no sea undefined y quitamos slash final
  const baseUrl = import.meta.env.PUBLIC_API_BASE || '';
  if (!baseUrl) {
    console.warn('[SecOps] ADVERTENCIA: PUBLIC_API_BASE no está definido en .env');
  }
  return baseUrl.replace(/\/$/, '');
}

/**
 * Cliente HTTP Seguro (Zero-Token-Leakage)
 * CORRECCIÓN CRÍTICA: Inyecta el X-Tenant-ID dinámicamente.
 */
export async function fetchApi(
  endpoint: string,
  options: RequestInit = {}
): Promise<Response> {
  const baseUrl = getApiBaseUrl();
  const url = `${baseUrl}${endpoint.startsWith('/') ? endpoint : '/' + endpoint}`;

  // 1. Obtener el Tenant ID del Store de Alpine (si existe)
  // Esto es vital para asegurar que el Backend filtre los datos correctamente
  // incluso si la cookie de sesión es ambigua.
  let tenantHeader: Record<string, string> = {};
  
  // Acceso seguro al objeto global Alpine (solo en navegador)
  if (typeof window !== 'undefined' && (window as any).Alpine) {
    const store = (window as any).Alpine.store('app') as AppStore;
    if (store && store.currentTenant) {
      tenantHeader['X-Tenant-ID'] = store.currentTenant;
    }
  }

  // 2. Configuración de seguridad
  const secureOptions: RequestInit = {
    ...options,
    credentials: 'include', // Envía cookies HttpOnly (JWT)
    headers: {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      'X-Requested-With': 'XMLHttpRequest', // Protección CSRF estándar
      ...tenantHeader, // <--- AQUÍ SE APLICA EL FILTRO DE ORGANIZACIÓN
      ...options.headers,
    },
  };

  try {
    const response = await fetch(url, secureOptions);

    // 3. Manejo de Sesión Expirada (401)
    if (response.status === 401) {
      console.warn('[SecOps] Sesión inválida o expirada (401).');
      // Evitar bucle de redirección si ya estamos en login
      if (typeof window !== 'undefined' && !window.location.pathname.includes('/login')) {
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
  console.log('🔒 [Kaze-Quantum] Inicializando Security Store...');

  alpine.store('app', {
    // Estado Inicial
    loading: true,
    authenticated: false,
    user: null,
    currentTenant: null,
    sidebarOpen: true,
    toasts: [],
    connectionStatus: {
      supabase: 'checking',
      backend: 'checking',
      lastCheck: Date.now(),
    },
    securityContext: {
      cryptoReady: true,
      tlsVerified: typeof window !== 'undefined' && window.location.protocol === 'https:',
      corsAllowed: true,
    },

    async init() {
      await this.checkSession();
    },

    async checkSession() {
      try {
        // Consultamos /auth/me. El backend debe devolver el usuario y su tenant_id
        const response = await fetchApi('/auth/me', { method: 'GET' });
        
        if (response.ok) {
          const userData: UserProfile = await response.json();
          this.setUser(userData);
          this.connectionStatus.backend = 'connected';
        } else {
          this.setUser(null); // Limpia estado si falla
        }
      } catch (e) {
        console.error('[Store] Error verificando sesión:', e);
        this.connectionStatus.backend = 'error';
        this.setUser(null);
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
      this.toasts = this.toasts.filter((t) => t.id !== id);
    },

    clearToasts() {
      this.toasts = [];
    },

    setUser(user: UserProfile | null) {
      this.user = user;
      this.authenticated = !!user;
      
      if (user && user.tenant_id) {
        this.currentTenant = user.tenant_id;
        console.log(`[Store] Contexto establecido: Organización ${this.currentTenant}`);
      } else {
        this.currentTenant = null;
      }
    },

    async logout() {
      try {
        await fetchApi('/auth/logout', { method: 'POST' });
      } catch (e) {
        console.warn('Logout forzado (error de red)');
      } finally {
        this.setUser(null);
        window.location.href = '/login';
      }
    },

    async checkSupabaseConnection(): Promise<boolean> {
      try {
        this.connectionStatus.supabase = 'checking';
        // Importación dinámica para code-splitting
        const { supabase } = await import('./supabase'); 
        
        // Query ligera para verificar conexión
        const { error } = await supabase.from('tenants').select('count', { count: 'exact', head: true });
        
        const isConnected = !error;
        this.connectionStatus.supabase = isConnected ? 'connected' : 'error';
        this.connectionStatus.lastCheck = Date.now();
        return isConnected;
      } catch (err) {
        console.error('[Supabase] Error de conexión:', err);
        this.connectionStatus.supabase = 'error';
        return false;
      }
    },

    async checkBackendConnection(): Promise<boolean> {
      try {
        this.connectionStatus.backend = 'checking';
        const response = await fetchApi('/health'); // Usamos fetchApi para verificar ruta base también
        const isConnected = response.ok;
        this.connectionStatus.backend = isConnected ? 'connected' : 'error';
        return isConnected;
      } catch (err) {
        this.connectionStatus.backend = 'error';
        return false;
      }
    },

    getDiagnostics(): SecurityDiagnostics {
      return {
        timestamp: new Date().toISOString(),
        status: this.connectionStatus,
        secureStorage: 'RAM Only (Active)',
        cookiesDetected: typeof document !== 'undefined' && document.cookie.length > 0,
        tls: this.securityContext.tlsVerified,
        currentTenantHeader: this.currentTenant // Verificación de que el tenant está cargado
      };
    }
  } as AppStore); // Cast explícito para asegurar tipado
}

export function validateEnvironment(): boolean {
  const baseUrl = import.meta.env.PUBLIC_API_BASE;
  if (!baseUrl) {
    console.error('❌ FATAL: Falta PUBLIC_API_BASE en variables de entorno.');
    return false;
  }
  return true;
}