import type { APIRoute } from 'astro';
// 👇 CAMBIO CRÍTICO: Forma compatible de importar Postgres en Producción (Docker/Node)
import pkg from 'pg'; 
const { Pool } = pkg; 

import bcrypt from 'bcryptjs';
import { v4 as uuidv4 } from 'uuid';

// Configuración de conexión
const dbConfig = {
  host: process.env.DB_HOST,
  user: process.env.DB_USERNAME,
  password: process.env.DB_PASSWORD,
  database: process.env.DB_DATABASE,
  port: Number(process.env.DB_PORT) || 5432,
  // IMPORTANTE: rejectUnauthorized en false es vital para Render/Supabase
  ssl: { rejectUnauthorized: false } 
};

// Inicializamos el Pool
let pool: any;
try {
  pool = new Pool(dbConfig);
} catch (e) {
  console.error("🔥 Error FATAL al iniciar Pool de DB:", e);
}

export const POST: APIRoute = async ({ request, cookies }) => {
  console.log("👉 [Login API] Solicitud recibida (Protocolo seguro)");

  try {
    // 1. Check de Seguridad Inicial
    if (!pool) {
        throw new Error("El Pool de Base de Datos no se pudo iniciar. Revisa las variables de entorno.");
    }
    if (!process.env.DB_HOST) {
        throw new Error("Faltan variables de entorno (DB_HOST undefined).");
    }

    const { email, password } = await request.json();

    // 2. Validación de Inputs
    if (!email || !password) {
      return new Response(JSON.stringify({ message: 'Faltan datos' }), { status: 400 });
    }

    console.log(`👉 Intentando login para: ${email}`);

    // 3. Consulta a la Base de Datos
    const userQuery = `
      SELECT u.*, t.id as tenant_id 
      FROM public.users u
      JOIN public.tenants t ON u.tenant_id = t.id
      WHERE u.email = $1 AND u.status = 'active'
      LIMIT 1
    `;
    
    // Aquí es donde solía fallar si la importación estaba mal
    const { rows } = await pool.query(userQuery, [email]);
    const user = rows[0];

    // 4. Verificación de Usuario
    if (!user) {
      console.warn(`⚠️ Usuario no encontrado: ${email}`);
      return new Response(JSON.stringify({ message: 'Credenciales inválidas' }), { status: 401 });
    }

    // 5. Verificación de Password
    const isPasswordValid = await bcrypt.compare(password, user.password_hash);
    
    if (!isPasswordValid) {
      console.warn(`⚠️ Password incorrecto para: ${email}`);
      return new Response(JSON.stringify({ message: 'Credenciales inválidas' }), { status: 401 });
    }

    // 6. Generar Sesión
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

    // 7. Setear Cookie
    cookies.set('workchain_session', sessionId, {
      path: '/',
      httpOnly: true,
      secure: true, // Render usa HTTPS, así que esto es correcto
      sameSite: 'strict',
      expires: expiresAt
    });

    console.log("✅ Login Exitoso. Redirigiendo...");
    return new Response(JSON.stringify({ message: 'Login exitoso' }), { status: 200 });

  } catch (error: any) {
    // 🔥 LOG CRÍTICO: Esto aparecerá en el log de Render
    console.error('❌ ERROR 500 REAL:', error);
    
    return new Response(JSON.stringify({ 
      message: 'Error interno del servidor',
      // Este detalle te dirá en el navegador QUÉ pasó realmente
      detail: error.message || error.toString() 
    }), { status: 500 });
  }
};