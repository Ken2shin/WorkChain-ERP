# WorkChain ERP - Sistema Empresarial Modular

WorkChain es un **SaaS ERP empresarial** diseñado para PYMES, con arquitectura modular, multi-tenant, y seguridad transversal automática.

## 🎯 Características Principales

### Módulos Implementados

#### 📦 **Inventario & Almacén**
- Gestión de múltiples almacenes
- Control de productos y SKU
- Kardex y movimientos de inventario
- Alertas de reorden automáticas
- Trazabilidad por lotes y vencimientos

#### 💼 **Ventas & Facturación**
- Gestión de clientes
- Cotizaciones y órdenes de venta
- Facturación digital
- Notas de crédito
- Control de cuentas por cobrar

#### 🛒 **Compras & Proveedores**
- Solicitudes de compra internas
- Flujos de aprobación
- Órdenes de compra
- Gestión de proveedores
- Recepción de mercancía

#### 👥 **Recursos Humanos**
- Registro de empleados
- Gestión de departamentos
- Control de asistencia
- Solicitudes de permisos
- Gestión de nómina (preparado)

#### 📅 **Proyectos & Tareas**
- Gestión de proyectos
- Kanban digital
- Asignación de tareas
- Adjuntos y comentarios
- Seguimiento de progreso

#### 🚚 **Logística & Envíos**
- Gestión de vehículos
- Información de choferes
- Rutas de distribución
- Seguimiento de envíos
- Tracking en tiempo real

#### 💰 **Finanzas & Contabilidad**
- Plan de cuentas
- Asientos de diario
- Registración de pagos
- Gestión de gastos
- Reportes financieros

#### 📄 **Gestión de Documentos**
- Almacenamiento centralizado
- Control de versionado
- Flujos de aprobación
- Auditoría de acceso
- Expiración automática

## 🔐 Seguridad Multi-Capas

### Arquitectura de Defensa

```
┌─────────────────────────────────────────────┐
│  1. PERÍMETRO INTELIGENTE (Edge)            │
│     - HTTPS/TLS                             │
│     - Normalización de requests             │
│     - Protection básica DDoS                │
└─────────────────────────────────────────────┘
                    ↓
┌─────────────────────────────────────────────┐
│  2. MALLA DE SEGURIDAD GLOBAL               │
│     - WAF distribuido                       │
│     - Rate limiting adaptativo              │
│     - Detección de anomalías                │
│     - Inspección de payloads                │
└─────────────────────────────────────────────┘
                    ↓
┌─────────────────────────────────────────────┐
│  3. IDENTIDAD & AUTENTICACIÓN               │
│     - JWT con expiración                    │
│     - Multi-tenant enforcement              │
│     - Tokens refresh automáticos            │
│     - 2FA ready                             │
└─────────────────────────────────────────────┘
                    ↓
┌─────────────────────────────────────────────┐
│  4. AUTORIZACIÓN & RBAC                     │
│     - Role-based access control             │
│     - Permisos granulares                   │
│     - Escalación de privilegios             │
│     - Auditoría de cambios                  │
└─────────────────────────────────────────────┘
                    ↓
┌─────────────────────────────────────────────┐
│  5. LÓGICA DE NEGOCIO LIMPIA                │
│     - Sin validaciones de seguridad         │
│     - Sin rate limiting interno             │
│     - Imposible de vulnerar desde adentro   │
└─────────────────────────────────────────────┘
```

### Características de Seguridad

✅ **Multi-tenant nativo** - Aislamiento total entre empresas  
✅ **JWT Authentication** - Tokens con expiración y refresh  
✅ **Rate Limiting Adaptativo** - Detecta y castiga anomalías  
✅ **Audit Logging** - Cada acción registrada y auditada  
✅ **RBAC Centralizado** - Roles y permisos dinámicos  
✅ **Security Headers** - CSP, X-Frame-Options, HSTS  
✅ **CSRF Protection** - Validación automática de tokens  
✅ **SQL Injection Prevention** - Prepared statements obligatorio  
✅ **Detección de Anomalías** - Comportamientos sospechosos  

## 🏗️ Arquitectura Técnica

### Stack Tecnológico

```
┌──────────────────┐
│  Frontend Layer  │
│ Livewire + Alpine.js │
└──────────────────┘
         ↓
┌──────────────────┐
│  API Layer       │
│  Laravel 11      │
│  RESTful Routes  │
└──────────────────┘
         ↓
┌──────────────────┐
│  Security Layer  │
│  Middleware Mesh │
│  Audit Service   │
└──────────────────┘
         ↓
┌──────────────────┐
│  Data Layer      │
│  PostgreSQL 16   │
│  Multi-tenant    │
└──────────────────┘
```

### Estructura de Carpetas

```
laravel/
├── app/
│   ├── Http/
│   │   ├── Controllers/Api/     (Controladores de API)
│   │   ├── Middleware/          (Middleware de seguridad)
│   │   └── Requests/            (Form Requests)
│   ├── Models/                  (Modelos Eloquent)
│   ├── Services/                (Lógica empresarial)
│   └── Jobs/                    (Trabajos en cola)
├── database/
│   ├── migrations/              (Migraciones)
│   └── seeders/                 (Datos de prueba)
├── routes/
│   ├── api.php                  (Rutas de API)
│   └── web.php                  (Rutas web)
└── storage/                     (Archivos y logs)
```

## 🚀 Instalación Rápida

### Requisitos
- Docker & Docker Compose
- 8GB RAM mínimo
- 10GB de espacio en disco

### Pasos

```bash
# 1. Clonar
git clone <repo-url>
cd workchain-erp

# 2. Configurar
cp laravel/.env.example laravel/.env
# Editar con tus valores

# 3. Iniciar
docker-compose up -d

# 4. Instalar dependencias
docker-compose exec laravel composer install
docker-compose exec laravel npm install

# 5. Migraciones
docker-compose exec laravel php artisan migrate

# 6. Acceder
# http://localhost:8000
```

Ver `SETUP.md` para instrucciones detalladas.

## 📊 Diagrama de Tablas

### Core
- `tenants` - Empresas multi-tenant
- `users` - Usuarios del sistema
- `security_audit_logs` - Auditoría de acciones

### Inventario
- `warehouses` - Almacenes
- `products` - Productos
- `inventory` - Stock por almacén
- `inventory_movements` - Movimientos de stock

### Ventas
- `customers` - Clientes
- `sales_orders` - Órdenes de venta
- `invoices` - Facturas
- `credit_notes` - Notas de crédito

### Compras
- `suppliers` - Proveedores
- `purchase_orders` - Órdenes de compra
- `purchase_requisitions` - Solicitudes de compra

### RRHH
- `departments` - Departamentos
- `employees` - Empleados
- `attendance` - Asistencia
- `leave_requests` - Solicitudes de permisos

### Proyectos
- `projects` - Proyectos
- `tasks` - Tareas
- `task_comments` - Comentarios

### Logística
- `vehicles` - Vehículos
- `drivers` - Choferes
- `shipments` - Envíos
- `shipment_tracking` - Rastreo

### Finanzas
- `chart_of_accounts` - Plan de cuentas
- `journal_entries` - Asientos
- `payments` - Pagos
- `expenses` - Gastos

### Documentos
- `documents` - Documentos
- `document_approvals` - Aprobaciones

## 🔌 API Endpoints

### Autenticación
```
POST   /api/v1/auth/login       - Login
POST   /api/v1/auth/register    - Registro
POST   /api/v1/auth/refresh     - Refresh token
POST   /api/v1/auth/logout      - Logout
GET    /api/v1/auth/me          - Datos del usuario
```

### Estructura de Respuesta

```json
{
  "success": true,
  "message": "Operación exitosa",
  "data": { }
}
```

Errores:
```json
{
  "success": false,
  "message": "Error message",
  "errors": { }
}
```

## 🛡️ Middleware de Seguridad

### `SecurityHeaders`
Añade headers de seguridad automáticamente:
- `X-Frame-Options: DENY`
- `X-Content-Type-Options: nosniff`
- `Content-Security-Policy`
- `Strict-Transport-Security`

### `AdaptiveRateLimiting`
Rate limiting inteligente que:
- Detecta comportamientos anómalos
- Aumenta límites para usuarios confiables
- Bloquea temporalmente a infractores
- Registra intentos sospechosos

### `EnsureMultiTenant`
Valida que cada request:
- Pertenezca a un tenant válido
- El usuario tenga acceso al tenant
- Las queries estén filtradas por tenant

## 📈 Escalabilidad

El sistema está preparado para:
- **Múltiples tenants** - Base de datos dedicada por tenant
- **Alta concurrencia** - Colas asincrónicas
- **Crecimiento de datos** - Índices optimizados
- **Distribución geográfica** - Replicación PostgreSQL

## 🔄 Flujos Principales

### Flujo de Venta
```
Cliente → Cotización → Orden → Factura → Pago → Envío
```

### Flujo de Compra
```
Requisición → Aprobación → Orden → Recepción → Pago
```

### Flujo de Inventario
```
Recepción → Stock → Movimientos → Kardex → Reportes
```

## 📝 Licencia

Propietario - WorkChain ERP

## 🤝 Soporte

Para reportar bugs o solicitar features:
- GitHub Issues
- Email: support@workchain.local

---

**Última actualización**: 2024  
**Versión**: 1.0.0-beta
#   W o r k C h a i n - E R P  
 