pub mod models;
pub mod detector;
pub mod patterns;
pub mod storage;
pub mod api;

pub use detector::AnomalyDetector;
pub use models::{BehaviorEvent, ThreatLevel, AnomalyScore};
pub use patterns::PatternMatcher;

/// Initialize the anomaly detection system
pub async fn initialize() -> Result<AnomalyDetector, Box<dyn std::error::Error>> {
    let detector = AnomalyDetector::new().await?;
    Ok(detector)
}
