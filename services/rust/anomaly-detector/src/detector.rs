use std::sync::Arc;
use tokio::sync::RwLock; // Solo para configs globales
use chrono::{DateTime, Utc, Duration};
use dashmap::DashMap; // MEJORA: Mapa concurrente sin bloqueos globales
use std::collections::HashMap;

// ==========================================
// MOCK MODELS (Para que el código compile completo)
// ==========================================
#[derive(Clone, Debug)]
pub struct BehaviorEvent {
    pub tenant_id: String, // AGREGADO: Vital para multi-tenancy
    pub client_id: String,
    pub timestamp: DateTime<Utc>,
    pub indicators: HashMap<String, f64>,
    pub confidence: f64,
}

#[derive(Clone, Debug)]
pub struct ClientProfile {
    pub tenant_id: String,
    pub client_id: String,
    pub first_seen: DateTime<Utc>,
    pub last_seen: DateTime<Utc>,
    pub total_events: u64,
    pub risk_score: f64,
    pub is_compromised: bool,
    pub threat_level: ThreatLevel,
}

#[derive(Clone, Debug)]
pub struct AnomalyScore {
    pub client_id: String,
    pub tenant_id: String,
    pub score: f64,
    pub level: ThreatLevel,
    pub detected_patterns: Vec<BehaviorPattern>,
    pub recommendation: String,
    pub timestamp: DateTime<Utc>,
}

#[derive(Clone, Debug, PartialEq, Eq, Hash)]
pub enum BehaviorPattern {
    Normal,
    RapidFailures,
    Enumeration,
    PayloadInjection,
    TimingAttack,
    ResourceAbuse,
    DeviceChange,
    AnomalousLocation,
    CredentialSpray,
}

#[derive(Clone, Debug, PartialEq, Eq)]
pub enum ThreatLevel {
    Safe,
    Low,
    Medium,
    High,
    Critical,
}

// Estructura para PatternMatcher (Mock)
pub struct PatternMatcher;
impl PatternMatcher {
    pub fn new() -> Self { Self }
    pub fn detect(&self, _event: &BehaviorEvent) -> Vec<BehaviorPattern> {
        // Simulación: En producción esto tendría lógica real
        vec![] 
    }
}

// ==========================================
// ANOMALY DETECTOR MEJORADO
// ==========================================

// Clave compuesta para separar usuarios por organización
// Evita que un ataque en la Org A afecte al usuario en la Org B
type ProfileKey = (String, String); // (tenant_id, client_id)

pub struct AnomalyDetector {
    // MEJORA: DashMap permite acceso concurrente ultra-rápido sin bloquear todo el sistema
    profiles: Arc<DashMap<ProfileKey, ClientProfile>>,
    pattern_matcher: Arc<PatternMatcher>,
    // Configuración global (rara vez cambia, RwLock está bien)
    thresholds: Arc<RwLock<HashMap<String, f64>>>,
    // Configuración de limpieza
    max_profiles: usize,
}

impl AnomalyDetector {
    pub async fn new() -> Self {
        Self {
            profiles: Arc::new(DashMap::new()),
            pattern_matcher: Arc::new(PatternMatcher::new()),
            thresholds: Arc::new(RwLock::new(Self::default_thresholds())),
            max_profiles: 100_000, // Límite para evitar Memory Exhaustion (DoS)
        }
    }

    pub async fn analyze(&self, event: &BehaviorEvent) -> Result<AnomalyScore, String> {
        // 1. Protección Anti-DoS de Memoria
        if self.profiles.len() >= self.max_profiles {
            self.cleanup_stale_profiles();
        }

        // 2. Clave Compuesta (Tenant Isolation)
        let key = (event.tenant_id.clone(), event.client_id.clone());

        // 3. Obtener o Crear Perfil (Operación Atómica con DashMap)
        let mut profile = self.profiles.entry(key.clone()).or_insert_with(|| ClientProfile {
            tenant_id: event.tenant_id.clone(),
            client_id: event.client_id.clone(),
            first_seen: Utc::now(),
            last_seen: Utc::now(),
            total_events: 0,
            risk_score: 0.0,
            is_compromised: false,
            threat_level: ThreatLevel::Safe,
        });

        // 4. Actualización de Metadatos
        profile.last_seen = Utc::now();
        profile.total_events += 1;

        // Si ya está comprometido, bloquear inmediatamente sin gastar CPU en análisis
        if profile.is_compromised {
            return Ok(AnomalyScore {
                client_id: event.client_id.clone(),
                tenant_id: event.tenant_id.clone(),
                score: 1.0,
                level: ThreatLevel::Critical,
                detected_patterns: vec![], // Ya no importa
                timestamp: Utc::now(),
                recommendation: "BLOCK_PERMANENTLY".to_string(),
            });
        }

        // 5. Detección de Patrones
        let detected_patterns = self.pattern_matcher.detect(event);

        // 6. Cálculo de Score (Corregido)
        let mut score = 0.0;
        let mut critical_trigger = false;

        for pattern in &detected_patterns {
            let p_score = self.calculate_pattern_score(pattern, &event.indicators).await;
            score += p_score;
            
            // Si hay inyección de payload, es CRÍTICO inmediatamente
            if *pattern == BehaviorPattern::PayloadInjection {
                critical_trigger = true;
                score = 1.0; 
            }
        }

        // Normalización inteligente (Acumulación con techo)
        score = score.min(1.0).max(0.0);

        // 7. Determinación de Nivel de Amenaza
        let level = match score {
            _ if critical_trigger => ThreatLevel::Critical, // Prioridad máxima
            s if s >= 0.9 => ThreatLevel::Critical,
            s if s >= 0.75 => ThreatLevel::High,
            s if s >= 0.5 => ThreatLevel::Medium,
            s if s >= 0.25 => ThreatLevel::Low,
            _ => ThreatLevel::Safe,
        };

        // 8. Actualización de Riesgo en el Perfil (Con memoria)
        // Usamos una media ponderada que da más peso al nuevo evento si es alto riesgo
        if score > profile.risk_score {
            // El riesgo sube rápido
            profile.risk_score = score;
        } else {
            // El riesgo baja lento (decay)
            profile.risk_score = (profile.risk_score * 0.9) + (score * 0.1);
        }
        
        profile.threat_level = level.clone();

        if level == ThreatLevel::Critical {
            profile.is_compromised = true;
        }

        // Recomendación de Seguridad para el Frontend/Gateway
        let recommendation = match level {
            ThreatLevel::Critical => "ISOLATE_SESSION".to_string(),
            ThreatLevel::High => "REQUIRE_MFA".to_string(),
            ThreatLevel::Medium => "THROTTLE_REQUESTS".to_string(),
            ThreatLevel::Low => "LOG_WARNING".to_string(),
            ThreatLevel::Safe => "ALLOW".to_string(),
        };

        Ok(AnomalyScore {
            client_id: event.client_id.clone(),
            tenant_id: event.tenant_id.clone(),
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
    ) -> f64 {
        let base_score = match pattern {
            BehaviorPattern::PayloadInjection => 1.0, // Instakill
            BehaviorPattern::CredentialSpray => 0.9,
            BehaviorPattern::Enumeration => 0.8,
            BehaviorPattern::ResourceAbuse => 0.7,
            BehaviorPattern::RapidFailures => 0.6,
            BehaviorPattern::TimingAttack => 0.5,
            BehaviorPattern::DeviceChange => 0.4,
            BehaviorPattern::AnomalousLocation => 0.3,
            BehaviorPattern::Normal => 0.0,
        };

        let mut multiplier = 1.0;
        if let Some(&failure_rate) = indicators.get("failure_rate") {
            // Si la tasa de fallo es alta, el patrón es más peligroso
            multiplier += failure_rate; 
        }

        (base_score * multiplier).min(1.0)
    }

    // MEJORA: Función para prevenir desbordamiento de memoria (DoS)
    // Elimina perfiles inactivos por más de 24 horas
    fn cleanup_stale_profiles(&self) {
        // En DashMap, retain escanea y elimina eficientemente
        let threshold_time = Utc::now() - Duration::hours(24);
        self.profiles.retain(|_, profile| {
            profile.last_seen > threshold_time && !profile.is_compromised
        });
        
        // Si aún estamos llenos (ataque activo), purga aleatoria de seguridad
        if self.profiles.len() >= self.max_profiles {
            self.profiles.clear(); // Drastic but safe fallback
        }
    }

    // Helpers
    pub fn get_profile(&self, tenant_id: &str, client_id: &str) -> Option<ClientProfile> {
        self.profiles.get(&(tenant_id.to_string(), client_id.to_string())).map(|r| r.value().clone())
    }

    fn default_thresholds() -> HashMap<String, f64> {
        let mut t = HashMap::new();
        t.insert("rate_limit".to_string(), 100.0);
        t
    }
}