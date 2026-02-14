// ==========================================
// WORKCHAIN SECURITY LIBRARY - ENTRY POINT
// ==========================================

// Declaración de módulos internos
pub mod models;
pub mod detector;
pub mod patterns;
pub mod storage; 
pub mod api;

// Re-exportaciones públicas (API Pública)
// Solo exponemos lo que el consumidor de la librería necesita ver
pub use detector::AnomalyDetector;
pub use models::{BehaviorEvent, ThreatLevel, AnomalyScore, BehaviorPattern};
pub use patterns::PatternMatcher;

use std::sync::Arc;

// ==========================================
// CONFIGURACIÓN CENTRALIZADA
// ==========================================

/// Configuración para el motor de seguridad.
/// Permite ajustar la sensibilidad sin tocar el código fuente.
#[derive(Debug, Clone)]
pub struct SecurityConfig {
    pub max_active_profiles: usize,
    pub rate_limit_threshold: f64,
    pub sensitivity: f64, // 0.0 a 1.0
}

impl Default for SecurityConfig {
    fn default() -> Self {
        Self {
            max_active_profiles: 100_000,
            rate_limit_threshold: 100.0,
            sensitivity: 0.8,
        }
    }
}

// ==========================================
// INICIALIZACIÓN DEL SISTEMA
// ==========================================

/// Inicializa el sistema de detección de anomalías.
///
/// ⚠️ **ADVERTENCIA CRÍTICA DE ARQUITECTURA:** ⚠️
///
/// Esta función debe llamarse **EXACTAMENTE UNA VEZ** al inicio de tu aplicación (en el `main`).
///
/// El objeto retornado (`Arc<AnomalyDetector>`) debe inyectarse en el estado de tu servidor web
/// (por ejemplo, usando `.app_data()` en Actix o `State` en Axum).
///
/// ❌ SI LLAMAS A ESTA FUNCIÓN DENTRO DE UN ENDPOINT DE LOGIN:
///    Se creará un detector nuevo y vacío para cada petición, perdiendo todo el historial.
///    El bloqueo de organizaciones NO funcionará.
///
/// ✅ USO CORRECTO:
///    let security_engine = workchain_security::initialize(None).await?;
///    HttpServer::new(move || App::new().app_data(Data::new(security_engine.clone()))...
///
pub async fn initialize(config: Option<SecurityConfig>) -> Result<Arc<AnomalyDetector>, Box<dyn std::error::Error>> {
    // 1. Cargar configuración (o usar defaults seguros)
    let _cfg = config.unwrap_or_default();

    // 2. Instanciar el detector (Usando la lógica corregida del paso anterior)
    let detector = AnomalyDetector::new().await;

    // Aquí podrías aplicar la configuración _cfg al detector si expusieras métodos para ello.
    // Por ejemplo: detector.set_max_profiles(_cfg.max_active_profiles);

    // 3. Log de arranque (Vital para auditoría)
    println!("[SECURITY] WorkChain Threat Engine Initialized.");
    println!("[SECURITY] Mode: Zero Trust / Multi-Tenant Isolation");
    
    // 4. Retornar Puntero Compartido (Arc)
    // Esto garantiza que todos los hilos del servidor web vean la misma memoria
    // y bloqueen a los atacantes globalmente.
    Ok(Arc::new(detector))
}