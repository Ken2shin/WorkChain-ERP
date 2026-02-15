# =========================
# ASTRO SERVER - PRODUCCIÓN
# =========================

FROM node:20-alpine AS builder

WORKDIR /app

COPY package.json package-lock.json* ./
RUN npm ci

COPY . .
RUN npm run build

# =========================
# RUNTIME
# =========================
FROM node:20-alpine

WORKDIR /app

ENV NODE_ENV=production

# Copiamos los archivos construidos
COPY --from=builder /app/dist ./dist
COPY --from=builder /app/node_modules ./node_modules
COPY --from=builder /app/package.json ./package.json

# Render inyecta la variable PORT.
EXPOSE 80

# 🔥 CORRECCIÓN FINAL Y SEGURA:
# Ejecutamos directamente el servidor generado por Astro.
# Usamos 'sh -c' para leer la variable ${PORT} de Render.
# Si Render asigna 10000, Astro escuchará en el 10000.
CMD ["sh", "-c", "node ./dist/server/entry.mjs --host 0.0.0.0 --port ${PORT:-80}"]