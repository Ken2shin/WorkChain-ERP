import type { APIRoute } from 'astro';
import pg from 'pg';
import bcrypt from 'bcrypt';
import { v4 as uuidv4 } from 'uuid';

// ---------------------------------------------------------
// 1. CONSTRUCCIÓN ROBUSTA DE LA CONEXIÓN A BD
// ---------------------------------------------------------
const getConnectionString = () => {
  // Si Render nos da la URL completa, la usamos
  if (process.env.DATABASE_URL) {
    return process.env.DATABASE_URL;
  }

  // Si no, la construimos con tus variables desglosadas
  const host = process.env.DB_HOST;
  const user = process.env.DB_USERNAME;
  const pass = process.env.DB_PASSWORD;
  const name = process.env.DB_DATABASE;
  const port = process.env.DB_PORT || 5432;

  if (host && user && pass && name) {
    return `postgres://${user}:${pass}@${host}:${port}/${name}`;
  }
  
  return null;
};

const connectionString = getConnectionString();

// Validamos antes de crear el Pool para que el error sea claro en los logs
if (!connectionString) {
  console.error("❌ [FATAL] No se encontraron variables de conexión a la Base de Datos.");
}

const pool = new pg.Pool({
  connectionString: connectionString || undefined,
  ssl: { rejectUnauthorized: false } // Necesario para Supabase/Render
});

// ---------------------------------------------------------
// 2. ENDPOINT DE LOGIN
// ---------------------------------------------------------
export const POST: APIRoute = async ({ request, cookies }) => {
  console.log("👉 [Login API] Procesando solicitud...");

  try {
    if (!connectionString) {
      throw new Error("Error de configuración: Base de datos no conectada.");
    }

    const { email, password } = await request.json();

    // Validación de campos
    if (!email || !password) {
      return new Response(JSON.stringify({ message: 'Faltan correo o contraseña' }), { status: 400 });
    }

    // Buscar usuario y tenant
    console.log(`👉 [Login API] Buscando: ${email}`);
    const userQuery = `
      SELECT u.*, t.id as tenant_id 
      FROM public.users u
      JOIN public.tenants t ON u.tenant_id = t.id
      WHERE u.email = $1 AND u.status = 'active'
      LIMIT 1
    `;
    
    const { rows } = await pool.query(userQuery, [email]);
    const user = rows[0];

    // Verificar usuario
    if (!user) {
      console.warn(`⚠️ Usuario no encontrado: ${email}`);
      return new Response(JSON.stringify({ message: 'Credenciales inválidas' }), { status: 401 });
    }

    // Validar contraseña
    const isPasswordValid = await bcrypt.compare(password, user.password_hash);
    
    if (!isPasswordValid) {
      console.warn(`⚠️ Contraseña incorrecta: ${email}`);
      return new Response(JSON.stringify({ message: 'Credenciales inválidas' }), { status: 401 });
    }

    // Crear Sesión
    const sessionId = uuidv4();
    // 24 horas de expiración
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

    // Cookie segura
    cookies.set('workchain_session', sessionId, {
      path: '/',
      httpOnly: true,
      secure: true, // Render usa HTTPS
      sameSite: 'strict',
      expires: expiresAt
    });

    console.log("✅ Login exitoso");
    return new Response(JSON.stringify({ message: 'Bienvenido a WorkChain' }), { status: 200 });

  } catch (error: any) {
    console.error('❌ [Login API Error]:', error);
    return new Response(JSON.stringify({ 
      message: 'Error interno del servidor',
      detail: error.message 
    }), { status: 500 });
  }
};