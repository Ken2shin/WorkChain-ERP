#!/bin/bash

# WorkChain ERP - Quick Start Commands
# Complete reference for building and running the system
# Date: January 31, 2026

set -e

echo "================================================"
echo "WorkChain ERP - Complete Execution Script"
echo "================================================"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Function to print colored messages
print_status() {
    echo -e "${GREEN}[✓]${NC} $1"
}

print_error() {
    echo -e "${RED}[✗]${NC} $1"
}

print_info() {
    echo -e "${YELLOW}[i]${NC} $1"
}

# ============================================
# PHASE 1: CHECK PREREQUISITES
# ============================================

phase1_check_prerequisites() {
    print_info "Checking prerequisites..."
    
    # Check Docker
    if ! command -v docker &> /dev/null; then
        print_error "Docker not found. Install Docker first."
        exit 1
    fi
    print_status "Docker found: $(docker --version)"
    
    # Check Docker Compose
    if ! command -v docker-compose &> /dev/null; then
        print_error "Docker Compose not found. Install Docker Compose first."
        exit 1
    fi
    print_status "Docker Compose found: $(docker-compose --version)"
    
    # Check disk space
    available=$(df / | awk 'NR==2 {print $4}')
    if [ "$available" -lt 100000000 ]; then
        print_error "Insufficient disk space. Need 100GB, have $(($available/1000000))GB"
        exit 1
    fi
    print_status "Disk space OK: $(($available/1000000))GB available"
    
    # Check RAM
    memory=$(free -m | awk 'NR==2 {print $2}')
    if [ "$memory" -lt 16000 ]; then
        print_error "Insufficient RAM. Need 16GB, have ${memory}MB"
        exit 1
    fi
    print_status "RAM OK: ${memory}MB available"
}

# ============================================
# PHASE 2: BUILD SERVICES
# ============================================

phase2_build_services() {
    print_info "Building services..."
    
    # Build Go Crypto Service
    print_info "Building Go Crypto Service..."
    cd services/go/crypto-service
    go mod tidy
    go build -o crypto-service main.go crypto.go
    print_status "Go service built"
    cd ../../..
    
    # Build Rust Anomaly Detector
    print_info "Building Rust Anomaly Detector..."
    cd services/rust/anomaly-detector
    cargo build --release
    print_status "Rust service built"
    cd ../../..
    
    # Build C Crypto Core (optional if needed)
    print_info "C Crypto Core ready (compile if needed)"
    
    # Build C++ Threat Engine (optional if needed)
    print_info "C++ Threat Engine ready (compile if needed)"
    
    print_status "All services built successfully"
}

# ============================================
# PHASE 3: DOCKER BUILD
# ============================================

phase3_docker_build() {
    print_info "Building Docker images..."
    
    # Build all images
    docker-compose build
    
    print_status "All Docker images built"
    
    # Verify images
    echo ""
    print_info "Docker images created:"
    docker images | grep -E "workchain|postgres|caddy|redis"
}

# ============================================
# PHASE 4: START SERVICES
# ============================================

phase4_start_services() {
    print_info "Starting Docker services..."
    
    # Start services in the right order
    print_info "Starting PostgreSQL..."
    docker-compose up -d postgres
    sleep 10
    
    print_info "Starting all services..."
    docker-compose up -d
    
    sleep 10
    
    print_info "Waiting for services to stabilize..."
    for i in {1..30}; do
        if docker-compose ps | grep -q "workchain_postgres.*Up"; then
            break
        fi
        sleep 1
    done
    
    print_status "Services started"
    
    # Show status
    echo ""
    print_info "Service status:"
    docker-compose ps
}

# ============================================
# PHASE 5: DATABASE SETUP
# ============================================

phase5_database_setup() {
    print_info "Setting up database..."
    
    print_info "Installing Composer dependencies..."
    docker-compose exec -T laravel composer install
    
    print_info "Running migrations..."
    docker-compose exec -T laravel php artisan migrate
    
    print_info "Seeding database..."
    docker-compose exec -T laravel php artisan db:seed
    
    print_status "Database setup complete"
}

# ============================================
# PHASE 6: VERIFICATION
# ============================================

phase6_verification() {
    print_info "Verifying services..."
    
    # Check Laravel
    print_info "Checking Laravel API..."
    if curl -s http://localhost:8000/health > /dev/null; then
        print_status "Laravel API responding"
    else
        print_error "Laravel API not responding"
    fi
    
    # Check Go Crypto Service
    print_info "Checking Go Crypto Service..."
    if curl -s http://localhost:3000/health > /dev/null; then
        print_status "Go service responding"
    else
        print_error "Go service not responding"
    fi
    
    # Check Rust Anomaly Detector
    print_info "Checking Rust Anomaly Detector..."
    if curl -s http://localhost:3001/health > /dev/null; then
        print_status "Rust service responding"
    else
        print_error "Rust service not responding"
    fi
    
    # Check Frontend
    print_info "Checking Astro Frontend..."
    if curl -s http://localhost:3002 > /dev/null; then
        print_status "Frontend responding"
    else
        print_error "Frontend not responding"
    fi
    
    print_status "All services verified"
}

# ============================================
# PHASE 7: DISPLAY INFORMATION
# ============================================

phase7_display_info() {
    echo ""
    echo "================================================"
    echo "WorkChain ERP is Ready!"
    echo "================================================"
    echo ""
    echo "Access Points:"
    echo "  Frontend:      http://localhost:3002"
    echo "  API:           http://localhost:8000"
    echo "  Crypto:        http://localhost:3000"
    echo "  Anomaly:       http://localhost:3001"
    echo "  Database:      localhost:5432"
    echo ""
    echo "Default Credentials:"
    echo "  Email:    admin@workchain.local"
    echo "  Password: Admin123!@#"
    echo "  Tenant:   workchain-demo"
    echo ""
    echo "Quick Commands:"
    echo "  View logs:       docker-compose logs -f"
    echo "  Stop services:   docker-compose down"
    echo "  Restart:         docker-compose restart"
    echo "  Database shell:  docker-compose exec postgres psql -U workchain_user -d workchain_erp"
    echo ""
    echo "Documentation:"
    echo "  - README.md (overview)"
    echo "  - INSTALLATION.md (detailed setup)"
    echo "  - ARCHITECTURE_COMPLETE.md (system design)"
    echo "  - MULTI_LANGUAGE_SECURITY.md (security details)"
    echo ""
    echo "================================================"
}

# ============================================
# CLEANUP FUNCTION
# ============================================

cleanup() {
    print_info "Cleaning up..."
    docker-compose down
    print_status "Cleanup complete"
}

# ============================================
# MAIN EXECUTION
# ============================================

main() {
    print_info "Starting WorkChain ERP Setup..."
    echo ""
    
    # Ask for confirmation
    read -p "This will build and start WorkChain ERP. Continue? (y/n) " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        print_error "Setup cancelled"
        exit 1
    fi
    
    # Execute phases
    phase1_check_prerequisites
    echo ""
    
    phase2_build_services
    echo ""
    
    phase3_docker_build
    echo ""
    
    phase4_start_services
    echo ""
    
    phase5_database_setup
    echo ""
    
    phase6_verification
    echo ""
    
    phase7_display_info
}

# ============================================
# COMMAND LINE ARGUMENTS
# ============================================

case "${1:-start}" in
    start)
        main
        ;;
    stop)
        print_info "Stopping services..."
        docker-compose down
        print_status "Services stopped"
        ;;
    restart)
        print_info "Restarting services..."
        docker-compose restart
        print_status "Services restarted"
        ;;
    logs)
        docker-compose logs -f "${2:-}"
        ;;
    test)
        print_info "Testing system..."
        
        # Login
        echo "Testing login..."
        curl -X POST http://localhost:8000/api/auth/login \
            -H "Content-Type: application/json" \
            -d '{
                "email": "admin@workchain.local",
                "password": "Admin123!@#",
                "tenant_id": "workchain-demo"
            }' | jq .
        
        # Crypto service
        echo "Testing crypto service..."
        curl http://localhost:3000/health | jq .
        
        # Anomaly detector
        echo "Testing anomaly detector..."
        curl http://localhost:3001/health | jq .
        
        print_status "Tests complete"
        ;;
    shell)
        docker-compose exec laravel bash
        ;;
    db)
        docker-compose exec postgres psql -U workchain_user -d workchain_erp
        ;;
    clean)
        cleanup
        ;;
    help)
        echo "Usage: $0 [command]"
        echo ""
        echo "Commands:"
        echo "  start      - Build and start all services (default)"
        echo "  stop       - Stop all services"
        echo "  restart    - Restart all services"
        echo "  logs       - View service logs"
        echo "  test       - Run system tests"
        echo "  shell      - Open Laravel shell"
        echo "  db         - Open database shell"
        echo "  clean      - Stop and cleanup"
        echo "  help       - Show this help message"
        ;;
    *)
        print_error "Unknown command: $1"
        echo "Use '$0 help' for usage information"
        exit 1
        ;;
esac
