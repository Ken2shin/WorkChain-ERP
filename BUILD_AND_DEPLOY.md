# WorkChain ERP - Build & Deployment Guide

Complete instructions for building and deploying the multi-language security architecture.

---

## Prerequisites

### System Requirements
- Linux (Ubuntu 20.04+ or equivalent)
- 16GB RAM minimum
- 100GB disk space
- Docker & Docker Compose
- Git

### Required Toolchains
```bash
# C/C++ Compiler
sudo apt-get install build-essential cmake openssl libssl-dev

# Go 1.20+
wget https://go.dev/dl/go1.20.linux-amd64.tar.gz
tar -C /usr/local -xzf go1.20.linux-amd64.tar.gz
export PATH=$PATH:/usr/local/go/bin

# Rust 1.70+
curl --proto '=https' --tlsv1.2 -sSf https://sh.rustup.rs | sh

# .NET 7.0+
wget https://dot.microsoft.com/download/dotnet/scripts/v1/dotnet-install.sh
chmod +x dotnet-install.sh
./dotnet-install.sh --version 7.0

# Swift 5.8+
sudo apt-get install swift

# NASM (Netwide Assembler)
sudo apt-get install nasm

# LLVM/Clang
sudo apt-get install llvm clang
```

---

## Build Process

### 1. C Cryptographic Core

```bash
cd services/c/crypto-core

# Compile with security flags
gcc -O2 -Wall \
    -fPIE \
    -fstack-protector-strong \
    -fno-builtin \
    -D_FORTIFY_SOURCE=2 \
    -c crypto.c

# Create shared library
gcc -shared -fPIC \
    -o libworkchain-crypto.so \
    crypto.o \
    -lssl -lcrypto

# Verify
ldd libworkchain-crypto.so
readelf -d libworkchain-crypto.so | grep NEEDED
```

### 2. C++ Threat Engine

```bash
cd services/cpp/threat-engine

# Create build directory
mkdir -p build && cd build

# Build with CMake
cmake .. \
    -DCMAKE_BUILD_TYPE=Release \
    -DCMAKE_CXX_FLAGS="-O3 -fPIC -std=c++17"

make -j$(nproc)

# Verify
file threat-engine
```

### 3. C# Access Control

```bash
cd services/csharp/AccessControl

# Build
dotnet build -c Release

# Create NuGet package
dotnet pack -c Release

# Output: bin/Release/WorkChain.AccessControl.*.nupkg
```

### 4. Assembly Security Primitives

```bash
cd services/assembly/crypto-primitives

# Assemble x86-64
nasm -f elf64 -o secure-operations.o secure-operations.asm

# Create shared library
gcc -shared -fPIC \
    -o libworkchain-asm.so \
    secure-operations.o

# Verify symbols
nm -D libworkchain-asm.so
```

### 5. Swift Session Manager

```bash
cd services/swift/SessionManager

# Build
swift build -c release

# Create library
swift build -c release --product SessionManager

# Verify
ls .build/release/
```

### 6. Rust Anomaly Detector

```bash
cd services/rust/anomaly-detector

# Build
cargo build --release

# Create binary
cargo build --release --bin anomaly-detector

# Strip and sign
strip target/release/anomaly-detector

# Run tests
cargo test --release
```

### 7. Go Crypto Service

```bash
cd services/go/crypto-service

# Download dependencies
go mod tidy

# Build
go build -o crypto-service \
    -ldflags="-s -w" \
    main.go crypto.go

# Strip binary
strip crypto-service

# Verify
file crypto-service
```

---

## Docker Container Build

### Build All Services

```bash
# From project root
docker-compose build

# Or build individual services
docker-compose build crypto-service
docker-compose build anomaly-detector
docker-compose build frontend
```

### Verify Images

```bash
docker images | grep workchain

# Should show:
# workchain-app:latest          (Laravel)
# workchain-frontend:latest     (Astro)
# workchain-crypto:latest       (Go)
# workchain-anomaly:latest      (Rust)
# workchain-gateway:latest      (Caddy)
```

---

## Deploy Stack

### 1. Initialize Environment

```bash
# Create .env file
cp .env.example .env

# Generate JWT secret
openssl rand -base64 32 > /tmp/jwt_secret
JWT_SECRET=$(cat /tmp/jwt_secret) docker-compose up -d postgres

# Wait for DB
sleep 10
```

### 2. Start Services

```bash
# Start all services
docker-compose up -d

# Verify all containers running
docker-compose ps

# Should show all 8 services as "Up"
```

### 3. Database Setup

```bash
# Run migrations
docker-compose exec laravel php artisan migrate

# Seed initial data
docker-compose exec laravel php artisan db:seed

# Verify
docker-compose exec postgres psql -U workchain_user -d workchain_erp -c "\dt"
```

### 4. Verification Checklist

```bash
# Check Laravel health
curl -s http://localhost:8000/health | jq .

# Check Go crypto service
curl -s http://localhost:3000/health | jq .

# Check Rust anomaly detector
curl -s http://localhost:3001/health | jq .

# Check Astro frontend
curl -s http://localhost:3002/ | head -20

# Check Gateway
curl -s https://localhost:443/ -k

# Database connectivity
docker-compose exec laravel php artisan tinker
>>> \Illuminate\Support\Facades\DB::connection()->getPdo();
```

---

## Load Testing

### Generate Test Load

```bash
# Install Apache Bench
sudo apt-get install apache2-utils

# Test Gateway
ab -n 10000 -c 100 http://localhost:80/

# Test Laravel API
ab -n 5000 -c 50 -H "Authorization: Bearer TOKEN" \
    http://localhost:8000/api/health

# Monitor during load
docker stats
```

### Security Under Load

```bash
# Watch rate limiting in action
watch -n 1 'curl -s http://localhost:8000/api/test \
    -H "Authorization: Bearer TOKEN" \
    -H "X-Client-IP: 192.168.1.100" | jq .'

# Monitor threat detection
curl -s http://localhost:3001/api/profiles | jq '.[] | select(.risk_score > 0.5)'
```

---

## Monitoring & Logs

### View Logs

```bash
# All services
docker-compose logs -f

# Specific service
docker-compose logs -f laravel
docker-compose logs -f crypto-service
docker-compose logs -f anomaly-detector

# Real-time monitoring
docker-compose exec laravel tail -f storage/logs/laravel.log
```

### Performance Metrics

```bash
# Check container resource usage
docker stats --no-stream

# Monitor database connections
docker-compose exec postgres psql -U workchain_user -d workchain_erp \
    -c "SELECT pid, usename, application_name, state FROM pg_stat_activity;"

# Check cache hits
docker-compose exec redis redis-cli INFO stats
```

---

## Security Verification

### Crypto Service Verification

```bash
# Generate test data
TEST_KEY=$(openssl rand -hex 32)
TEST_DATA="Hello, WorkChain!"

# Encrypt
curl -X POST http://localhost:3000/api/encrypt \
    -H "Content-Type: application/json" \
    -d "{\"data\": \"$TEST_DATA\", \"key\": \"$TEST_KEY\"}" | jq .

# Verify signatures
curl -X POST http://localhost:3000/api/verify-signature \
    -H "Content-Type: application/json" \
    -d "{...}" | jq .
```

### Anomaly Detection Verification

```bash
# Send test events
curl -X POST http://localhost:3001/api/analyze \
    -H "Content-Type: application/json" \
    -d '{
        "client_id": "test-user",
        "pattern": "RapidFailures",
        "confidence": 0.95,
        "indicators": {"failure_rate": 0.8}
    }' | jq .

# Check threat level
curl http://localhost:3001/api/threat-level/test-user | jq .
```

### Access Control Verification

```bash
# Test policy evaluation
curl -X POST http://localhost:8000/api/access-check \
    -H "Authorization: Bearer TOKEN" \
    -H "Content-Type: application/json" \
    -d '{
        "user_id": "user-123",
        "resource": "inventory/products",
        "action": "view",
        "tenant": "tenant-001"
    }' | jq .
```

---

## Production Deployment

### Pre-Production Checklist

- [ ] Change all default passwords
- [ ] Rotate JWT secret
- [ ] Enable HTTPS with valid certificates
- [ ] Configure backups
- [ ] Setup monitoring alerts
- [ ] Enable audit logging
- [ ] Configure rate limiting thresholds
- [ ] Setup log aggregation
- [ ] Configure auto-scaling policies
- [ ] Run security audit

### Production Environment Variables

```bash
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:$(openssl rand -base64 32)

DB_HOST=prod-postgres.example.com
DB_PASSWORD=$(openssl rand -base64 32)

JWT_SECRET=$(openssl rand -base64 32)
JWT_EXPIRATION=3600

CRYPTO_SERVICE_URL=http://crypto-service-lb:3000
ANOMALY_SERVICE_URL=http://anomaly-service-lb:3001

REDIS_HOST=prod-redis.example.com
REDIS_PASSWORD=$(openssl rand -base64 32)

SENTRY_DSN=https://key@sentry.io/project

LOG_CHANNEL=stack
LOG_LEVEL=info
```

---

## Troubleshooting

### Service Won't Start

```bash
# Check logs
docker-compose logs <service>

# Verify network
docker network ls
docker network inspect workchain_network

# Check port availability
netstat -tulpn | grep -E "3000|3001|3002|5432|6379"
```

### Database Connection Errors

```bash
# Test connection
docker-compose exec postgres psql -U workchain_user -c "SELECT 1"

# Check migrations
docker-compose exec laravel php artisan migrate:status

# Rollback and retry
docker-compose exec laravel php artisan migrate:rollback
docker-compose exec laravel php artisan migrate
```

### Crypto Service Issues

```bash
# Test directly
docker-compose exec crypto-service curl localhost:3000/health

# Check logs
docker-compose logs crypto-service

# Verify binary
docker-compose exec crypto-service file /app/crypto-service
```

---

## Update & Maintenance

### Update Services

```bash
# Pull latest code
git pull origin main

# Rebuild containers
docker-compose build --no-cache

# Restart services
docker-compose down
docker-compose up -d

# Verify
docker-compose ps
```

### Database Maintenance

```bash
# Backup
docker-compose exec postgres pg_dump -U workchain_user workchain_erp > backup.sql

# Restore
docker-compose exec -T postgres psql -U workchain_user workchain_erp < backup.sql

# Cleanup old data
docker-compose exec laravel php artisan command:cleanup-logs
```

---

## Performance Optimization

### Database Tuning

```sql
-- Create indexes
CREATE INDEX idx_sessions_user_tenant ON sessions(user_id, tenant_id);
CREATE INDEX idx_audit_logs_timestamp ON audit_logs(timestamp DESC);
CREATE INDEX idx_behaviors_client_time ON behaviors(client_id, timestamp DESC);

-- Analyze
ANALYZE;
```

### Redis Caching

```bash
# Configure cache
CACHE_DRIVER=redis
REDIS_HOST=redis
REDIS_PORT=6379

# Verify
docker-compose exec redis redis-cli PING
```

### Load Balancing

```nginx
# Caddy config for multiple app instances
workchain.example.com {
    reverse_proxy localhost:8000 localhost:8001 localhost:8002 {
        policy random
        health_uri /health
        health_interval 10s
    }
}
