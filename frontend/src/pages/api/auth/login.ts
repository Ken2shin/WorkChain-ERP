import type { APIRoute } from 'astro';
import pkg from 'pg';
import bcrypt from 'bcryptjs';
import { v4 as uuidv4 } from 'uuid';

const { Pool } = pkg;

// ✅ Inicialización segura del Pool (singleton)
let pool: any;

function getPool() {
  if (!pool) {
    pool = new Pool({
      host: import.meta.env.DB_HOST,
      user: import.meta.env.DB_USERNAME,
      password: import.meta.env.DB_PASSWORD,
      database: import.meta.env.DB_DATABASE,
      port: Number(import.meta.env.DB_PORT) || 5432,
      ssl: { rejectUnauthorized: false }
    });
  }
  return pool;
}

export const POST: APIRoute = async ({ request, cookies }) => {
  try {
    const { email, password } = await request.json();

    if (!email || !password) {
      return new Response(
        JSON.stringify({ message: 'Faltan datos' }),
        { status: 400, headers: { 'Content-Type': 'application/json' } }
      );
    }

    const db = getPool();

    const userQuery = `
      SELECT u.*, t.id AS tenant_id
      FROM public.users u
      JOIN public.tenants t ON u.tenant_id = t.id
      WHERE u.email = $1 AND u.status = 'active'
      LIMIT 1
    `;

    const { rows } = await db.query(userQuery, [email]);
    const user = rows[0];

    if (!user) {
      return new Response(
        JSON.stringify({ message: 'Credenciales inválidas' }),
        { status: 401, headers: { 'Content-Type': 'application/json' } }
      );
    }

    const isPasswordValid = await bcrypt.compare(password, user.password_hash);

    if (!isPasswordValid) {
      return new Response(
        JSON.stringify({ message: 'Credenciales inválidas' }),
        { status: 401, headers: { 'Content-Type': 'application/json' } }
      );
    }

    // ✅ Crear sesión
    const sessionId = uuidv4();
    const expiresAt = new Date(Date.now() + 1000 * 60 * 60 * 24);

    const sessionQuery = `
      INSERT INTO public.sessions
      (id, user_id, tenant_id, ip_address, user_agent, payload, expires_at)
      VALUES ($1, $2, $3, $4, $5, $6, $7)
    `;

    await db.query(sessionQuery, [
      sessionId,
      user.id,
      user.tenant_id,
      request.headers.get('x-forwarded-for') || '127.0.0.1',
      request.headers.get('user-agent') || 'unknown',
      Buffer.from(JSON.stringify({ role: user.role })),
      expiresAt
    ]);

    // ✅ Cookie segura (Render HTTPS)
    cookies.set('workchain_session', sessionId, {
      path: '/',
      httpOnly: true,
      secure: true,
      sameSite: 'strict',
      expires: expiresAt
    });

    return new Response(
      JSON.stringify({ message: 'Login exitoso' }),
      { status: 200, headers: { 'Content-Type': 'application/json' } }
    );

  } catch (error: any) {
    console.error('❌ ERROR LOGIN:', error);

    return new Response(
      JSON.stringify({
        message: 'Error interno del servidor',
        detail: error?.message || String(error)
      }),
      { status: 500, headers: { 'Content-Type': 'application/json' } }
    );
  }
};
