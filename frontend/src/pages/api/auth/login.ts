import type { APIRoute } from 'astro';
import pg from 'pg';
import bcrypt from 'bcrypt';
import { v4 as uuidv4 } from 'uuid';

// ---------------------------------------------------------
// 1. CONFIGURACIÓN DE CONEXIÓN SEGURA
// ---------------------------------------------------------
// Usamos un objeto de configuración en lugar de string para evitar
// errores si la contraseña contiene caracteres especiales como '@'
const dbConfig = process.env.DATABASE_URL
  ? { connectionString: process.env.DATABASE_URL }
  : {
      host: process.env.DB_HOST,
      user: process.env.DB_USERNAME,
      password: process.env.DB_PASSWORD,
      database: process.env.DB_DATABASE,
      port: Number(process.env.DB_PORT) || 5432,
    };

// Validamos que exista configuración mínima
if (!dbConfig.connectionString && !dbConfig.host) {
  console.error("❌ [FATAL] No se encontraron variables de conexión a la Base de Datos.");
}

const pool = new pg.Pool({
  ...dbConfig,
  ssl: { rejectUnauthorized: false } // Necesario para Render/Supabase
});

// ---------------------------------------------------------
// 2. ENDPOINT DE LOGIN
// ---------------------------------------------------------
export const POST: APIRoute = async ({ request, cookies }) => {
  console.log("👉 [Login API] Procesando solicitud...");

  try {
    const { email, password } = await request.json();

    // Validación de campos
    if (!email || !password) {
      return new Response(JSON.stringify({ message: 'Faltan correo o contraseña' }), { status: 400 });
    }

    console.log(`👉 [Login API] Buscando: ${email}`);
    
    // Consulta para obtener usuario y tenant
    const userQuery = `
      SELECT u.*, t.id as tenant_id 
      FROM public.users u
      JOIN public.tenants t ON u.tenant_id = t.id
      WHERE u.email = $1 AND u.status = 'active'
      LIMIT 1
    `;
    
    const { rows } = await pool.query(userQuery, [email]);
    const user = rows[0];

    // Verificar si el usuario existe
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

    // Crear ID de sesión
    const sessionId = uuidv4();
    const expiresAt = new Date(Date.now() + 1000 * 60 * 60 * 24); // 24 horas

    // Guardar sesión en BD
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

    // Establecer Cookie segura
    cookies.set('workchain_session', sessionId, {
      path: '/',
      httpOnly: true,
      secure: true,
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