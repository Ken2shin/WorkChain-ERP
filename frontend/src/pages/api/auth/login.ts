import type { APIRoute } from 'astro';
import pg from 'pg';
import bcrypt from 'bcrypt';
import { v4 as uuidv4 } from 'uuid';

// Configuración de la base de datos (Usa tus variables de entorno de Render)
const pool = new pg.Pool({
  connectionString: process.env.DATABASE_URL,
  ssl: { rejectUnauthorized: false } // Requerido para Render/Supabase
});

export const POST: APIRoute = async ({ request, cookies }) => {
  try {
    const { email, password } = await request.json();

    // 1. Validación básica de entrada
    if (!email || !password) {
      return new Response(JSON.stringify({ message: 'Campos requeridos faltantes' }), { status: 400 });
    }

    // 2. Buscar usuario y su organización (Tenant)
    // Usamos el email_hash o el email directamente según tu esquema
    const userQuery = `
      SELECT u.*, t.id as tenant_id 
      FROM public.users u
      JOIN public.tenants t ON u.tenant_id = t.id
      WHERE u.email = $1 AND u.status = 'active'
      LIMIT 1
    `;
    
    const { rows } = await pool.query(userQuery, [email]);
    const user = rows[0];

    // 3. Verificación de seguridad (Protección contra ataques de enumeración)
    if (!user) {
      return new Response(JSON.stringify({ message: 'Credenciales inválidas' }), { status: 401 });
    }

    // 4. Validar contraseña
    // Comparamos el password recibido con el password_hash de la DB
    const isPasswordValid = await bcrypt.compare(password, user.password_hash);
    
    // Parche temporal: Si aún usas texto plano para pruebas, descomenta la siguiente línea:
    // const isPasswordValid = (password === user.password_hash);

    if (!isPasswordValid) {
      return new Response(JSON.stringify({ message: 'Credenciales inválidas' }), { status: 401 });
    }

    // 5. Crear Sesión en la tabla public.sessions
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
      Buffer.from(JSON.stringify({ role: user.role })), // Payload en bytea
      expiresAt
    ]);

    // 6. Establecer Cookie de sesión segura
    cookies.set('workchain_session', sessionId, {
      path: '/',
      httpOnly: true,
      secure: true,
      sameSite: 'strict',
      expires: expiresAt
    });

    return new Response(JSON.stringify({ message: 'Login exitoso' }), { status: 200 });

  } catch (error) {
    console.error('Error en Login API:', error);
    return new Response(JSON.stringify({ message: 'Error interno del servidor' }), { status: 500 });
  }
};