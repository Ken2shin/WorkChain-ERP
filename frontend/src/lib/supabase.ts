import { createClient } from '@supabase/supabase-js';

/**
 * src/lib/supabase.ts
 * Cliente Supabase para Kaze-Quantum ERP
 * * MEJORAS DE SEGURIDAD APLICADAS:
 * - Tipado estricto (Interfaces) para evitar errores de mapeo.
 * - Nueva función `getTenantByDomain` para soportar la estrategia de subdominios.
 * - Protección contra Tenant Enumeration (Advertencia en getTenants).
 */

// --- Tipos e Interfaces ---

export interface TenantPublicInfo {
  id: string;
  name: string;
  domain?: string;
  logo_url?: string;
}

// --- Inicialización del Cliente ---

const supabaseUrl = import.meta.env.PUBLIC_SUPABASE_URL;
const supabaseAnonKey = import.meta.env.PUBLIC_SUPABASE_ANON_KEY;

if (!supabaseUrl || !supabaseAnonKey) {
  console.error('[Supabase] CRITICAL: Missing environment variables.');
  throw new Error('Supabase credentials not configured in environment');
}

export const supabase = createClient(supabaseUrl, supabaseAnonKey, {
  auth: {
    persistSession: true, // Mantiene la sesión si usas Auth de Supabase
    autoRefreshToken: true,
  }
});

// --- Funciones de Datos ---

/**
 * CRÍTICO PARA EL LOGIN POR SUBDOMINIO:
 * Busca un tenant específico basado en el dominio/subdominio de la URL.
 * Esto asegura que el login se filtre automáticamente sin que el usuario elija de una lista.
 * * @param domain El dominio actual (ej: demo.workchain-erp.onrender.com)
 */
export async function getTenantByDomain(domain: string): Promise<TenantPublicInfo | null> {
  try {
    const { data, error } = await supabase
      .from('tenants')
      .select('id, name, domain')
      .eq('domain', domain) // Filtro estricto por dominio
      .eq('is_active', true)
      .single();

    if (error || !data) {
      console.warn(`[Supabase] No tenant found for domain: ${domain}`);
      return null;
    }

    return {
      id: String(data.id),
      name: data.name,
      domain: data.domain
    };
  } catch (err) {
    console.error('[Supabase] Error resolving tenant by domain:', err);
    return null;
  }
}

/**
 * Obtiene la lista de organizaciones activas.
 * ⚠️ ADVERTENCIA DE SEGURIDAD:
 * Esta función expone la lista de clientes. En producción con "Subdomain Strategy",
 * deberías evitar usarla en el login público para prevenir enumeración.
 * Úsala solo si necesitas un selector manual de empresas.
 */
export async function getTenants(): Promise<TenantPublicInfo[]> {
  // console.log('[Supabase] Fetching active tenants list...'); 

  try {
    const { data, error } = await supabase
      .from('tenants')
      .select('id, name')
      .eq('is_active', true)
      .order('name', { ascending: true });

    if (error) {
      console.error('[Supabase] Query error:', error.message);
      throw new Error(`Failed to load tenants: ${error.message}`);
    }

    return (data ?? []).map((tenant) => ({
      id: String(tenant.id),
      name: tenant.name,
    }));
  } catch (err) {
    console.error('[Supabase] Connection error:', err);
    return []; // Fail-safe: retorna array vacío en vez de romper la UI
  }
}

/**
 * Valida que una organización exista y esté activa por ID.
 */
export async function validateTenant(tenantId: string): Promise<boolean> {
  try {
    // Validación básica de formato UUID para evitar llamadas innecesarias a la BD
    if (!tenantId || tenantId.length < 10) return false;

    const { data, error } = await supabase
      .from('tenants')
      .select('id')
      .eq('id', tenantId)
      .eq('is_active', true)
      .single();

    if (error || !data) {
      return false;
    }

    return true;
  } catch (err) {
    console.error('[Supabase] Validation error:', err);
    return false;
  }
}

/**
 * Health Check de Supabase
 */
export async function checkConnection(): Promise<boolean> {
  try {
    const { error } = await supabase
      .from('tenants')
      .select('count', { count: 'exact', head: true }) // Query más ligera posible (HEAD)
      .limit(1);

    if (error) {
      console.error('[Supabase] Health check failed:', error.message);
      return false;
    }
    return true;
  } catch (err) {
    return false;
  }
}