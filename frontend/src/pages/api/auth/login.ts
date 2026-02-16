import type { APIRoute } from 'astro';
import pg from 'pg';
import bcrypt from 'bcrypt';
import { v4 as uuidv4 } from 'uuid';

// Configuración robusta para la base de datos
const dbConfig = {
  host: process.env.DB_HOST,
  user: process.env.DB_USERNAME,
  password: process.env.DB_PASSWORD,
  database: process.env.DB_DATABASE,
  port: Number(process.env.DB_PORT) || 5432,
  ssl: { rejectUnauthorized: false } // Obligatorio para Render/Supabase
};

// Crear el pool fuera del handler para reutilizar conexiones
const pool = new pg.Pool(dbConfig);

export const POST: APIRoute = async ({ request, cookies }) => {
  console.log("👉 [Login API] Iniciando solicitud...");

  try {
    // 1. Validar que las variables de entorno llegaron bien
    if (!process.env.DB_HOST) {
        throw new Error(`Faltan variables de entorno. DB_HOST es: ${process.env.DB_HOST}`);
    }

    const { email, password } = await request.json();

    // 2. Validación básica
    if (!email || !password) {
      return new Response(JSON.stringify({ message: 'Faltan datos' }), { status: 400 });
    }

    // 3. Buscar usuario
    const userQuery = `
      SELECT u.*, t.id as tenant_id 
      FROM public.users u
      JOIN public.tenants t ON u.tenant_id = t.id
      WHERE u.email = $1 AND u.status = 'active'
      LIMIT 1
    `;
    
    // Intentamos la consulta
    const { rows } = await pool.query(userQuery, [email]);
    const user = rows[0];

    if (!user) {
      return new Response(JSON.stringify({ message: 'Usuario no encontrado' }), { status: 401 });
    }

    // 4. Validar contraseña con bcrypt
    const isPasswordValid = await bcrypt.compare(password, user.password_hash);
    
    if (!isPasswordValid) {
      return new Response(JSON.stringify({ message: 'Contraseña incorrecta' }), { status: 401 });
    }

    // 5. Crear Sesión
    const sessionId = uuidv4();
    const expiresAt = new Date(Date.now() + 1000 * 60 * 60 * 24); 

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

    // 6. Cookie
    cookies.set('workchain_session', sessionId, {
      path: '/',
      httpOnly: true,
      secure: true,
      sameSite: 'strict',
      expires: expiresAt
    });

    return new Response(JSON.stringify({ message: 'Login exitoso' }), { status: 200 });

  } catch (error: any) {
    // 🔥 ESTO ES LO QUE NECESITAMOS AHORA MISMO:
    // Devolvemos el error real al navegador para que puedas leerlo.
    // (En producción final quitarías el 'detail', pero para debug es vital).
    console.error('❌ Error Login:', error);
    
    return new Response(JSON.stringify({ 
      message: 'Error interno del servidor (Debug Mode)',
      detail: error.message,
      stack: error.stack 
    }), { status: 500 });
  }
};