use serde::{Deserialize, Serialize};
use std::collections::HashMap;
use chrono::{DateTime, Utc};

#[derive(Debug, Clone, Serialize, Deserialize, PartialEq, Eq, Hash)]
pub enum ThreatLevel {
    Safe = 0,
    Low = 1,
    Medium = 2,
    High = 3,
    Critical = 4,
}

#[derive(Debug, Clone, Serialize, Deserialize, PartialEq, Eq, Hash)]
pub enum BehaviorPattern {
    Normal,
    RapidFailures,
    Enumeration,
    PayloadInjection,
    TimingAttack,
    ResourceAbuse,
    AnomalousLocation,
    DeviceChange,
    CredentialSpray,
}

#[derive(Debug, Clone, Serialize, Deserialize)]
pub struct BehaviorEvent {
    pub client_id: String,
    pub timestamp: DateTime<Utc>,
    pub pattern: BehaviorPattern,
    pub confidence: f64,
    pub indicators: HashMap<String, f64>,
    pub metadata: HashMap<String, String>,
}

#[derive(Debug, Clone, Serialize, Deserialize)]
pub struct AnomalyScore {
    pub client_id: String,
    pub score: f64,
    pub level: ThreatLevel,
    pub detected_patterns: Vec<BehaviorPattern>,
    pub timestamp: DateTime<Utc>,
    pub recommendation: String,
}

#[derive(Debug, Clone, Serialize, Deserialize)]
pub struct ClientProfile {
    pub client_id: String,
    pub first_seen: DateTime<Utc>,
    pub last_seen: DateTime<Utc>,
    pub total_events: u64,
    pub average_confidence: f64,
    pub risk_score: f64,
    pub is_compromised: bool,
    pub device_id: String,
    pub location_history: Vec<String>,
}

#[derive(Debug, Clone, Serialize, Deserialize)]
pub struct ThreatSignature {
    pub id: String,
    pub pattern: BehaviorPattern,
    pub threshold: u32,
    pub time_window_ms: u64,
    pub severity: ThreatLevel,
    pub description: String,
}

#[derive(Debug, Clone, Serialize, Deserialize)]
pub struct DetectionResult {
    pub success: bool,
    pub anomaly_score: Option<AnomalyScore>,
    pub error: Option<String>,
    pub processing_time_ms: u64,
}

#[derive(Debug, Clone, Serialize, Deserialize)]
pub struct HealthCheck {
    pub status: String,
    pub uptime_seconds: u64,
    pub events_processed: u64,
    pub active_profiles: u64,
    pub memory_usage_mb: u64,
}
