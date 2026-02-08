import { supabase } from './supabase';
import type { PostgrestError } from '@supabase/supabase-js';

/**
 * 🚀 CLIENTE API DE ALTO RENDIMIENTO - WORKCHAIN ERP
 * Compatible con: Supabase + Astro + Alpine.js
 */

export interface ApiError {
  code: string;
  message: string;
  details?: string;
  status: number;
}

export interface QueryOptions {
  select?: string;
  page?: number;
  limit?: number;
  orderBy?: { column: string; ascending?: boolean };
  filters?: Record<string, any>;
}

export interface PaginatedResult<T> {
  data: T[];
  count: number | null;
  error: ApiError | null;
}

const MAX_RETRIES = 3;
const RETRY_DELAY_MS = 1000;

class ApiClient {

  // --- AUTENTICACIÓN ---

  async getCurrentUser() {
    const { data: { user } } = await supabase.auth.getUser();
    return user;
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
      window.location.href = '/login';
    }
  }

  // --- MÉTODOS CRUD ROBUSTOS ---

  /**
   * GET: Obtener datos con reintentos automáticos
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

      const { data, error, count } = await query;

      if (error) throw this.normalizeError(error);

      return {
        data: data as T[],
        count: count,
        error: null
      };
    });
  }

  // --- UTILIDADES INTERNAS DE RESILIENCIA ---

  /**
   * Sistema de Reintentos (Si falla el internet, reintenta 3 veces)
   */
  private async retryOperation<T>(operation: () => Promise<T>, retries = MAX_RETRIES): Promise<T> {
    try {
      return await operation();
    } catch (error: any) {
      // Reintentar si es error de red (status 0 o 5xx)
      const isRetryable = !error.status || (error.status >= 500);

      if (retries > 0 && isRetryable) {
        console.warn(`⚠️ Red inestable, reintentando... (${retries} restantes)`);
        await new Promise(resolve => setTimeout(resolve, RETRY_DELAY_MS));
        return this.retryOperation(operation, retries - 1);
      }
      throw error;
    }
  }

  /**
   * Traductor de errores de Base de Datos a Humano
   */
  private normalizeError(error: PostgrestError | any): ApiError {
    console.error('🔥 DB Error:', error);

    let readableMessage = error.message;
    if (error.code === '23505') readableMessage = 'Este registro ya existe.';
    if (error.code === 'PGRST116') readableMessage = 'No se encontraron resultados.';

    return {
      status: error.code ? 400 : 500,
      code: error.code || 'UNKNOWN',
      message: readableMessage,
      details: error.details || ''
    };
  }
}

// ESTA ES LA LÍNEA QUE ARREGLA TU ERROR EN VS CODE:
export const api = new ApiClient();