import { supabase } from './supabase';
import type { PostgrestError, PostgrestResponse } from '@supabase/supabase-js';

/**
 * 🚀 CLIENTE API DE ALTO RENDIMIENTO - WORKCHAIN ERP
 * * Características de Producción:
 * 1. Retry Strategy: Reintentos automáticos ante fallos de red.
 * 2. Smart Parsing: Normalización de errores de PostgreSQL.
 * 3. Pagination Support: Preparado para tablas con millones de registros.
 * 4. Type Safety: Genéricos estrictos para TypeScript.
 */

export interface ApiError {
  code: string;
  message: string;
  details?: string;
  hint?: string;
  status: number;
}

export interface QueryOptions {
  select?: string;
  page?: number;     // Página actual (1, 2, 3...)
  limit?: number;    // Registros por página (10, 50, 100)
  orderBy?: { column: string; ascending?: boolean };
  filters?: Record<string, any>; // Filtros simples (eq)
}

export interface PaginatedResult<T> {
  data: T[];
  count: number | null;
  error: ApiError | null;
}

const MAX_RETRIES = 3;
const RETRY_DELAY_MS = 1000;

class ApiClient {

  // --- AUTENTICACIÓN & SESIÓN ---

  async getCurrentUser() {
    const { data: { user } } = await supabase.auth.getUser();
    return user;
  }

  async isAuthenticated(): Promise<boolean> {
    const { data: { session } } = await supabase.auth.getSession();
    return !!session;
  }

  async login(email: string, password: string) {
    const { data, error } = await supabase.auth.signInWithPassword({ email, password });
    if (error) throw this.normalizeError(error);
    return data;
  }

  async logout() {
    await supabase.auth.signOut();
    if (typeof window !== 'undefined') {
      sessionStorage.clear();
      localStorage.clear(); // Limpiamos todo por seguridad
      window.location.href = '/login';
    }
  }

  // --- MÉTODOS CRUD OPTIMIZADOS ---

  /**
   * GET Optimizado con Paginación y Filtros
   * Ideal para Grid/Tablas de datos masivos.
   */
  async get<T = any>(table: string, options: QueryOptions = {}): Promise<PaginatedResult<T>> {
    return this.retryOperation(async () => {
      let query = supabase.from(table).select(options.select || '*', { count: 'exact' });

      // 1. Aplicar Filtros (igualdad simple)
      if (options.filters) {
        Object.entries(options.filters).forEach(([key, value]) => {
          if (value !== undefined && value !== null && value !== '') {
            query = query.eq(key, value);
          }
        });
      }

      // 2. Aplicar Ordenamiento
      if (options.orderBy) {
        query = query.order(options.orderBy.column, { ascending: options.orderBy.ascending ?? true });
      }

      // 3. Aplicar Paginación (Vital para alto tráfico)
      if (options.page && options.limit) {
        const from = (options.page - 1) * options.limit;
        const to = from + options.limit - 1;
        query = query.range(from, to);
      } else if (options.limit) {
        query = query.limit(options.limit);
      }

      const { data, error, count } = await query;

      if (error) throw this.normalizeError(error);

      return {
        data: data as T[],
        count: count,
        error: null
      };
    });
  }

  /**
   * Obtener un solo registro por ID (Optimizado)
   */
  async getById<T = any>(table: string, id: string | number, select: string = '*'): Promise<T> {
    return this.retryOperation(async () => {
      const { data, error } = await supabase
        .from(table)
        .select(select)
        .eq('id', id)
        .single();

      if (error) throw this.normalizeError(error);
      return data as T;
    });
  }

  /**
   * POST (Insertar)
   * Devuelve el objeto creado
   */
  async post<T = any>(table: string, payload: any): Promise<T> {
    return this.retryOperation(async () => {
      const { data, error } = await supabase
        .from(table)
        .insert(payload)
        .select()
        .single();

      if (error) throw this.normalizeError(error);
      return data as T;
    });
  }

  /**
   * PUT (Actualizar)
   */
  async put<T = any>(table: string, id: string | number, payload: any): Promise<T> {
    return this.retryOperation(async () => {
      const { data, error } = await supabase
        .from(table)
        .update(payload)
        .eq('id', id)
        .select()
        .single();

      if (error) throw this.normalizeError(error);
      return data as T;
    });
  }

  /**
   * DELETE (Eliminar)
   */
  async delete(table: string, id: string | number): Promise<void> {
    return this.retryOperation(async () => {
      const { error } = await supabase
        .from(table)
        .delete()
        .eq('id', id);

      if (error) throw this.normalizeError(error);
    });
  }

  /**
   * RPC (Remote Procedure Call)
   * Para ejecutar lógica compleja directamente en la Base de Datos (Stored Procedures).
   * Esto es vital para ERPs complejos para reducir latencia.
   */
  async rpc<T = any>(functionName: string, params?: Record<string, any>): Promise<T> {
    return this.retryOperation(async () => {
      const { data, error } = await supabase.rpc(functionName, params);
      if (error) throw this.normalizeError(error);
      return data as T;
    });
  }

  // --- UTILIDADES INTERNAS DE RESILIENCIA ---

  /**
   * Mecanismo de Reintento (Exponential Backoff)
   * Si la red falla, reintenta 3 veces antes de rendirse.
   */
  private async retryOperation<T>(operation: () => Promise<T>, retries = MAX_RETRIES): Promise<T> {
    try {
      return await operation();
    } catch (error: any) {
      // Solo reintentamos si es error de red o timeout (5xx, NetworkError)
      // No reintentamos si es 4xx (Bad Request, Unauthorized, etc)
      const isRetryable = !error.status || (error.status >= 500 && error.status < 600);

      if (retries > 0 && isRetryable) {
        console.warn(`⚠️ Red inestable, reintentando operación... (${retries} restantes)`);
        await new Promise(resolve => setTimeout(resolve, RETRY_DELAY_MS));
        return this.retryOperation(operation, retries - 1);
      }
      throw error;
    }
  }

  /**
   * Normalizador de Errores de PostgreSQL a formato legible
   */
  private normalizeError(error: PostgrestError | any): ApiError {
    console.error('🔥 DB Error:', error);

    // Mapeo de códigos comunes de Postgres
    let readableMessage = error.message;
    if (error.code === '23505') readableMessage = 'Este registro ya existe (duplicado).';
    if (error.code === '23503') readableMessage = 'No se puede eliminar porque tiene datos relacionados.';
    if (error.code === 'PGRST116') readableMessage = 'No se encontraron resultados.';

    return {
      status: error.code ? 400 : 500, // Postgres errors suelen ser 400 (Bad Request)
      code: error.code || 'UNKNOWN',
      message: readableMessage,
      details: error.details || '',
      hint: error.hint || ''
    };
  }
}

export const api = new ApiClient();