# 📡 WorkChain ERP - Documentación de API

## Base URL

```
http://localhost:8000/api/v1
```

## Autenticación

Todas las rutas (excepto login y register) requieren el header:

```
Authorization: Bearer <tu-token-jwt>
X-Tenant-ID: <tenant-id>
```

## 🔐 Endpoints de Autenticación

### Login
Obtener tokens de acceso y refresh.

```http
POST /auth/login
Content-Type: application/json

{
  "email": "admin@demo.local",
  "password": "Admin123!@#",
  "tenant_id": 1
}
```

**Respuesta Exitosa (200)**
```json
{
  "success": true,
  "message": "Login successful",
  "data": {
    "user": {
      "id": 1,
      "name": "Administrador",
      "email": "admin@demo.local",
      "role": "admin",
      "tenant_id": 1
    },
    "access_token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
    "refresh_token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
    "expires_in": 86400
  }
}
```

**Error (401)**
```json
{
  "success": false,
  "message": "Invalid credentials"
}
```

---

### Register
Crear nuevo usuario.

```http
POST /auth/register
Content-Type: application/json

{
  "name": "Nuevo Usuario",
  "email": "newuser@example.com",
  "password": "SecurePassword123!",
  "password_confirmation": "SecurePassword123!",
  "tenant_code": "demo"
}
```

**Respuesta Exitosa (201)**
```json
{
  "success": true,
  "message": "User registered",
  "data": {
    "user_id": 2,
    "message": "Registration successful. Waiting for admin approval."
  }
}
```

---

### Refresh Token
Obtener nuevo access token.

```http
POST /auth/refresh
Content-Type: application/json

{
  "refresh_token": "eyJ0eXAiOiJKV1QiLCJhbGc..."
}
```

**Respuesta Exitosa (200)**
```json
{
  "success": true,
  "message": "Token refreshed",
  "data": {
    "access_token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
    "expires_in": 86400
  }
}
```

---

### Get Current User
Obtener información del usuario autenticado.

```http
GET /auth/me
Authorization: Bearer <token>
X-Tenant-ID: 1
```

**Respuesta Exitosa (200)**
```json
{
  "success": true,
  "message": "Success",
  "data": {
    "id": 1,
    "name": "Administrador",
    "email": "admin@demo.local",
    "role": "admin",
    "tenant_id": 1,
    "permissions": ["*"]
  }
}
```

---

### Logout
Cerrar sesión.

```http
POST /auth/logout
Authorization: Bearer <token>
X-Tenant-ID: 1
```

**Respuesta Exitosa (200)**
```json
{
  "success": true,
  "message": "Logout successful",
  "data": null
}
```

---

## 📦 Estructura de Respuestas

### Respuesta Exitosa

```json
{
  "success": true,
  "message": "Descripción del resultado",
  "data": {
    "id": 1,
    "name": "Ejemplo"
  }
}
```

### Respuesta con Paginación

```json
{
  "success": true,
  "data": [
    { "id": 1, "name": "Item 1" },
    { "id": 2, "name": "Item 2" }
  ],
  "pagination": {
    "total": 50,
    "page": 1,
    "per_page": 20,
    "last_page": 3
  }
}
```

### Respuesta de Error

```json
{
  "success": false,
  "message": "Descripción del error",
  "errors": {
    "email": ["El email es requerido"],
    "password": ["La contraseña debe tener mínimo 8 caracteres"]
  }
}
```

---

## ⚠️ Códigos de Estado HTTP

| Código | Significado | Uso |
|--------|-------------|-----|
| **200** | OK | Solicitud exitosa |
| **201** | Created | Recurso creado |
| **400** | Bad Request | Datos inválidos |
| **401** | Unauthorized | No autenticado |
| **403** | Forbidden | Sin permisos |
| **404** | Not Found | Recurso no encontrado |
| **422** | Validation Failed | Validación fallida |
| **429** | Too Many Requests | Rate limit excedido |
| **500** | Server Error | Error del servidor |

---

## 🔑 Roles y Permisos

### Roles Disponibles

```
admin       → Acceso total a todo
manager     → Acceso a módulos operativos
user        → Acceso limitado a datos propios
guest       → Acceso de solo lectura
```

### Permisos Base

```
view_reports
manage_users
manage_departments
approve_expenses
manage_projects
view_own_data
submit_expense
view_projects
submit_timesheet
```

---

## 📋 Ejemplos de Uso

### Ejemplo 1: Login y Acceso a Datos

```bash
#!/bin/bash

# 1. Login
LOGIN=$(curl -s -X POST http://localhost:8000/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "admin@demo.local",
    "password": "Admin123!@#",
    "tenant_id": 1
  }')

# 2. Extraer token
TOKEN=$(echo $LOGIN | jq -r '.data.access_token')

# 3. Usar token
curl -X GET http://localhost:8000/api/v1/auth/me \
  -H "Authorization: Bearer $TOKEN" \
  -H "X-Tenant-ID: 1"
```

### Ejemplo 2: Con cURL

```bash
curl -X POST http://localhost:8000/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -H "X-Tenant-ID: 1" \
  -d '{
    "email": "admin@demo.local",
    "password": "Admin123!@#",
    "tenant_id": 1
  }'
```

### Ejemplo 3: Con Python

```python
import requests
import json

BASE_URL = "http://localhost:8000/api/v1"

# Login
response = requests.post(
    f"{BASE_URL}/auth/login",
    json={
        "email": "admin@demo.local",
        "password": "Admin123!@#",
        "tenant_id": 1
    }
)

data = response.json()
token = data['data']['access_token']

# Usar token
headers = {
    "Authorization": f"Bearer {token}",
    "X-Tenant-ID": "1"
}

me = requests.get(
    f"{BASE_URL}/auth/me",
    headers=headers
)

print(me.json())
```

### Ejemplo 4: Con JavaScript/Fetch

```javascript
const BASE_URL = "http://localhost:8000/api/v1";

// Login
const loginResponse = await fetch(`${BASE_URL}/auth/login`, {
  method: "POST",
  headers: {
    "Content-Type": "application/json"
  },
  body: JSON.stringify({
    email: "admin@demo.local",
    password: "Admin123!@#",
    tenant_id: 1
  })
});

const loginData = await loginResponse.json();
const token = loginData.data.access_token;

// Usar token
const meResponse = await fetch(`${BASE_URL}/auth/me`, {
  headers: {
    "Authorization": `Bearer ${token}`,
    "X-Tenant-ID": "1"
  }
});

const userData = await meResponse.json();
console.log(userData);
```

---

## 🔒 Seguridad de API

### Headers Requeridos

```
Authorization: Bearer <jwt-token>      # Token de autenticación
X-Tenant-ID: <tenant-id>              # ID del tenant
Content-Type: application/json         # Tipo de contenido
```

### Rate Limiting

El sistema implementa rate limiting adaptativo:

- **Usuarios normales**: 60 requests/minuto
- **Comportamiento anómalo**: 10 requests/minuto (más restrictivo)
- **Bloqueo temporal**: 15 minutos después de exceder límite

Headers de respuesta:
```
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 45
```

### CORS

Configurado para funcionar con:
```
localhost:8000
localhost:3000
```

---

## 🚀 Próximos Endpoints (Fase 2)

Los siguientes endpoints están listos para implementación:

### Inventario
```
GET    /products
POST   /products
PUT    /products/:id
DELETE /products/:id

GET    /warehouses
GET    /warehouses/:id/inventory
```

### Ventas
```
GET    /customers
POST   /sales-orders
GET    /invoices
POST   /invoices/:id/payments
```

### Compras
```
GET    /suppliers
POST   /purchase-orders
GET    /purchase-orders/:id/receipt
```

### RRHH
```
GET    /employees
GET    /departments
POST   /leave-requests
GET    /attendance
```

### Proyectos
```
GET    /projects
POST   /tasks
GET    /tasks/:id/comments
```

### Logística
```
GET    /shipments
POST   /shipments/:id/tracking
GET    /vehicles
```

### Finanzas
```
GET    /expenses
POST   /expenses/:id/approve
GET    /financial-reports
POST   /payments
```

### Documentos
```
GET    /documents
POST   /documents
POST   /documents/:id/approve
GET    /documents/:id/versions
```

---

## 📞 Contacto y Soporte

Para reportar errores en la API:
1. Incluir método HTTP (GET, POST, etc)
2. URL del endpoint
3. Headers enviados
4. Body de la solicitud
5. Respuesta recibida
6. Logs del servidor

---

**Última actualización**: 2024  
**Versión API**: v1  
**Estado**: Beta
