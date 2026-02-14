use crate::models::{BehaviorEvent, BehaviorPattern};
use std::collections::HashMap;

// ==========================================
// CONSTANTES DE CONFIGURACIÓN (THRESHOLDS)
// ==========================================

// Keys para buscar en el HashMap de indicadores
const KEY_INJECTION_SCORE: &str = "injection_score";
const KEY_FAILURE_RATE: &str = "failure_rate";
const KEY_ENUMERATION_SCORE: &str = "enumeration_score";
const KEY_TIMING_VARIANCE: &str = "timing_variance";
const KEY_RESOURCE_USAGE: &str = "resource_usage";
const KEY_SPRAY_SCORE: &str = "spray_score";
const KEY_LOCATION_RISK: &str = "location_risk"; // Nuevo

// Umbrales de Detección (Ajustados para Login de Organizador)
const THRESHOLD_INJECTION: f64 = 0.8;
const THRESHOLD_FAILURE_RATE: f64 = 0.4; // Bajado de 0.6 a 0.4 (Más estricto para login)
const THRESHOLD_ENUMERATION: f64 = 0.7;
const THRESHOLD_RESOURCE: f64 = 0.85;
const THRESHOLD_SPRAY: f64 = 0.7;
const THRESHOLD_LOCATION: f64 = 0.8; // Alta certeza de ubicación anómala

// Para Timing Attacks: Varianza muy baja (comportamiento robótico)
const TIMING_VARIANCE_MIN: f64 = 0.0;
const TIMING_VARIANCE_MAX: f64 = 10.0; // ms

pub struct PatternMatcher;

impl PatternMatcher {
    pub fn new() -> Self {
        Self
    }

    /// Analiza un evento y devuelve una lista de patrones sospechosos detectados.
    pub fn detect(&self, event: &BehaviorEvent) -> Vec<BehaviorPattern> {
        let mut patterns = Vec::new();

        // 1. Inyección de Payload (SQLi, XSS) - CRÍTICO
        if self.detect_payload_injection(&event.indicators) {
            patterns.push(BehaviorPattern::PayloadInjection);
        }

        // 2. Fallos Rápidos (Brute Force) - CRÍTICO PARA LOGIN
        if self.detect_rapid_failures(&event.indicators) {
            patterns.push(BehaviorPattern::RapidFailures);
        }

        // 3. Enumeración (Escaneo de usuarios/archivos)
        if self.detect_enumeration(&event.indicators) {
            patterns.push(BehaviorPattern::Enumeration);
        }

        // 4. Timing Attacks (Side-channel analysis)
        if self.detect_timing_attack(&event.indicators) {
            patterns.push(BehaviorPattern::TimingAttack);
        }

        // 5. Abuso de Recursos (DoS)
        if self.detect_resource_abuse(&event.indicators) {
            patterns.push(BehaviorPattern::ResourceAbuse);
        }

        // 6. Credential Spraying (Probar 1 password en muchos usuarios)
        if self.detect_credential_spray(&event.indicators) {
            patterns.push(BehaviorPattern::CredentialSpray);
        }

        // 7. Ubicación Anómala (Geoip Mismatch) - NUEVO
        if self.detect_anomalous_location(&event.indicators) {
            patterns.push(BehaviorPattern::AnomalousLocation);
        }

        patterns
    }

    // --- Métodos de Detección Específicos ---

    fn detect_payload_injection(&self, indicators: &HashMap<String, f64>) -> bool {
        indicators
            .get(KEY_INJECTION_SCORE)
            .map(|&score| score > THRESHOLD_INJECTION)
            .unwrap_or(false)
    }

    fn detect_rapid_failures(&self, indicators: &HashMap<String, f64>) -> bool {
        indicators
            .get(KEY_FAILURE_RATE)
            .map(|&rate| rate > THRESHOLD_FAILURE_RATE)
            .unwrap_or(false)
    }

    fn detect_enumeration(&self, indicators: &HashMap<String, f64>) -> bool {
        indicators
            .get(KEY_ENUMERATION_SCORE)
            .map(|&score| score > THRESHOLD_ENUMERATION)
            .unwrap_or(false)
    }

    fn detect_timing_attack(&self, indicators: &HashMap<String, f64>) -> bool {
        if let Some(&variance) = indicators.get(KEY_TIMING_VARIANCE) {
            // Detecta varianza artificialmente baja (bots)
            variance > TIMING_VARIANCE_MIN && variance < TIMING_VARIANCE_MAX
        } else {
            false
        }
    }

    fn detect_resource_abuse(&self, indicators: &HashMap<String, f64>) -> bool {
        indicators
            .get(KEY_RESOURCE_USAGE)
            .map(|&usage| usage > THRESHOLD_RESOURCE)
            .unwrap_or(false)
    }

    fn detect_credential_spray(&self, indicators: &HashMap<String, f64>) -> bool {
        indicators
            .get(KEY_SPRAY_SCORE)
            .map(|&score| score > THRESHOLD_SPRAY)
            .unwrap_or(false)
    }

    fn detect_anomalous_location(&self, indicators: &HashMap<String, f64>) -> bool {
        // Asumimos que upstream calcula 'location_risk' basado en historial vs IP actual
        indicators
            .get(KEY_LOCATION_RISK)
            .map(|&risk| risk > THRESHOLD_LOCATION)
            .unwrap_or(false)
    }
}