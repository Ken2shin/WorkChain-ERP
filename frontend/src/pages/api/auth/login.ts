import type { APIRoute } from 'astro';
import pg from 'pg';
import bcrypt from 'bcrypt';
import { v4 as uuidv4 } from 'uuid';

// Configuración de la base de datos
// Nota: Se usará la variable que pasamos explícitamente en supervisor.conf
const pool = new pg.Pool({
  connectionString: process.env.DATABASE_URL,
  ssl: { rejectUnauthorized: false } // Requerido para Render/Supabase
});

export const POST: APIRoute = async ({ request, cookies }) => {
  console.log("👉 [Login API] Iniciando intento de login...");

  try {
    // 0. Validación de entorno crítica
    if (!process.env.DATABASE_URL) {
      console.error("❌ [Login API] ERROR FATAL: DATABASE_URL no está definida.");
      throw new Error("Error de configuración del servidor: Base de datos no vinculada.");
    }

    const { email, password } = await request.json();

    // 1. Validación básica de entrada
    if (!email || !password) {
      return new Response(JSON.stringify({ message: 'Campos requeridos faltantes' }), { status: 400 });
    }

    console.log(`👉 [Login API] Buscando usuario: ${email}`);

    // 2. Buscar usuario y su organización (Tenant)
    const userQuery = `
      SELECT u.*, t.id as tenant_id 
      FROM public.users u
      JOIN public.tenants t ON u.tenant_id = t.id
      WHERE u.email = $1 AND u.status = 'active'
      LIMIT 1
    `;
    
    const { rows } = await pool.query(userQuery, [email]);
    const user = rows[0];

    // 3. Verificación de seguridad
    if (!user) {
      console.warn(`⚠️ [Login API] Usuario no encontrado o inactivo: ${email}`);
      return new Response(JSON.stringify({ message: 'Credenciales inválidas' }), { status: 401 });
    }

    // 4. Validar contraseña
    const isPasswordValid = await bcrypt.compare(password, user.password_hash);
    
    if (!isPasswordValid) {
      console.warn(`⚠️ [Login API] Contraseña incorrecta para: ${email}`);
      return new Response(JSON.stringify({ message: 'Credenciales inválidas' }), { status: 401 });
    }

    // 5. Crear Sesión
    const sessionId = uuidv4();
    const expiresAt = new Date(Date.now() + 1000 * 60 * 60 * 24); // 24 horas

    const sessionQuery = `
      INSERT INTO public.sessions (id, user_id, tenant_id, ip_address, user_agent, payload, expires_at)
      VALUES ($1, $2, $3, $4, $5, $6, $7)
    `;

    await pool.query(sessionQuery, [
      sessionId,
      user.id,
      user.tenant_id,
      request.headers.get('x-forwarded-for') || '127.0.0.1',
      request.headers.get('user-agent') || 'unknown',
      Buffer.from(JSON.stringify({ role: user.role })),
      expiresAt
    ]);

    // 6. Establecer Cookie
    cookies.set('workchain_session', sessionId, {
      path: '/',
      httpOnly: true,
      secure: true,
      sameSite: 'strict',
      expires: expiresAt
    });

    console.log(`✅ [Login API] Login exitoso para: ${email}`);
    return new Response(JSON.stringify({ message: 'Login exitoso' }), { status: 200 });

  } catch (error: any) {
    // Este log aparecerá en la consola de Render si ocurre un error 500
    console.error('❌ [Login API] Excepción no controlada:', error);
    
    return new Response(JSON.stringify({ 
      message: 'Error interno del servidor',
      debug: error.message // Útil para desarrollo, quítalo en producción final
    }), { status: 500 });
  }
};