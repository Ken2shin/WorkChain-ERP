import type { APIRoute } from 'astro';
import pg from 'pg';
import bcrypt from 'bcryptjs'; // 👈 CAMBIO CRÍTICO: Usamos la versión JS para evitar crasheos en Docker
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
    // Si esto falla, es culpa de supervisor.conf
    if (!process.env.DB_HOST) {
        throw new Error(`CRÍTICO: Faltan variables de entorno. DB_HOST es undefined.`);
    }

    const { email, password } = await request.json();

    // 2. Validación básica
    if (!email || !password) {
      return new Response(JSON.stringify({ message: 'Faltan datos' }), { status: 400 });
    }

    console.log(`👉 Buscando usuario: ${email}`);

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
      console.warn("⚠️ Usuario no encontrado en BD");
      return new Response(JSON.stringify({ message: 'Credenciales inválidas' }), { status: 401 });
    }

    // 4. Validar contraseña con bcryptjs
    // Esto ya no dará error 500 por incompatibilidad de binarios
    const isPasswordValid = await bcrypt.compare(password, user.password_hash);
    
    if (!isPasswordValid) {
      console.warn("⚠️ Contraseña incorrecta");
      return new Response(JSON.stringify({ message: 'Credenciales inválidas' }), { status: 401 });
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

    console.log("✅ Login Exitoso");
    return new Response(JSON.stringify({ message: 'Login exitoso' }), { status: 200 });

  } catch (error: any) {
    // 🔥 LOG DE ERRORES:
    // Ahora que arreglaste supervisor.conf, esto saldrá en tu pantalla de Render.
    console.error('❌ Error Login CRÍTICO:', error);
    
    return new Response(JSON.stringify({ 
      message: 'Error interno del servidor',
      detail: error.message, // Esto te dirá el error exacto en el navegador (Network tab)
      stack: error.stack 
    }), { status: 500 });
  }
};