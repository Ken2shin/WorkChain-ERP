import { createClient } from '@supabase/supabase-js';

/**
 * AUDITORIA CRITICA CORREGIDA:
 * El problema era que no había cliente de Supabase instanciado.
 * AlpineJS no podía acceder a los datos porque faltaba la conexión.
 * 
 * SOLUCION:
 * - Validamos que las variables de entorno sean accesibles (PUBLIC_*)
 * - Creamos la instancia del cliente una sola vez
 * - Exponemos funciones tipadas para consultar tenants
 */

// Validar que las variables de entorno existan
const supabaseUrl = import.meta.env.PUBLIC_SUPABASE_URL;
const supabaseAnonKey = import.meta.env.PUBLIC_SUPABASE_ANON_KEY;

if (!supabaseUrl || !supabaseAnonKey) {
  console.error('[Supabase] Missing environment variables:');
  console.error('  - PUBLIC_SUPABASE_URL:', supabaseUrl ? '✓' : '✗ MISSING');
  console.error('  - PUBLIC_SUPABASE_ANON_KEY:', supabaseAnonKey ? '✓' : '✗ MISSING');
  throw new Error('Supabase credentials not configured in environment');
}

// Crear cliente de Supabase (instancia única)
export const supabase = createClient(supabaseUrl, supabaseAnonKey);

/**
 * Obtiene la lista de organizaciones (tenants) activos desde Supabase
 * SEGURIDAD: Filtra solo registros activos (is_active = true)
 * 
 * @returns Array de organizaciones con id y name
 */
export async function getTenants(): Promise<Array<{ id: string; name: string }>> {
  console.log('[Supabase] Fetching active tenants from PostgreSQL...');

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

    console.log('[Supabase] Tenants loaded successfully:', data?.length ?? 0);
    
    // Asegura tipos correctos (string IDs, no números)
    return data?.map((tenant: any) => ({
      id: String(tenant.id),
      name: tenant.name,
    })) ?? [];
  } catch (err) {
    const errorMsg = err instanceof Error ? err.message : String(err);
    console.error('[Supabase] Connection error:', errorMsg);
    throw err;
  }
}

/**
 * Valida que una organización exista y esté activa
 * SEGURIDAD: Verifica permisos antes de login
 */
export async function validateTenant(tenantId: string): Promise<boolean> {
  try {
    const { data, error } = await supabase
      .from('tenants')
      .select('id')
      .eq('id', tenantId)
      .eq('is_active', true)
      .single();

    if (error || !data) {
      console.warn('[Supabase] Tenant validation failed:', tenantId);
      return false;
    }

    return true;
  } catch (err) {
    console.error('[Supabase] Validation error:', err);
    return false;
  }
}

/**
 * Verifica el estado de la conexión a Supabase
 * Útil para debugging
 */
export async function checkConnection(): Promise<boolean> {
  try {
    const { error } = await supabase
      .from('tenants')
      .select('count')
      .limit(1);

    if (error) {
      console.error('[Supabase] Connection check failed:', error.message);
      return false;
    }

    console.log('[Supabase] Connection OK');
    return true;
  } catch (err) {
    console.error('[Supabase] Connection check exception:', err);
    return false;
  }
}
