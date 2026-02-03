# 🚀 WorkChain ERP - Guía de Instalación

## Estructura de Archivos Generados

```
workchain-erp/
├── docker-compose.yml              # Configuración de Docker
├── Dockerfile                       # Imagen de Laravel
├── nginx.conf                       # Configuración de Nginx
├── README.md                        # Documentación principal
├── SETUP.md                         # Guía de configuración
├── INSTALLATION.md                  # Este archivo
├── .gitignore                       # Exclusiones de Git
│
└── laravel/
    ├── .env.example                 # Plantilla de variables
    ├── composer.json                # Dependencias PHP
    │
    ├── app/
    │   ├── Http/
    │   │   ├── Controllers/Api/
    │   │   │   ├── ApiController.php        # Base controller
    │   │   │   └── AuthController.php       # Autenticación
    │   │   └── Middleware/
    │   │       ├── SecurityHeaders.php      # Headers de seguridad
    │   │       ├── AdaptiveRateLimiting.php # Rate limiting
    │   │       └── EnsureMultiTenant.php    # Multi-tenant guard
    │   ├── Models/
    │   │   ├── BaseModel.php                # Modelo base
    │   │   ├── Tenant.php                   # Multi-tenant
    │   │   └── User.php                     # Usuarios
    │   └── Services/
    │       ├── AuditLogger.php              # Auditoría
    │       ├── JWTService.php               # JWT tokens
    │       └── PermissionGuard.php          # RBAC
    │
    ├── database/
    │   ├── migrations/
    │   │   ├── 2024_01_01_000000_create_tenants_table.php
    │   │   ├── 2024_01_01_000001_create_users_table.php
    │   │   ├── 2024_01_01_000002_create_security_audit_logs_table.php
    │   │   ├── 2024_01_01_000010_create_warehouse_inventory_table.php
    │   │   ├── 2024_01_01_000020_create_sales_module_table.php
    │   │   ├── 2024_01_01_000030_create_purchasing_module_table.php
    │   │   ├── 2024_01_01_000040_create_hr_module_table.php
    │   │   ├── 2024_01_01_000050_create_projects_module_table.php
    │   │   ├── 2024_01_01_000060_create_logistics_module_table.php
    │   │   ├── 2024_01_01_000070_create_finance_module_table.php
    │   │   └── 2024_01_01_000080_create_documents_module_table.php
    │   └── seeders/
    │       └── DatabaseSeeder.php           # Datos iniciales
    │
    └── routes/
        └── api.php                          # Rutas de API
```

## ⚙️ Instalación Paso a Paso

### 1️⃣ Requisitos Previos

```bash
# Verificar versiones
docker --version          # Docker 20.10+
docker-compose --version  # Docker Compose 1.29+
git --version            # Git 2.30+
```

### 2️⃣ Clonar y Configurar

```bash
# Clonar el repositorio
git clone <tu-url-repo>
cd workchain-erp

# Crear archivo .env
cp laravel/.env.example laravel/.env

# Generar JWT secret (guardar este valor)
php -r "echo 'JWT_SECRET=' . bin2hex(random_bytes(32)) . PHP_EOL;"
```

Editar `laravel/.env`:
```env
# Base de datos
DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=workchain_erp
DB_USERNAME=workchain_user
DB_PASSWORD=tu_contraseña_segura

# JWT
JWT_SECRET=tu_secreto_generado_aqui
JWT_ALGORITHM=HS256

# Seguridad
FORCE_HTTPS=false

# App
APP_NAME="WorkChain ERP"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000
```

### 3️⃣ Iniciar Contenedores

```bash
# Iniciar servicios
docker-compose up -d

# Verificar que estén corriendo
docker-compose ps

# Debería ver:
# workchain_postgres  postgres    Up
# workchain_app       laravel     Up
```

### 4️⃣ Instalar Dependencias

```bash
# PHP dependencies
docker-compose exec laravel composer install

# JavaScript dependencies
docker-compose exec laravel npm install

# Generar APP_KEY
docker-compose exec laravel php artisan key:generate
```

### 5️⃣ Configurar Base de Datos

```bash
# Ejecutar migraciones (crear tablas)
docker-compose exec laravel php artisan migrate

# Cargar datos iniciales
docker-compose exec laravel php artisan db:seed
```

### 6️⃣ Verificar Instalación

```bash
# Ver logs
docker-compose logs -f laravel

# Verificar health check
curl http://localhost:8000/api/health

# Respuesta esperada:
# {
#   "status": "OK",
#   "timestamp": "2024-...",
#   "service": "WorkChain ERP API"
# }
```

## 📱 Acceso a la Aplicación

| Campo | Valor |
|-------|-------|
| **URL** | http://localhost:8000 |
| **Usuario Admin** | admin@demo.local |
| **Contraseña** | Admin123!@# |
| **Tenant** | Demo Company (demo) |

Usuarios adicionales:
- **Manager**: manager@demo.local / Manager123!@#
- **User**: user@demo.local / User123!@#

## 🔄 Operaciones Comunes

### Ver Logs

```bash
# Laravel
docker-compose logs -f laravel

# PostgreSQL
docker-compose logs -f postgres

# Nginx
docker-compose logs -f nginx
```

### Ejecutar Artisan Commands

```bash
# Listar todas las rutas
docker-compose exec laravel php artisan route:list

# Limpiar caché
docker-compose exec laravel php artisan cache:clear
docker-compose exec laravel php artisan config:clear

# Acceder a Tinker (REPL interactivo)
docker-compose exec laravel php artisan tinker
```

### Resetear Base de Datos

```bash
# Eliminar todo y recrear
docker-compose exec laravel php artisan migrate:fresh

# Con datos iniciales
docker-compose exec laravel php artisan migrate:fresh --seed
```

### Crear Nueva Migración

```bash
docker-compose exec laravel php artisan make:migration create_new_table_name
```

## 🔐 Seguridad Post-Instalación

### ✅ Checklist de Seguridad

- [ ] Cambiar `APP_KEY` en .env
- [ ] Cambiar `JWT_SECRET` en .env
- [ ] Cambiar contraseña de usuario admin
- [ ] Cambiar contraseña de PostgreSQL
- [ ] Activar `FORCE_HTTPS=true` en producción
- [ ] Configurar dominio real en lugar de localhost
- [ ] Revisar permisos de archivos: `storage/` y `bootstrap/cache/`
- [ ] Configurar backups automáticos de PostgreSQL
- [ ] Implementar 2FA en cuentas administrativas

### Cambiar Contraseña de Admin

```bash
docker-compose exec laravel php artisan tinker

# Dentro de Tinker:
$user = User::first();
$user->password = Hash::make('Nueva_Contraseña_Segura');
$user->save();
exit
```

## 🐛 Troubleshooting

### Error: "Connection refused"

```bash
# Verificar que PostgreSQL está corriendo
docker-compose ps

# Reiniciar PostgreSQL
docker-compose restart postgres

# Esperar 30 segundos e intentar de nuevo
```

### Error: "SQLSTATE[HY000] [2002] No such file or directory"

```bash
# Borrar volúmenes y recrear
docker-compose down -v
docker-compose up -d postgres

# Esperar a que inicie PostgreSQL
sleep 30

# Reiniciar Laravel
docker-compose up -d laravel
```

### Error: "Class not found" después de migration

```bash
# Regenerar autoloader
docker-compose exec laravel composer dump-autoload

# Limpiar caché
docker-compose exec laravel php artisan cache:clear
```

### Permisos en Laravel

```bash
# Asignar permisos correctos
docker-compose exec laravel chown -R www-data:www-data storage
docker-compose exec laravel chown -R www-data:www-data bootstrap/cache
docker-compose exec laravel chmod -R 775 storage
docker-compose exec laravel chmod -R 775 bootstrap/cache
```

## 📊 Verificar Migraciones

```bash
# Ver migraciones ejecutadas
docker-compose exec laravel php artisan migrate:status

# Rollback de una migración
docker-compose exec laravel php artisan migrate:rollback --step=1

# Ver todas las migraciones pendientes
docker-compose exec laravel php artisan migrate:status
```

## 🌐 API Endpoints Básicos

### Health Check
```bash
GET http://localhost:8000/api/health
```

### Login
```bash
POST http://localhost:8000/api/v1/auth/login
Content-Type: application/json

{
  "email": "admin@demo.local",
  "password": "Admin123!@#",
  "tenant_id": 1
}
```

### Obtener Datos del Usuario
```bash
GET http://localhost:8000/api/v1/auth/me
Authorization: Bearer <tu-token>
```

## 📚 Documentación Relacionada

- [README.md](./README.md) - Descripción general del proyecto
- [SETUP.md](./SETUP.md) - Configuración avanzada
- [API Documentation](./API.md) - Endpoints de API

## 🆘 Soporte

Cualquier problema durante la instalación:

1. Revisar los logs: `docker-compose logs -f`
2. Verificar que los puertos (8000, 5432) estén disponibles
3. Consultar la sección Troubleshooting de este documento
4. Reportar el issue en GitHub

---

**Última actualización**: 2024  
**Versión**: 1.0.0-beta  
**Tiempo estimado de instalación**: 10-15 minutos
