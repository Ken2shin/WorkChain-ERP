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

COPY --from=builder /app/dist ./dist
COPY --from=builder /app/node_modules ./node_modules
COPY --from=builder /app/package.json ./package.json

EXPOSE 3002

# 🚫 SIN localhost
CMD ["npm", "run", "preview", "--", "--host", "0.0.0.0", "--port", "3002"]
