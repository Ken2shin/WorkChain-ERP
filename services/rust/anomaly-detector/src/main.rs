use actix_web::{web, App, HttpServer, HttpResponse, middleware};
use serde::{Deserialize, Serialize};
use std::collections::HashMap;
use std::sync::{Arc, Mutex};
use log::info;
use dotenv::dotenv;

/**
 * WorkChain ERP - Anomaly Detection Service
 * Written in Rust for memory safety and performance
 *
 * Detects:
 * - Behavioral anomalies
 * - Geographic inconsistencies
 * - Access time anomalies
 * - Request pattern changes
 * - Endpoint enumeration
 */

#[derive(Clone)]
struct AppState {
    baselines: Arc<Mutex<HashMap<String, UserBaseline>>>,
}

#[derive(Clone, Debug)]
struct UserBaseline {
    user_id: i32,
    tenant_id: String,
    typical_countries: Vec<String>,
    typical_hours: Vec<u8>,
    request_count_hourly: Vec<u32>,
    known_user_agents: Vec<String>,
    endpoints_24h: Vec<String>,
    last_updated: i64,
}

#[derive(Deserialize, Serialize)]
struct AnomalyRequest {
    user_id: i32,
    tenant_id: String,
    ip_address: String,
    user_agent: String,
    endpoint: String,
    timestamp: i64,
}

#[derive(Serialize)]
struct AnomalyResponse {
    anomaly_score: f32,
    anomalies: Vec<String>,
    risk_level: String, // low, medium, high, critical
}

#[actix_web::main]
async fn main() -> std::io::Result<()> {
    dotenv().ok(); // Load from root .env
    env_logger::init_from_env(env_logger::Env::new().default_filter_or("info"));

    let app_state = AppState {
        baselines: Arc::new(Mutex::new(HashMap::new())),
    };

    info!("Starting Anomaly Detection Service");

    HttpServer::new(move || {
        App::new()
            .app_data(web::Data::new(app_state.clone()))
            .wrap(middleware::Logger::default())
            .route("/health", web::get().to(health))
            .route("/detect", web::post().to(detect_anomaly))
            .route("/baseline", web::post().to(update_baseline))
            .route("/reset", web::post().to(reset_baseline))
    })
    .bind("0.0.0.0:3001")?
    .run()
    .await
}

async fn health() -> HttpResponse {
    HttpResponse::Ok().json(serde_json::json!({
        "status": "healthy",
        "service": "anomaly-detector"
    }))
}

/**
 * Main anomaly detection endpoint
 */
async fn detect_anomaly(
    state: web::Data<AppState>,
    req: web::Json<AnomalyRequest>,
) -> HttpResponse {
    let baselines = state.baselines.lock().unwrap();
    let key = format!("{}:{}", req.tenant_id, req.user_id);

    let baseline = baselines.get(&key).cloned();
    drop(baselines);

    let (score, anomalies) = if let Some(baseline) = baseline {
        calculate_anomaly_score(&req, &baseline)
    } else {
        (0.0, vec![])
    };

    let risk_level = determine_risk_level(score);

    info!(
        "Anomaly detected: user_id={}, score={}, risk_level={}",
        req.user_id, score, risk_level
    );

    HttpResponse::Ok().json(AnomalyResponse {
        anomaly_score: score,
        anomalies,
        risk_level,
    })
}

/**
 * Calculate anomaly score based on baseline
 */
fn calculate_anomaly_score(req: &AnomalyRequest, baseline: &UserBaseline) -> (f32, Vec<String>) {
    let mut score: f32 = 0.0;
    let mut anomalies = Vec::new();

    // 1. Geographic anomaly
    if !baseline.typical_countries.contains(&extract_country(&req.ip_address)) {
        score += 3.0;
        anomalies.push("Geographic anomaly detected".to_string());
    }

    // 2. Time of access anomaly
    let hour = extract_hour(req.timestamp);
    if !baseline.typical_hours.contains(&hour) {
        score += 2.0;
        anomalies.push("Unusual access time".to_string());
    }

    // 3. User-agent mismatch
    if !is_user_agent_known(&req.user_agent, &baseline.known_user_agents) {
        score += 1.5;
        anomalies.push("Unknown user agent".to_string());
    }

    // 4. Endpoint enumeration
    if is_endpoint_enumeration(&req.endpoint, &baseline.endpoints_24h) {
        score += 4.0;
        anomalies.push("Possible endpoint enumeration".to_string());
    }

    (score, anomalies)
}

/**
 * Update user baseline (called during normal operation)
 */
async fn update_baseline(
    state: web::Data<AppState>,
    req: web::Json<AnomalyRequest>,
) -> HttpResponse {
    let mut baselines = state.baselines.lock().unwrap();
    let key = format!("{}:{}", req.tenant_id, req.user_id);

    let baseline = baselines.entry(key).or_insert_with(|| UserBaseline {
        user_id: req.user_id,
        tenant_id: req.tenant_id.clone(),
        typical_countries: vec![extract_country(&req.ip_address)],
        typical_hours: vec![extract_hour(req.timestamp)],
        request_count_hourly: vec![1],
        known_user_agents: vec![req.user_agent.clone()],
        endpoints_24h: vec![req.endpoint.clone()],
        last_updated: req.timestamp,
    });

    // Update baseline with new data
    if !baseline.typical_countries.contains(&extract_country(&req.ip_address)) {
        baseline.typical_countries.push(extract_country(&req.ip_address));
    }

    if !baseline.typical_hours.contains(&extract_hour(req.timestamp)) {
        baseline.typical_hours.push(extract_hour(req.timestamp));
    }

    if !baseline.known_user_agents.contains(&req.user_agent) {
        baseline.known_user_agents.push(req.user_agent.clone());
    }

    if !baseline.endpoints_24h.contains(&req.endpoint) {
        baseline.endpoints_24h.push(req.endpoint.clone());
    }

    baseline.last_updated = req.timestamp;

    drop(baselines);

    HttpResponse::Ok().json(serde_json::json!({
        "status": "baseline updated",
        "user_id": req.user_id
    }))
}

/**
 * Reset baseline for a user
 */
async fn reset_baseline(
    state: web::Data<AppState>,
    req: web::Json<serde_json::Value>,
) -> HttpResponse {
    let user_id = req["user_id"].as_i64().unwrap_or(0) as i32;
    let tenant_id = req["tenant_id"].as_str().unwrap_or("default");

    let mut baselines = state.baselines.lock().unwrap();
    let key = format!("{}:{}", tenant_id, user_id);
    baselines.remove(&key);

    HttpResponse::Ok().json(serde_json::json!({
        "status": "baseline reset",
        "user_id": user_id
    }))
}

/**
 * Helper: Extract country from IP (placeholder)
 */
fn extract_country(ip: &str) -> String {
    // TODO: Integrate with MaxMind GeoIP
    // For now, return a placeholder
    "US".to_string()
}

/**
 * Helper: Extract hour from timestamp
 */
fn extract_hour(timestamp: i64) -> u8 {
    let hours = (timestamp / 3600) % 24;
    hours as u8
}

/**
 * Helper: Check if user-agent is known
 */
fn is_user_agent_known(user_agent: &str, known: &[String]) -> bool {
    known.iter().any(|ua| ua.contains(user_agent.split('/').next().unwrap_or("")))
}

/**
 * Helper: Detect endpoint enumeration
 */
fn is_endpoint_enumeration(current: &str, recent: &[String]) -> bool {
    // If same user accessed 50+ unique endpoints in 24h, it's enumeration
    recent.len() > 50 && !recent.contains(&current.to_string())
}

/**
 * Determine risk level
 */
fn determine_risk_level(score: f32) -> String {
    match score {
        x if x < 2.0 => "low".to_string(),
        x if x < 4.0 => "medium".to_string(),
        x if x < 7.0 => "high".to_string(),
        _ => "critical".to_string(),
    }
}
