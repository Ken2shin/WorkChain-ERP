# WorkChain ERP - Setup Instructions

## Requisitos Previos
- Docker y Docker Compose instalados
- Git
- 8GB RAM mínimo disponible
- PostgreSQL 16 (via Docker)

## Pasos de Instalación

### 1. Clonar el repositorio
```bash
git clone <tu-repo-url>
cd workchain-erp
```

### 2. Configurar variables de entorno
```bash
cp laravel/.env.example laravel/.env
```

Editar `laravel/.env` con tus datos:
```env
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=workchain_erp
DB_USERNAME=workchain_user
DB_PASSWORD=tu_contraseña_segura

JWT_SECRET=tu_secreto_jwt_generado_aqui

FORCE_HTTPS=false (cambiar a true en producción)
```

### 3. Iniciar los contenedores
```bash
docker-compose up -d
```

Esperar 30 segundos a que PostgreSQL inicie.

### 4. Instalar dependencias
```bash
docker-compose exec laravel composer install
docker-compose exec laravel npm install
```

### 5. Generar clave de aplicación
```bash
docker-compose exec laravel php artisan key:generate
```

### 6. Ejecutar migraciones
```bash
docker-compose exec laravel php artisan migrate
```

### 7. Crear usuario administrador de prueba
```bash
docker-compose exec laravel php artisan db:seed --class=AdminSeeder
```

## Acceso a la Aplicación

- **URL**: http://localhost:8000
- **Usuario**: admin@workchain.local
- **Contraseña**: SecurePassword123!

## Comandos Útiles

### Ver logs
```bash
docker-compose logs -f laravel
docker-compose logs -f postgres
```

### Acceder a la consola Laravel
```bash
docker-compose exec laravel php artisan tinker
```

### Ejecutar migraciones específicas
```bash
docker-compose exec laravel php artisan migrate --step=1
```

### Resetear base de datos
```bash
docker-compose exec laravel php artisan migrate:fresh
```

### Detener los contenedores
```bash
docker-compose down
```

## Estructura de la Base de Datos

### Módulos Implementados:
- **Inventario**: Warehouses, Products, Inventory, Movements
- **Ventas**: Customers, Sales Orders, Invoices, Credit Notes
- **Compras**: Suppliers, Purchase Orders, Requisitions
- **RRHH**: Employees, Departments, Attendance, Leave Management
- **Proyectos**: Projects, Tasks, Comments, Attachments
- **Logística**: Vehicles, Drivers, Routes, Shipments
- **Finanzas**: Chart of Accounts, Journal Entries, Payments, Expenses
- **Documentos**: Document Management, Approvals, Access Logs

## Seguridad

### Características Implementadas:
- ✅ Multi-tenant architecture
- ✅ JWT authentication
- ✅ Role-based access control (RBAC)
- ✅ Rate limiting adaptativo
- ✅ Audit logging automático
- ✅ Security headers
- ✅ CSRF protection
- ✅ SQL injection prevention

### Middleware Aplicado:
- `SecurityHeaders` - Encabezados de seguridad
- `AdaptiveRateLimiting` - Rate limiting dinámico
- `EnsureMultiTenant` - Validación de tenant

## Desarrollo

### Crear nueva migración
```bash
docker-compose exec laravel php artisan make:migration create_table_name
```

### Crear nuevo modelo
```bash
docker-compose exec laravel php artisan make:model ModelName
```

### Crear nuevo controller
```bash
docker-compose exec laravel php artisan make:controller ControllerName
```

## Troubleshooting

### PostgreSQL no inicia
```bash
docker-compose down -v
docker-compose up -d postgres
# Esperar 30 segundos
docker-compose up -d
```

### Permisos en la carpeta storage
```bash
docker-compose exec laravel chown -R www-data:www-data storage bootstrap/cache
```

### Limpiar caché
```bash
docker-compose exec laravel php artisan cache:clear
docker-compose exec laravel php artisan config:clear
docker-compose exec laravel php artisan route:clear
```

## Documentación de API

Las rutas de la API se encuentran en `/laravel/routes/api.php`

### Autenticación
- `POST /api/auth/login` - Login
- `POST /api/auth/register` - Registro
- `POST /api/auth/refresh` - Refresh token
- `POST /api/auth/logout` - Logout

### Recursos principales (requieren autenticación)
- `GET /api/products` - Listar productos
- `GET /api/customers` - Listar clientes
- `GET /api/sales-orders` - Listar órdenes de ventas
- `GET /api/invoices` - Listar facturas

## Soporte

Para reportar problemas o solicitar features, crear un issue en GitHub.
