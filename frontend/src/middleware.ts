import { defineMiddleware } from 'astro:middleware';
import type { MiddlewareNext } from 'astro';

// Rutas públicas que no requieren autenticación
const PUBLIC_ROUTES = ['/login', '/register', '/password-reset', '/health'];

export const onRequest = defineMiddleware((context, next: MiddlewareNext) => {
  const { pathname } = context.url;
  
  // Permitir rutas públicas
  if (PUBLIC_ROUTES.some(route => pathname.startsWith(route))) {
    return next();
  }

  // Permitir archivos estáticos
  if (pathname.startsWith('/_astro/') || pathname.startsWith('/public/')) {
    return next();
  }

  // Verificar sesión desde cookie
  const sessionToken = context.cookies.get('session_token')?.value;

  // Si no hay sesión y es una ruta protegida, redirigir a login
  if (!sessionToken && !pathname.startsWith('/login')) {
    return context.redirect('/login');
  }

  return next();
});
