use crate::models::{BehaviorEvent, BehaviorPattern};
use std::collections::HashMap;

pub struct PatternMatcher;

impl PatternMatcher {
    pub fn new() -> Self {
        Self
    }

    pub fn detect(&self, event: &BehaviorEvent) -> Vec<BehaviorPattern> {
        let mut patterns = Vec::new();

        // Check for payload injection
        if self.detect_payload_injection(&event.indicators) {
            patterns.push(BehaviorPattern::PayloadInjection);
        }

        // Check for rapid failures
        if self.detect_rapid_failures(&event.indicators) {
            patterns.push(BehaviorPattern::RapidFailures);
        }

        // Check for enumeration
        if self.detect_enumeration(&event.indicators) {
            patterns.push(BehaviorPattern::Enumeration);
        }

        // Check for timing attacks
        if self.detect_timing_attack(&event.indicators) {
            patterns.push(BehaviorPattern::TimingAttack);
        }

        // Check for resource abuse
        if self.detect_resource_abuse(&event.indicators) {
            patterns.push(BehaviorPattern::ResourceAbuse);
        }

        // Check for credential spray
        if self.detect_credential_spray(&event.indicators) {
            patterns.push(BehaviorPattern::CredentialSpray);
        }

        patterns
    }

    fn detect_payload_injection(&self, indicators: &HashMap<String, f64>) -> bool {
        if let Some(&injection_score) = indicators.get("injection_score") {
            injection_score > 0.8
        } else {
            false
        }
    }

    fn detect_rapid_failures(&self, indicators: &HashMap<String, f64>) -> bool {
        if let Some(&failure_rate) = indicators.get("failure_rate") {
            failure_rate > 0.6 // 60% or higher failure rate
        } else {
            false
        }
    }

    fn detect_enumeration(&self, indicators: &HashMap<String, f64>) -> bool {
        if let Some(&enum_score) = indicators.get("enumeration_score") {
            enum_score > 0.7
        } else {
            false
        }
    }

    fn detect_timing_attack(&self, indicators: &HashMap<String, f64>) -> bool {
        if let Some(&timing_variance) = indicators.get("timing_variance") {
            // Very low variance (< 10ms) might indicate timing attack
            timing_variance < 10.0 && timing_variance > 0.0
        } else {
            false
        }
    }

    fn detect_resource_abuse(&self, indicators: &HashMap<String, f64>) -> bool {
        if let Some(&resource_usage) = indicators.get("resource_usage") {
            resource_usage > 0.8
        } else {
            false
        }
    }

    fn detect_credential_spray(&self, indicators: &HashMap<String, f64>) -> bool {
        if let Some(&spray_score) = indicators.get("spray_score") {
            spray_score > 0.75
        } else {
            false
        }
    }
}
