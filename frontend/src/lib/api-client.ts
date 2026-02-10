/**
 * 🚀 CLIENTE API UNIFICADO - KAZE-QUANTUM ERP
 * * COMBINA:
 * 1. Validación estricta de entorno (Código 1)
 * 2. Cliente Supabase optimizado (Código 2)
 * 3. Diagnóstico de errores detallado (Red vs Permisos vs Credenciales)
 * 4. Sistema de reintentos automático
 */

import { supabase } from './supabase';
import type { PostgrestError } from '@supabase/supabase-js';

// --- 1. VALIDACIÓN DE ENTORNO (Seguridad al inicio) ---

const getApiBaseUrl = (): string => {
  // Aseguramos que Astro inyecte la variable
  const apiBase = import.meta.env.PUBLIC_API_BASE;
  
  if (!apiBase) {
    console.error('[API] ❌ Error Crítico: Falta PUBLIC_API_BASE');
    // En desarrollo permitimos continuar para no romper el build, pero logueamos error grave
    return ''; 
  }

  // Validación de formato (HTTPS en producción)
  if (import.meta.env.PROD && !apiBase.startsWith('https://')) {
    console.error('[API] ⚠️ Inseguro: Producción requiere HTTPS');
  }

  // Normalizar URL (quitar slash final)
  return apiBase.replace(/\/$/, '');
};

export const API_BASE_URL = getApiBaseUrl();

// --- 2. INTERFACES Y TIPOS ---

export interface ApiError {
  type: 'network' | 'credentials' | 'permissions' | 'server' | 'unknown';
  code: string;
  message: string;
  details?: string;
  status: number;
  recoverable: boolean;
}

export interface PaginatedResult<T> {
  data: T[];
  count: number | null;
  error: ApiError | null;
}

export interface QueryOptions {
  select?: string;
  page?: number;
  limit?: number;
  orderBy?: { column: string; ascending?: boolean };
  filters?: Record<string, any>;
}

// --- 3. DIAGNÓSTICO DE ERRORES (Lógica unificada) ---

function diagnoseError(error: any, context: string = 'API'): ApiError {
  console.error(`[${context}] Error detectado:`, error);

  // A. Error de Supabase (PostgrestError)
  if (error.code && typeof error.code === 'string') {
    let type: ApiError['type'] = 'server';
    let message = error.message;
    
    // Mapeo de códigos PostgreSQL comunes
    if (error.code === '23505') {
       type = 'permissions';
       message = 'Registro duplicado detectado.';
    }
    if (error.code === '42501' || error.code === 'PGRST301') {
       type = 'permissions';
       message = 'No tienes permisos para ver estos datos (RLS).';
    }
    if (error.code === 'PGRST116') {
       type = 'server';
       message = 'No se encontraron resultados.';
    }

    return {
      type,
      code: error.code,
      message,
      details: error.details || error.hint,
      status: 400, // Supabase suele devolver 400 para errores lógicos
      recoverable: true
    };
  }

  // B. Error de Red (Fetch)
  if (error instanceof TypeError && error.message.includes('fetch')) {
    return {
      type: 'network',
      code: 'NET_ERR',
      message: 'Error de conexión con el servidor.',
      details: 'Verifica tu conexión a internet o la VPN.',
      status: 0,
      recoverable: true
    };
  }

  // C. Error HTTP (Response)
  if (error instanceof Response) {
    const status = error.status;
    return {
      type: status === 401 ? 'credentials' : status === 403 ? 'permissions' : 'server',
      code: `HTTP_${status}`,
      message: status === 401 ? 'Sesión expirada.' : 'Error del servidor.',
      details: error.statusText,
      status,
      recoverable: status >= 500
    };
  }

  // D. Desconocido
  return {
    type: 'unknown',
    code: 'UNKNOWN',
    message: error.message || 'Error desconocido',
    details: JSON.stringify(error),
    status: 500,
    recoverable: false
  };
}

// --- 4. CLASE PRINCIPAL (SINGLETON) ---

const MAX_RETRIES = 3;
const RETRY_DELAY_MS = 1000;

class ApiClient {
  
  // ==========================================
  // AUTENTICACIÓN (Vía Supabase)
  // ==========================================

  async getCurrentUser() {
    const { data: { user }, error } = await supabase.auth.getUser();
    if (error) throw diagnoseError(error, 'AUTH');
    return user;
  }

  async login(email: string, password: string) {
    const { data, error } = await supabase.auth.signInWithPassword({ email, password });
    
    if (error) {
      throw diagnoseError(error, 'LOGIN');
    }
    return data;
  }

  async logout() {
    await supabase.auth.signOut();
    // Limpieza segura (Sin localStorage)
    if (typeof window !== 'undefined') {
      // Forzar recarga para limpiar memoria RAM del store
      window.location.href = '/login';
    }
  }

  // ==========================================
  // BASE DE DATOS (Directo a Supabase)
  // ==========================================

  /**
   * Consulta segura a Supabase con reintentos automáticos
   */
  async get<T = any>(table: string, options: QueryOptions = {}): Promise<PaginatedResult<T>> {
    return this.retryOperation(async () => {
      let query = supabase.from(table).select(options.select || '*', { count: 'exact' });

      // Ordenar
      if (options.orderBy) {
        query = query.order(options.orderBy.column, { ascending: options.orderBy.ascending ?? true });
      }

      // Paginar
      if (options.limit) {
        query = query.limit(options.limit);
      }

      // Filtros dinámicos
      if (options.filters) {
        Object.entries(options.filters).forEach(([key, value]) => {
          query = query.eq(key, value);
        });
      }

      const { data, error, count } = await query;

      if (error) throw error; // Lanzamos para que catch lo capture y normalice

      return {
        data: data as T[],
        count: count,
        error: null
      };
    }, `DB_GET_${table}`);
  }

  // ==========================================
  // BACKEND PERSONALIZADO (Rust/Go/Laravel)
  // ==========================================

  /**
   * Llamada Fetch segura para tu backend personalizado
   * Incluye cabeceras de seguridad y manejo de errores unificado
   */
  async callBackend<T>(endpoint: string, options: RequestInit = {}): Promise<T> {
    return this.retryOperation(async () => {
      const normalizedEndpoint = endpoint.startsWith('/') ? endpoint : `/${endpoint}`;
      const fullUrl = `${API_BASE_URL}${normalizedEndpoint}`;

      console.log(`[BACKEND] ${options.method || 'GET'} ${endpoint}`);

      // Headers base
      const headers: HeadersInit = {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest', // Protección CSRF básica
        ...options.headers,
      };

      // Inyectar Token de Supabase si existe (Integración Híbrida)
      const { data: { session } } = await supabase.auth.getSession();
      if (session?.access_token) {
        (headers as any)['Authorization'] = `Bearer ${session.access_token}`;
      }

      const response = await fetch(fullUrl, { ...options, headers });

      if (!response.ok) {
        throw response; // Lanzamos Response para que diagnoseError lo maneje
      }

      return await response.json() as T;
    }, `API_${endpoint}`);
  }

  // ==========================================
  // UTILIDADES DE RESILIENCIA
  // ==========================================

  private async retryOperation<T>(operation: () => Promise<T>, context: string, retries = MAX_RETRIES): Promise<T> {
    try {
      return await operation();
    } catch (error: any) {
      const diagnosis = diagnoseError(error, context);

      // Solo reintentamos errores recuperables (Red o Servidor 500)
      // NO reintentamos errores de permisos (403) o credenciales (401)
      if (retries > 0 && diagnosis.recoverable) {
        console.warn(`[${context}] ⚠️ Fallo temporal (${diagnosis.message}). Reintentando en ${RETRY_DELAY_MS}ms...`);
        await new Promise(resolve => setTimeout(resolve, RETRY_DELAY_MS));
        return this.retryOperation(operation, context, retries - 1);
      }
      
      // Si no es recuperable o se acabaron los intentos, lanzamos el error normalizado
      throw diagnosis;
    }
  }
}

// Exportamos la instancia única
export const api = new ApiClient();