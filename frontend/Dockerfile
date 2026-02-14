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

# Exponemos 80 por convención
EXPOSE 80

# 🔥 CORRECCIÓN FINAL:
# Usamos sh -c para inyectar la variable $PORT en el comando de Astro
# Esto soluciona el error "Port scan timeout"
CMD ["sh", "-c", "npm run preview -- --host 0.0.0.0 --port ${PORT:-80}"]