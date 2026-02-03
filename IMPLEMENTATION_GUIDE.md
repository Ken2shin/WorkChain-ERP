# WorkChain ERP - Implementation Guide

## Prerequisites

- Docker & Docker Compose
- Git
- Minimum 4GB RAM available
- 2+ CPU cores
- Minimum 2GB free disk space

## Quick Start (5 minutes)

### 1. Clone and Setup

```bash
git clone <repository>
cd workchain-erp

# Copy environment file
cp laravel/.env.example laravel/.env

# Generate or set JWT_SECRET
export JWT_SECRET=$(openssl rand -hex 32)
```

### 2. Start Services

```bash
# Build and start all containers
docker-compose up -d

# Wait for services to be healthy
docker-compose ps
```

### 3. Initialize Database

```bash
# Install PHP dependencies
docker-compose exec laravel composer install

# Run migrations
docker-compose exec laravel php artisan migrate

# Seed demo data
docker-compose exec laravel php artisan db:seed
```

### 4. Access System

```
Frontend:  http://localhost:3002
Backend:   http://localhost:8000
Gateway:   https://localhost (self-signed cert)
Docs:      http://localhost:8000/docs
```

## Service Status Checks

```bash
# Check all services
docker-compose ps

# View logs
docker-compose logs -f laravel
docker-compose logs -f crypto-service
docker-compose logs -f anomaly-detector

# Check service health
curl http://localhost:3000/health     # Crypto
curl http://localhost:3001/health     # Anomaly Detector
curl http://localhost:8000/health     # Laravel
curl http://localhost:3002/health     # Frontend
```

## Default Credentials

**Admin Account**
```
Email:    admin@workchain.local
Password: Admin123!@#
Tenant:   default
```

**Demo Account**
```
Email:    demo@workchain.local
Password: Demo123!@#
Tenant:   demo
```

## Project Structure

```
workchain-erp/
├── edge-gateway/                # Caddy reverse proxy config
│   └── Caddyfile
├── laravel/                     # Main backend (Laravel)
│   ├── app/
│   │   ├── Http/
│   │   │   ├── Middleware/      # Security middleware
│   │   │   │   ├── NanoWAF.php
│   │   │   │   ├── NanoRateLimiting.php
│   │   │   │   └── EnsureMultiTenant.php
│   │   │   └── Controllers/
│   │   ├── Models/
│   │   └── Services/            # Business logic
│   ├── database/
│   │   ├── migrations/          # Schema changes
│   │   └── seeders/
│   ├── routes/
│   │   └── api.php
│   └── .env
├── frontend/                    # Astro + Alpine.js
│   ├── src/
│   │   ├── layouts/
│   │   ├── pages/
│   │   ├── components/
│   │   └── styles/
│   └── astro.config.mjs
├── services/
│   ├── go/
│   │   └── crypto-service/      # JWT, encryption, hashing
│   ├── rust/
│   │   └── anomaly-detector/    # Behavioral analysis
│   ├── cpp/                     # Reserved for crypto acceleration
│   ├── csharp/                  # Reserved for Windows integration
│   └── swift/                   # Reserved for iOS backend
├── docker-compose.yml           # Service orchestration
├── Dockerfile                   # Laravel container
├── ARCHITECTURE_COMPLETE.md     # Architecture doc
└── IMPLEMENTATION_GUIDE.md      # This file
```

## Security Architecture Implementation

### 1. Edge Gateway (Caddy)
Located in: `/edge-gateway/Caddyfile`

**What it does:**
- TLS termination
- DDoS mitigation
- Request normalization
- Routing to backend

**How to modify:**
```bash
# Edit configuration
nano edge-gateway/Caddyfile

# Reload (without restart)
docker-compose exec gateway caddy reload --config /etc/caddy/Caddyfile
```

### 2. Security Mesh (Laravel Middleware)
Located in: `/laravel/app/Http/Middleware/`

**Middleware Stack (Execution Order):**
1. `NanoWAF` - OWASP pattern detection
2. `NanoRateLimiting` - Adaptive rate limiting
3. `EnsureMultiTenant` - Tenant context validation

**How to add custom rules:**

Edit `NanoWAF.php` and add patterns:
```php
private const OWASP_PATTERNS = [
    'custom' => [
        "/your-pattern/i",
    ],
];
```

### 3. Go Cryptographic Service
Located in: `/services/go/crypto-service/`

**Endpoints:**
```
POST /hash              # Hash passwords
POST /verify-hash       # Verify hashes
POST /encrypt           # Encrypt data
POST /decrypt           # Decrypt data
POST /generate-jwt      # Create JWT tokens
POST /verify-jwt        # Validate JWT tokens
```

**Example Usage (from Laravel):**
```php
$response = Http::post('http://crypto-service:3000/hash', [
    'data' => $password,
    'algorithm' => 'argon2',
]);

$hash = $response['hash'];
```

### 4. Rust Anomaly Detection Service
Located in: `/services/rust/anomaly-detector/`

**Endpoints:**
```
POST /detect            # Analyze request for anomalies
POST /baseline          # Update user baseline
POST /reset             # Reset user profile
```

**Anomaly Scoring:**
- < 2.0: Low risk
- 2.0-4.0: Medium risk
- 4.0-7.0: High risk
- > 7.0: Critical risk

### 5. Frontend (Astro + Alpine.js)
Located in: `/frontend/`

**Architecture:**
- Pages: `/src/pages/`
- Components: `/src/components/`
- Layouts: `/src/layouts/`
- Styles: `/src/styles/`

**Adding a new page:**
```bash
# Create page component
touch frontend/src/pages/new-feature.astro

# Edit it
nano frontend/src/pages/new-feature.astro
```

### 6. Database Schema
Located in: `/laravel/database/migrations/`

**Multi-tenant enforcement:**
```php
// All tables must have tenant_id
$table->uuid('tenant_id')->index();
$table->foreign('tenant_id')->references('id')->on('tenants');
```

## Configuration

### Environment Variables

**Laravel (.env)**
```bash
# Database
DB_HOST=postgres
DB_DATABASE=workchain_erp
DB_USERNAME=workchain_user
DB_PASSWORD=secure_password_change_me

# JWT
JWT_SECRET=your-secure-secret-here

# Services
CRYPTO_SERVICE_URL=http://crypto-service:3000
ANOMALY_SERVICE_URL=http://anomaly-detector:3001

# Security
RATE_LIMIT_DEFAULT=60
RATE_LIMIT_ADMIN=1000
```

**Frontend (.env)**
```bash
PUBLIC_API_BASE=http://localhost:8000/api
```

## Development Workflow

### 1. Making Database Changes

```bash
# Create migration
docker-compose exec laravel php artisan make:migration migration_name

# Edit migration file
nano laravel/database/migrations/xxxx_migration_name.php

# Run migration
docker-compose exec laravel php artisan migrate

# Rollback if needed
docker-compose exec laravel php artisan migrate:rollback
```

### 2. Adding New API Endpoints

```php
// In laravel/routes/api.php
Route::post('/new-endpoint', [NewController::class, 'store'])
    ->middleware(['auth:api', 'can:create.resource']);
```

### 3. Creating New Frontend Pages

```bash
# Create Astro page
touch frontend/src/pages/new-page.astro

# Add Alpine.js interactivity
nano frontend/src/pages/new-page.astro
```

### 4. Extending Security Rules

**Add WAF pattern:**
```php
// In NanoWAF.php
'custom_threat' => [
    "/suspicious-pattern/i",
],
```

**Add rate limit rule:**
```php
// In NanoRateLimiting.php
private const BASE_LIMITS = [
    'unauthenticated' => 60,
    'authenticated' => 300,
    'premium' => 5000, // New tier
];
```

## Troubleshooting

### Service Won't Start

```bash
# Check logs
docker-compose logs crypto-service

# Rebuild
docker-compose build --no-cache crypto-service

# Restart
docker-compose restart crypto-service
```

### Database Connection Error

```bash
# Verify postgres is running
docker-compose ps postgres

# Check database credentials in .env
cat laravel/.env | grep DB_

# Connect directly
docker-compose exec postgres psql -U workchain_user -d workchain_erp
```

### Middleware Not Applied

```bash
# Verify middleware is registered in kernel
docker-compose exec laravel php artisan route:list

# Check middleware order
grep -A 20 "protected \$middleware" laravel/app/Http/Kernel.php
```

### Frontend Can't Connect to Backend

```bash
# Check CORS headers
curl -H "Origin: http://localhost:3002" http://localhost:8000/api

# Verify API base URL
cat frontend/.env | grep PUBLIC_API_BASE
```

## Performance Optimization

### Database Queries

```bash
# Enable query logging
docker-compose exec laravel php artisan tinker
>>> DB::enableQueryLog();

# Check slow queries
>>> DB::getQueryLog();
```

### Caching

```bash
# Clear cache
docker-compose exec laravel php artisan cache:clear

# View cache config
cat laravel/config/cache.php
```

### Rate Limiting

Adaptive rate limiting automatically strengthens after failed attempts:
- First attempt blocked: 60 req/min
- After 3 violations: 50 req/min  
- After 6 violations: 40 req/min
- Maximum backoff: 10 req/min

## Monitoring

### Security Audit Log

```bash
# View recent security events
docker-compose exec laravel php artisan tinker
>>> \App\Models\SecurityAuditLog::latest()->limit(10)->get();
```

### Anomaly Detection Dashboard

- API endpoint: `GET /api/dashboard/anomalies`
- Shows user risk scores
- Displays detected patterns
- Lists geographic anomalies

## Deployment to Production

### 1. Prepare Environment

```bash
# Generate strong secrets
export JWT_SECRET=$(openssl rand -hex 32)
export DB_PASSWORD=$(openssl rand -base64 32)

# Update .env with production values
```

### 2. SSL Certificates

```bash
# Obtain from Let's Encrypt
certbot certonly --standalone -d yourdomain.com

# Update Caddyfile with certificate path
```

### 3. Scale Services

```yaml
# docker-compose.yml for production
services:
  laravel:
    deploy:
      replicas: 3
    
  crypto-service:
    deploy:
      replicas: 2
```

### 4. Monitoring Stack

Consider adding:
- Prometheus (metrics)
- Grafana (dashboards)
- ELK Stack (logs)
- Sentry (error tracking)

## Support & Documentation

- **Architecture**: See `ARCHITECTURE_COMPLETE.md`
- **Security**: See `NANODEFENSE_ARCHITECTURE.md`
- **API Docs**: Available at `http://localhost:8000/docs`
- **Issues**: Check GitHub issues or create new one

## License

All code is proprietary and confidential.
