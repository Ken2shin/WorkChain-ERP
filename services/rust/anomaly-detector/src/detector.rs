use crate::models::*;
use crate::patterns::PatternMatcher;
use std::collections::HashMap;
use std::sync::Arc;
use tokio::sync::RwLock;
use chrono::Utc;

pub struct AnomalyDetector {
    profiles: Arc<RwLock<HashMap<String, ClientProfile>>>,
    pattern_matcher: Arc<PatternMatcher>,
    threshold_manager: Arc<RwLock<HashMap<String, f64>>>,
}

impl AnomalyDetector {
    pub async fn new() -> Result<Self, Box<dyn std::error::Error>> {
        Ok(Self {
            profiles: Arc::new(RwLock::new(HashMap::new())),
            pattern_matcher: Arc::new(PatternMatcher::new()),
            threshold_manager: Arc::new(RwLock::new(Self::default_thresholds())),
        })
    }

    pub async fn analyze(&self, event: &BehaviorEvent) -> Result<AnomalyScore, String> {
        let start = std::time::Instant::now();

        // Record the event
        let mut profiles = self.profiles.write().await;
        let profile = profiles
            .entry(event.client_id.clone())
            .or_insert_with(|| ClientProfile {
                client_id: event.client_id.clone(),
                first_seen: Utc::now(),
                last_seen: Utc::now(),
                total_events: 0,
                average_confidence: 0.0,
                risk_score: 0.0,
                is_compromised: false,
                device_id: String::new(),
                location_history: Vec::new(),
            });

        profile.last_seen = Utc::now();
        profile.total_events += 1;
        profile.average_confidence = (profile.average_confidence * ((profile.total_events - 1) as f64) 
            + event.confidence) / (profile.total_events as f64);

        // Detect patterns
        let detected_patterns = self.pattern_matcher.detect(&event);

        // Calculate anomaly score
        let mut score = 0.0;
        
        for pattern in &detected_patterns {
            score += self.calculate_pattern_score(pattern, &event.indicators).await?;
        }

        // Normalize score to 0.0-1.0
        score = score.min(1.0).max(0.0);

        // Determine threat level
        let level = match score {
            s if s >= 0.9 => ThreatLevel::Critical,
            s if s >= 0.75 => ThreatLevel::High,
            s if s >= 0.5 => ThreatLevel::Medium,
            s if s >= 0.25 => ThreatLevel::Low,
            _ => ThreatLevel::Safe,
        };

        // Update profile risk score
        profile.risk_score = (profile.risk_score + score) / 2.0;
        if level == ThreatLevel::Critical {
            profile.is_compromised = true;
        }

        let processing_time = start.elapsed().as_millis() as u64;

        let recommendation = match level {
            ThreatLevel::Critical => "ISOLATE_IMMEDIATELY".to_string(),
            ThreatLevel::High => "ENFORCE_MFA_CHALLENGE".to_string(),
            ThreatLevel::Medium => "MONITOR_CLOSELY".to_string(),
            ThreatLevel::Low => "LOG_EVENT".to_string(),
            ThreatLevel::Safe => "ALLOW".to_string(),
        };

        Ok(AnomalyScore {
            client_id: event.client_id.clone(),
            score,
            level,
            detected_patterns,
            timestamp: Utc::now(),
            recommendation,
        })
    }

    async fn calculate_pattern_score(
        &self,
        pattern: &BehaviorPattern,
        indicators: &HashMap<String, f64>,
    ) -> Result<f64, String> {
        let base_score = match pattern {
            BehaviorPattern::PayloadInjection => 1.0,
            BehaviorPattern::CredentialSpray => 0.9,
            BehaviorPattern::Enumeration => 0.8,
            BehaviorPattern::ResourceAbuse => 0.7,
            BehaviorPattern::RapidFailures => 0.6,
            BehaviorPattern::TimingAttack => 0.5,
            BehaviorPattern::DeviceChange => 0.4,
            BehaviorPattern::AnomalousLocation => 0.3,
            BehaviorPattern::Normal => 0.0,
        };

        // Apply indicator multipliers
        let mut multiplier = 1.0;
        if let Some(&failure_rate) = indicators.get("failure_rate") {
            multiplier *= 1.0 + failure_rate;
        }
        if let Some(&request_rate) = indicators.get("request_rate") {
            multiplier *= 1.0 + request_rate;
        }

        Ok((base_score * multiplier).min(1.0))
    }

    pub async fn get_profile(&self, client_id: &str) -> Option<ClientProfile> {
        let profiles = self.profiles.read().await;
        profiles.get(client_id).cloned()
    }

    pub async fn get_all_profiles(&self) -> Vec<ClientProfile> {
        let profiles = self.profiles.read().await;
        profiles.values().cloned().collect()
    }

    pub async fn mark_compromised(&self, client_id: &str) {
        let mut profiles = self.profiles.write().await;
        if let Some(profile) = profiles.get_mut(client_id) {
            profile.is_compromised = true;
            profile.risk_score = 1.0;
        }
    }

    pub async fn reset_profile(&self, client_id: &str) {
        let mut profiles = self.profiles.write().await;
        profiles.remove(client_id);
    }

    pub async fn get_health(&self) -> HealthCheck {
        let profiles = self.profiles.read().await;
        let total_events: u64 = profiles.values().map(|p| p.total_events).sum();

        HealthCheck {
            status: "operational".to_string(),
            uptime_seconds: 0,
            events_processed: total_events,
            active_profiles: profiles.len() as u64,
            memory_usage_mb: 0,
        }
    }

    fn default_thresholds() -> HashMap<String, f64> {
        let mut thresholds = HashMap::new();
        thresholds.insert("rate_limit".to_string(), 100.0);
        thresholds.insert("anomaly_score".to_string(), 0.5);
        thresholds.insert("failure_count".to_string(), 5.0);
        thresholds.insert("enumeration_attempts".to_string(), 20.0);
        thresholds
    }
}
