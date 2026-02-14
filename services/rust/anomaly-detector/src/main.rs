use actix_web::{web, App, HttpServer, HttpResponse, HttpRequest, middleware, Responder};
use serde::{Deserialize, Serialize};
use dashmap::DashMap; // MEJORA: Reemplazo de Mutex<HashMap> para alto rendimiento
use std::sync::Arc;
use log::{info, warn, error};
use dotenv::dotenv;
use chrono::{DateTime, Utc, Timelike};

/**
 * WorkChain ERP - Anomaly Detection Service (Optimized)
 * * Improvements:
 * - DashMap for concurrent access (No global mutex locking).
 * - Memory caps on vectors to prevent DoS.
 * - Strict typing for Tenant Isolation.
 * - API Key Security.
 */

// ==========================================
// ESTRUCTURAS DE DATOS
// ==========================================

#[derive(Clone)]
struct AppState {
    // DashMap permite acceso concurrente. Clave: "tenant_id:user_id"
    baselines: Arc<DashMap<String, UserBaseline>>,
    api_key: String,
}

#[derive(Clone, Debug, Serialize, Deserialize)]
struct UserBaseline {
    user_id: i32,
    tenant_id: String,
    typical_countries: Vec<String>,
    typical_hours: Vec<u32>,
    known_user_agents: Vec<String>,
    endpoints_history: Vec<String>, // Renombrado para claridad
    last_updated: DateTime<Utc>,
}

#[derive(Deserialize, Serialize, Debug)]
struct AnomalyRequest {
    user_id: i32,
    tenant_id: String,
    ip_address: String,
    user_agent: String,
    endpoint: String,
}

#[derive(Deserialize)]
struct ResetRequest {
    user_id: i32,
    tenant_id: String,
}

#[derive(Serialize)]
struct AnomalyResponse {
    anomaly_score: f32,
    anomalies: Vec<String>,
    risk_level: String,
    action: String, // ALLOW, CHALLENGE, BLOCK
}

// ==========================================
// CONFIGURACIÓN Y MAIN
// ==========================================

#[actix_web::main]
async fn main() -> std::io::Result<()> {
    dotenv().ok();
    env_logger::init_from_env(env_logger::Env::new().default_filter_or("info"));

    let api_key = std::env::var("ANOMALY_API_KEY").unwrap_or_else(|_| "change_me_in_production".to_string());
    
    let app_state = AppState {
        baselines: Arc::new(DashMap::new()),
        api_key,
    };

    info!("🚀 Anomaly Detection Service started on port 3001");
    info!("🔒 Concurrency mode: DashMap (Lock-free reading)");

    HttpServer::new(move || {
        App::new()
            .app_data(web::Data::new(app_state.clone()))
            .wrap(middleware::Logger::default())
            // Middleware de seguridad simple
            .wrap(middleware::NormalizePath::trim())
            .route("/health", web::get().to(health))
            .service(
                web::scope("/api/v1")
                    .route("/detect", web::post().to(detect_anomaly))
                    .route("/baseline", web::post().to(update_baseline))
                    .route("/reset", web::post().to(reset_baseline))
            )
    })
    .bind("0.0.0.0:3001")?
    .run()
    .await
}

async fn health() -> impl Responder {
    HttpResponse::Ok().json(serde_json::json!({ "status": "healthy", "engine": "rust-dashmap" }))
}

// Helper para validar API Key
fn is_authorized(req: &HttpRequest, state: &web::Data<AppState>) -> bool {
    match req.headers().get("X-API-KEY") {
        Some(key) => key.to_str().unwrap_or("") == state.api_key,
        None => false,
    }
}

// ==========================================
// HANDLERS
// ==========================================

async fn detect_anomaly(
    req: HttpRequest,
    state: web::Data<AppState>,
    body: web::Json<AnomalyRequest>,
) -> HttpResponse {
    if !is_authorized(&req, &state) {
        return HttpResponse::Unauthorized().json(serde_json::json!({"error": "Invalid API Key"}));
    }

    // Generar clave compuesta para aislamiento Multi-Tenant estricto
    let key = format!("{}:{}", body.tenant_id, body.user_id);

    // DashMap permite obtener una referencia de lectura sin bloquear todo el mapa
    let baseline_ref = state.baselines.get(&key);

    let (score, anomalies) = match baseline_ref {
        Some(entry) => calculate_anomaly_score(&body, entry.value()),
        None => (0.0, vec!["New user profile created".to_string()]), // Cold start
    };

    let risk_level = determine_risk_level(score);
    let action = match risk_level.as_str() {
        "critical" => "BLOCK",
        "high" => "CHALLENGE",
        _ => "ALLOW",
    };

    if score > 0.0 {
        info!("⚠️ Anomaly detected [Tenant: {} User: {}]: Score: {}, Risk: {}", body.tenant_id, body.user_id, score, risk_level);
    }

    HttpResponse::Ok().json(AnomalyResponse {
        anomaly_score: score,
        anomalies,
        risk_level,
        action: action.to_string(),
    })
}

async fn update_baseline(
    req: HttpRequest,
    state: web::Data<AppState>,
    body: web::Json<AnomalyRequest>,
) -> HttpResponse {
    if !is_authorized(&req, &state) {
        return HttpResponse::Unauthorized().finish();
    }

    let key = format!("{}:{}", body.tenant_id, body.user_id);
    let now = Utc::now();
    let country = extract_country(&body.ip_address);
    let hour = now.hour();

    // DashMap: Operación atómica de escritura/actualización
    state.baselines.entry(key).and_modify(|b| {
        // Actualizar datos existentes con límites de memoria
        if !b.typical_countries.contains(&country) {
            if b.typical_countries.len() < 5 { b.typical_countries.push(country.clone()); }
        }
        if !b.typical_hours.contains(&hour) {
            b.typical_hours.push(hour);
        }
        if !b.known_user_agents.contains(&body.user_agent) {
            // Límite anti-DoS: Solo guardar últimos 10 UAs
            if b.known_user_agents.len() >= 10 { b.known_user_agents.remove(0); }
            b.known_user_agents.push(body.user_agent.clone());
        }
        
        // Sliding window para endpoints (Límite 50)
        b.endpoints_history.push(body.endpoint.clone());
        if b.endpoints_history.len() > 50 {
            b.endpoints_history.remove(0);
        }
        
        b.last_updated = now;
    }).or_insert(UserBaseline {
        user_id: body.user_id,
        tenant_id: body.tenant_id.clone(),
        typical_countries: vec![country],
        typical_hours: vec![hour],
        known_user_agents: vec![body.user_agent.clone()],
        endpoints_history: vec![body.endpoint.clone()],
        last_updated: now,
    });

    HttpResponse::Ok().json(serde_json::json!({ "status": "updated" }))
}

async fn reset_baseline(
    req: HttpRequest,
    state: web::Data<AppState>,
    body: web::Json<ResetRequest>, // Uso de Struct tipado en lugar de JSON genérico
) -> HttpResponse {
    if !is_authorized(&req, &state) {
        return HttpResponse::Unauthorized().finish();
    }

    let key = format!("{}:{}", body.tenant_id, body.user_id);
    
    // Eliminación atómica
    if state.baselines.remove(&key).is_some() {
        info!("Baseline reset for user {}", key);
        HttpResponse::Ok().json(serde_json::json!({ "status": "deleted" }))
    } else {
        HttpResponse::NotFound().json(serde_json::json!({ "error": "Profile not found" }))
    }
}

// ==========================================
// LOGICA DE NEGOCIO Y CALCULOS
// ==========================================

fn calculate_anomaly_score(req: &AnomalyRequest, baseline: &UserBaseline) -> (f32, Vec<String>) {
    let mut score: f32 = 0.0;
    let mut anomalies = Vec::new();

    // 1. Geo Check
    let current_country = extract_country(&req.ip_address);
    if !baseline.typical_countries.contains(&current_country) {
        score += 3.0;
        anomalies.push(format!("Unusual Location: {}", current_country));
    }

    // 2. Time Check
    let current_hour = Utc::now().hour();
    if !baseline.typical_hours.contains(&current_hour) {
        score += 1.5; // Bajamos peso, puede ser trabajo nocturno
        anomalies.push("Unusual Time".to_string());
    }

    // 3. User Agent Check
    if !baseline.known_user_agents.contains(&req.user_agent) {
        score += 2.0;
        anomalies.push("New Device/Browser".to_string());
    }

    // 4. Endpoint Enumeration (Heurística simple)
    // Si el usuario está tocando muchos endpoints nuevos rápidamente
    if !baseline.endpoints_history.contains(&req.endpoint) {
        score += 0.5; // Pequeña penalización por exploración normal
    }

    (score, anomalies)
}

fn extract_country(ip: &str) -> String {
    // Placeholder para integración real con GeoIP (MaxMind)
    // En producción usarías una crate como `maxminddb`
    if ip.starts_with("10.") || ip.starts_with("192.") {
        return "LAN".to_string();
    }
    "US".to_string() 
}

fn determine_risk_level(score: f32) -> String {
    match score {
        x if x < 2.0 => "low".to_string(),
        x if x < 4.5 => "medium".to_string(),
        x if x < 7.0 => "high".to_string(),
        _ => "critical".to_string(),
    }
}