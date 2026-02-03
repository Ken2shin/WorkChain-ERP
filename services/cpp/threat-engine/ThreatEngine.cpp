#include "ThreatEngine.hpp"
#include <cmath>
#include <numeric>
#include <mutex>
#include <vector>
#include <map>
#include <algorithm>
#include <memory>
#include <iostream>

namespace WorkChain::Security {

/* ThreatSignatureDatabase Implementation */

ThreatSignatureDatabase::ThreatSignatureDatabase() {
    /* Initialize with default threat signatures */
    addSignature({
        "rapid_failures",
        BehaviorPattern::RAPID_FAILURES,
        5,
        60000,
        ThreatLevel::MEDIUM,
        "5 failed requests in 1 minute"
    });
    
    addSignature({
        "enumeration_attack",
        BehaviorPattern::ENUMERATION,
        20,
        300000,
        ThreatLevel::HIGH,
        "20+ path enumeration attempts"
    });
    
    addSignature({
        "payload_injection",
        BehaviorPattern::PAYLOAD_INJECTION,
        1,
        1000,
        ThreatLevel::CRITICAL,
        "Malicious payload detected"
    });
    
    addSignature({
        "timing_attack",
        BehaviorPattern::TIMING_ATTACK,
        50,
        60000,
        ThreatLevel::MEDIUM,
        "Abnormal request timing pattern"
    });
    
    addSignature({
        "resource_abuse",
        BehaviorPattern::RESOURCE_ABUSE,
        100,
        60000,
        ThreatLevel::HIGH,
        "Excessive resource consumption"
    });
}

void ThreatSignatureDatabase::addSignature(const ThreatSignature& sig) {
    std::lock_guard<std::mutex> lock(db_mutex);
    signatures[sig.id] = sig;
}

const ThreatSignature* ThreatSignatureDatabase::getSignature(const std::string& id) const {
    std::lock_guard<std::mutex> lock(db_mutex);
    auto it = signatures.find(id);
    return (it != signatures.end()) ? &it->second : nullptr;
}

bool ThreatSignatureDatabase::matchesPattern(BehaviorPattern pattern) const {
    std::lock_guard<std::mutex> lock(db_mutex);
    return std::any_of(signatures.begin(), signatures.end(),
        [pattern](const auto& pair) { return pair.second.pattern == pattern; });
}

/* BehaviorAnalyzer Implementation */

BehaviorAnalyzer::BehaviorAnalyzer(size_t history_size) 
    : max_history_size(history_size) {}

void BehaviorAnalyzer::recordBehavior(const BehaviorMetrics& metrics) {
    std::lock_guard<std::mutex> lock(history_mutex);
    
    auto& client_hist = history[metrics.client_id];
    
    if (client_hist.behaviors.empty()) {
        client_hist.first_seen = metrics.timestamp;
    }
    
    client_hist.behaviors.push_back(metrics);
    client_hist.last_seen = metrics.timestamp;
    
    /* Keep history size bounded */
    if (client_hist.behaviors.size() > max_history_size) {
        client_hist.behaviors.erase(client_hist.behaviors.begin());
    }
}

AnomalyScore BehaviorAnalyzer::analyzeBehavior(const std::string& client_id) {
    float score = calculateAnomalyScore(client_id);
    auto patterns = detectPatterns(client_id);
    
    ThreatLevel level = ThreatLevel::SAFE;
    if (score > 0.9f) level = ThreatLevel::CRITICAL;
    else if (score > 0.75f) level = ThreatLevel::HIGH;
    else if (score > 0.5f) level = ThreatLevel::MEDIUM;
    else if (score > 0.25f) level = ThreatLevel::LOW;
    
    return {
        client_id,
        score,
        level,
        patterns,
        std::chrono::high_resolution_clock::now()
    };
}

float BehaviorAnalyzer::calculateAnomalyScore(const std::string& client_id) {
    std::lock_guard<std::mutex> lock(history_mutex);
    
    auto it = history.find(client_id);
    if (it == history.end() || it->second.behaviors.empty()) {
        return 0.0f;
    }
    
    const auto& client_hist = it->second;
    float total_score = 0.0f;
    
    total_score += calculateRapidFailureScore(client_hist) * 0.25f;
    total_score += calculateEnumerationScore(client_hist) * 0.25f;
    total_score += calculatePayloadScore(client_hist) * 0.30f;
    total_score += calculateTimingScore(client_hist) * 0.10f;
    total_score += calculateResourceScore(client_hist) * 0.10f;
    
    return std::min(total_score, 1.0f);
}

std::vector<BehaviorPattern> BehaviorAnalyzer::detectPatterns(const std::string& client_id) {
    std::vector<BehaviorPattern> patterns;
    
    std::lock_guard<std::mutex> lock(history_mutex);
    auto it = history.find(client_id);
    if (it == history.end()) {
        return patterns;
    }
    
    /* Pattern detection logic */
    if (calculateRapidFailureScore(it->second) > 0.7f) {
        patterns.push_back(BehaviorPattern::RAPID_FAILURES);
    }
    if (calculateEnumerationScore(it->second) > 0.7f) {
        patterns.push_back(BehaviorPattern::ENUMERATION);
    }
    if (calculatePayloadScore(it->second) > 0.7f) {
        patterns.push_back(BehaviorPattern::PAYLOAD_INJECTION);
    }
    if (calculateTimingScore(it->second) > 0.7f) {
        patterns.push_back(BehaviorPattern::TIMING_ATTACK);
    }
    if (calculateResourceScore(it->second) > 0.7f) {
        patterns.push_back(BehaviorPattern::RESOURCE_ABUSE);
    }
    
    return patterns;
}

float BehaviorAnalyzer::calculateRapidFailureScore(const ClientHistory& history) {
    if (history.behaviors.size() < 3) return 0.0f;
    
    auto now = std::chrono::high_resolution_clock::now();
    uint32_t failures = 0;
    
    for (const auto& behavior : history.behaviors) {
        auto elapsed = std::chrono::duration_cast<Milliseconds>(now - behavior.timestamp).count();
        if (elapsed < 60000) { /* 1 minute window */
            if (behavior.confidence > 0.8f) {
                failures++;
            }
        }
    }
    
    return std::min(failures / 5.0f, 1.0f);
}

float BehaviorAnalyzer::calculateEnumerationScore(const ClientHistory& history) {
    if (history.behaviors.size() < 5) return 0.0f;
    
    std::map<std::string, uint32_t> path_counts;
    for (const auto& behavior : history.behaviors) {
        if (behavior.pattern == BehaviorPattern::ENUMERATION) {
            // AHORA FUNCIONA: Porque agregamos resource_id a la estructura en el .hpp
            path_counts[behavior.resource_id]++; 
        }
    }
    
    uint32_t unique_paths = path_counts.size();
    return std::min(unique_paths / 20.0f, 1.0f);
}

float BehaviorAnalyzer::calculatePayloadScore(const ClientHistory& history) {
    uint32_t suspicious_payloads = 0;
    
    for (const auto& behavior : history.behaviors) {
        if (behavior.pattern == BehaviorPattern::PAYLOAD_INJECTION) {
            suspicious_payloads++;
        }
    }
    
    return suspicious_payloads > 0 ? 1.0f : 0.0f;
}

float BehaviorAnalyzer::calculateTimingScore(const ClientHistory& history) {
    if (history.behaviors.size() < 10) return 0.0f;
    
    std::vector<long long> intervals;
    for (size_t i = 1; i < history.behaviors.size(); ++i) {
        auto delta = std::chrono::duration_cast<Milliseconds>(
            history.behaviors[i].timestamp - history.behaviors[i-1].timestamp
        ).count();
        intervals.push_back(delta);
    }
    
    double mean = std::accumulate(intervals.begin(), intervals.end(), 0.0) / intervals.size();
    double variance = 0.0;
    
    for (auto interval : intervals) {
        variance += (interval - mean) * (interval - mean);
    }
    variance /= intervals.size();
    
    double std_dev = std::sqrt(variance);
    
    return std_dev < 10.0 ? 0.8f : 0.0f;
}

float BehaviorAnalyzer::calculateResourceScore(const ClientHistory& history) {
    if (history.behaviors.size() < 5) return 0.0f;
    
    uint32_t high_resource_requests = 0;
    for (const auto& behavior : history.behaviors) {
        auto it = behavior.indicators.find("resource_usage");
        if (it != behavior.indicators.end() && it->second > 0.8) {
            high_resource_requests++;
        }
    }
    
    return std::min(high_resource_requests / 10.0f, 1.0f);
}

/* AdaptiveThresholdManager Implementation */

AdaptiveThresholdManager::AdaptiveThresholdManager() {
    thresholds["rate_limit"] = 100.0f;
    thresholds["anomaly_score"] = 0.5f;
    thresholds["failure_count"] = 5.0f;
    thresholds["enumeration_attempts"] = 20.0f;
}

void AdaptiveThresholdManager::updateThreshold(const std::string& metric, float new_threshold) {
    std::lock_guard<std::mutex> lock(threshold_mutex);
    thresholds[metric] = new_threshold;
}

float AdaptiveThresholdManager::getThreshold(const std::string& metric) const {
    std::lock_guard<std::mutex> lock(threshold_mutex);
    auto it = thresholds.find(metric);
    return (it != thresholds.end()) ? it->second : 0.5f;
}

void AdaptiveThresholdManager::reinforceThresholds(const AnomalyScore& anomaly) {
    std::lock_guard<std::mutex> lock(threshold_mutex);
    
    hit_counts[std::to_string(static_cast<int>(anomaly.level))]++;
    
    /* Harden thresholds based on threat level */
    if (anomaly.level >= ThreatLevel::HIGH) {
        thresholds["rate_limit"] *= 0.9f;
        thresholds["anomaly_score"] *= 0.95f;
    }
}

void AdaptiveThresholdManager::resetThresholds() {
    std::lock_guard<std::mutex> lock(threshold_mutex);
    thresholds.clear();
    hit_counts.clear();
}

/* RateLimitingPolicy Implementation */

RateLimitingPolicy::RateLimitingPolicy(uint32_t default_rps) 
    : default_rps(default_rps) {}

bool RateLimitingPolicy::checkLimit(const std::string& client_id) {
    std::lock_guard<std::mutex> lock(policy_mutex);
    
    auto& policy = policies[client_id];
    auto now = std::chrono::high_resolution_clock::now();
    
    if (std::chrono::duration_cast<std::chrono::seconds>(now - policy.last_reset).count() >= 1) {
        policy.request_count = 0;
        policy.last_reset = now;
    }
    
    if (policy.request_count >= policy.requests_per_second) {
        return false;
    }
    
    policy.request_count++;
    return true;
}

void RateLimitingPolicy::enforceDynamicLimits(const AnomalyScore& anomaly) {
    std::lock_guard<std::mutex> lock(policy_mutex);
    
    auto& policy = policies[anomaly.client_id];
    
    if (anomaly.level >= ThreatLevel::HIGH) {
        policy.requests_per_second = default_rps / 10;
    } else if (anomaly.level >= ThreatLevel::MEDIUM) {
        policy.requests_per_second = default_rps / 5;
    } else {
        policy.requests_per_second = default_rps;
    }
}

void RateLimitingPolicy::resetPolicies() {
    std::lock_guard<std::mutex> lock(policy_mutex);
    policies.clear();
}

/* ThreatResponseEngine Implementation */

ThreatResponseEngine::ThreatResponseEngine() {}

void ThreatResponseEngine::respondToThreat(const AnomalyScore& anomaly) {
    if (anomaly.level >= ThreatLevel::CRITICAL) {
        isolateClient(anomaly.client_id, anomaly.level);
    } else if (anomaly.level >= ThreatLevel::HIGH) {
        throttleClient(anomaly.client_id, 0.5f);
        generateAlert(anomaly);
    } else if (anomaly.level >= ThreatLevel::MEDIUM) {
        throttleClient(anomaly.client_id, 0.7f);
    }
}

void ThreatResponseEngine::isolateClient(const std::string& client_id, ThreatLevel level) {
    std::lock_guard<std::mutex> lock(response_mutex);
    isolated_clients.push_back({
        client_id,
        level,
        std::chrono::high_resolution_clock::now(),
        "Threat level exceeded CRITICAL threshold"
    });
}

void ThreatResponseEngine::throttleClient(const std::string& client_id, float reduction_factor) {
    /* Implementation for traffic throttling */
}

// CORREGIDO: Espacio eliminado en el nombre de la función
void ThreatResponseEngine::rerouteTraffic(const std::string& client_id) {
    /* Implementation for traffic rerouting */
}

void ThreatResponseEngine::generateAlert(const AnomalyScore& anomaly) {
    /* Implementation for alert generation */
}

/* NanoSecurityMesh Implementation */

NanoSecurityMesh::NanoSecurityMesh() : initialized(false) {
    signature_db = std::make_unique<ThreatSignatureDatabase>();
    behavior_analyzer = std::make_unique<BehaviorAnalyzer>();
    threshold_manager = std::make_unique<AdaptiveThresholdManager>();
    rate_limiter = std::make_unique<RateLimitingPolicy>();
    response_engine = std::make_unique<ThreatResponseEngine>();
}

NanoSecurityMesh::~NanoSecurityMesh() = default;

void NanoSecurityMesh::initialize() {
    std::lock_guard<std::mutex> lock(mesh_mutex);
    initialized = true;
}

void NanoSecurityMesh::processRequest(const std::string& client_id, const BehaviorMetrics& metrics) {
    if (!initialized) return;
    
    behavior_analyzer->recordBehavior(metrics);
    auto anomaly = behavior_analyzer->analyzeBehavior(client_id);
    
    if (anomaly.level >= ThreatLevel::MEDIUM) {
        threshold_manager->reinforceThresholds(anomaly);
        rate_limiter->enforceDynamicLimits(anomaly);
        response_engine->respondToThreat(anomaly);
    }
}

ThreatLevel NanoSecurityMesh::getThreatLevel(const std::string& client_id) {
    return behavior_analyzer->analyzeBehavior(client_id).level;
}

AnomalyScore NanoSecurityMesh::getAnomalyScore(const std::string& client_id) {
    return behavior_analyzer->analyzeBehavior(client_id);
}

void NanoSecurityMesh::enforceDefense(const AnomalyScore& anomaly) {
    response_engine->respondToThreat(anomaly);
}

} // namespace WorkChain::Security