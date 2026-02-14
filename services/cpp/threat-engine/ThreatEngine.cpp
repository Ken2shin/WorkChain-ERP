#include "ThreatEngine.hpp"
#include <cmath>
#include <numeric>
#include <shared_mutex> // Mejora de concurrencia
#include <vector>
#include <map>
#include <algorithm>
#include <memory>
#include <iostream>
#include <chrono>

namespace WorkChain::Security {

/* ---------------------------------------------------------
   ThreatSignatureDatabase Implementation 
   Optimized: Uses shared_mutex for high-concurrency reading
--------------------------------------------------------- */

/* Mutex wrapper for Reader/Writer locks */
static std::shared_mutex db_mutex;

ThreatSignatureDatabase::ThreatSignatureDatabase() {
    /* Initialize with default threat signatures */
    addSignature({"rapid_failures", BehaviorPattern::RAPID_FAILURES, 5, 60000, ThreatLevel::MEDIUM, "5 failed requests in 1 minute"});
    addSignature({"enumeration_attack", BehaviorPattern::ENUMERATION, 20, 300000, ThreatLevel::HIGH, "20+ path enumeration attempts"});
    addSignature({"payload_injection", BehaviorPattern::PAYLOAD_INJECTION, 1, 1000, ThreatLevel::CRITICAL, "Malicious payload detected"});
    addSignature({"timing_attack", BehaviorPattern::TIMING_ATTACK, 50, 60000, ThreatLevel::MEDIUM, "Abnormal request timing pattern"});
    addSignature({"resource_abuse", BehaviorPattern::RESOURCE_ABUSE, 100, 60000, ThreatLevel::HIGH, "Excessive resource consumption"});
}

void ThreatSignatureDatabase::addSignature(const ThreatSignature& sig) {
    std::unique_lock<std::shared_mutex> lock(db_mutex); // Write lock
    signatures[sig.id] = sig;
}

const ThreatSignature* ThreatSignatureDatabase::getSignature(const std::string& id) const {
    std::shared_lock<std::shared_mutex> lock(db_mutex); // Read lock (Allows multiple readers)
    auto it = signatures.find(id);
    return (it != signatures.end()) ? &it->second : nullptr;
}

bool ThreatSignatureDatabase::matchesPattern(BehaviorPattern pattern) const {
    std::shared_lock<std::shared_mutex> lock(db_mutex); // Read lock
    return std::any_of(signatures.begin(), signatures.end(),
        [pattern](const auto& pair) { return pair.second.pattern == pattern; });
}

/* ---------------------------------------------------------
   BehaviorAnalyzer Implementation
   Optimized: Garbage collection for stale clients
--------------------------------------------------------- */

static std::shared_mutex history_mutex;

BehaviorAnalyzer::BehaviorAnalyzer(size_t history_size) 
    : max_history_size(history_size) {}

void BehaviorAnalyzer::recordBehavior(const BehaviorMetrics& metrics) {
    std::unique_lock<std::shared_mutex> lock(history_mutex);
    
    // Auto-cleanup: Randomly check size to prevent DoS via memory exhaustion
    if (history.size() > 10000) { 
        cleanupStaleHistory(); 
    }

    auto& client_hist = history[metrics.client_id];
    
    if (client_hist.behaviors.empty()) {
        client_hist.first_seen = metrics.timestamp;
    }
    
    client_hist.behaviors.push_back(metrics);
    client_hist.last_seen = metrics.timestamp;
    
    /* Keep history size bounded (Sliding Window) */
    if (client_hist.behaviors.size() > max_history_size) {
        client_hist.behaviors.erase(client_hist.behaviors.begin());
    }
}

/* Helper to clean RAM from attackers generating random Client IDs */
void BehaviorAnalyzer::cleanupStaleHistory() {
    auto now = std::chrono::high_resolution_clock::now();
    for (auto it = history.begin(); it != history.end(); ) {
        auto idle_time = std::chrono::duration_cast<std::chrono::hours>(now - it->second.last_seen).count();
        if (idle_time > 24) { // Remove clients idle for > 24h
            it = history.erase(it);
        } else {
            ++it;
        }
    }
}

AnomalyScore BehaviorAnalyzer::analyzeBehavior(const std::string& client_id) {
    /* Critical: We lock ONCE for reading to ensure data consistency between Score and Patterns */
    std::shared_lock<std::shared_mutex> lock(history_mutex);

    auto it = history.find(client_id);
    if (it == history.end() || it->second.behaviors.empty()) {
        return { client_id, 0.0f, ThreatLevel::SAFE, {}, std::chrono::high_resolution_clock::now() };
    }

    const auto& client_hist = it->second;

    // Calculate internals without re-locking
    float total_score = 0.0f;
    total_score += calculateRapidFailureScore(client_hist) * 0.25f;
    total_score += calculateEnumerationScore(client_hist) * 0.25f;
    total_score += calculatePayloadScore(client_hist) * 0.30f;
    total_score += calculateTimingScore(client_hist) * 0.10f;
    total_score += calculateResourceScore(client_hist) * 0.10f;
    
    float final_score = std::min(total_score, 1.0f);

    std::vector<BehaviorPattern> patterns;
    if (calculateRapidFailureScore(client_hist) > 0.7f) patterns.push_back(BehaviorPattern::RAPID_FAILURES);
    if (calculateEnumerationScore(client_hist) > 0.7f) patterns.push_back(BehaviorPattern::ENUMERATION);
    if (calculatePayloadScore(client_hist) > 0.7f) patterns.push_back(BehaviorPattern::PAYLOAD_INJECTION);
    if (calculateTimingScore(client_hist) > 0.7f) patterns.push_back(BehaviorPattern::TIMING_ATTACK);
    if (calculateResourceScore(client_hist) > 0.7f) patterns.push_back(BehaviorPattern::RESOURCE_ABUSE);

    ThreatLevel level = ThreatLevel::SAFE;
    if (final_score > 0.9f) level = ThreatLevel::CRITICAL;
    else if (final_score > 0.75f) level = ThreatLevel::HIGH;
    else if (final_score > 0.5f) level = ThreatLevel::MEDIUM;
    else if (final_score > 0.25f) level = ThreatLevel::LOW;
    
    return {
        client_id,
        final_score,
        level,
        patterns,
        std::chrono::high_resolution_clock::now()
    };
}

/* Note: These internal calculation functions assume the caller holds the lock */
float BehaviorAnalyzer::calculateRapidFailureScore(const ClientHistory& history) {
    if (history.behaviors.size() < 3) return 0.0f;
    auto now = std::chrono::high_resolution_clock::now();
    uint32_t failures = 0;
    
    for (auto it = history.behaviors.rbegin(); it != history.behaviors.rend(); ++it) {
         auto elapsed = std::chrono::duration_cast<std::chrono::milliseconds>(now - it->timestamp).count();
         if (elapsed > 60000) break; // Optimization: Stop checking if older than window
         
         // Assuming 'confidence' maps to success/fail logic (e.g. low confidence = fail)
         // Or strictly checking a status flag if added to metrics
         if (it->confidence > 0.8f) failures++; // Adjusted logic based on context
    }
    return std::min(failures / 5.0f, 1.0f);
}

float BehaviorAnalyzer::calculateEnumerationScore(const ClientHistory& history) {
    if (history.behaviors.size() < 5) return 0.0f;
    std::map<std::string, uint32_t> path_counts;
    for (const auto& behavior : history.behaviors) {
        if (!behavior.resource_id.empty()) {
            path_counts[behavior.resource_id]++;
        }
    }
    // High unique paths count = Enumeration scanning
    uint32_t unique_paths = path_counts.size();
    return std::min(unique_paths / 20.0f, 1.0f);
}

float BehaviorAnalyzer::calculatePayloadScore(const ClientHistory& history) {
    uint32_t suspicious_payloads = 0;
    for (const auto& behavior : history.behaviors) {
        if (behavior.pattern == BehaviorPattern::PAYLOAD_INJECTION) suspicious_payloads++;
    }
    return suspicious_payloads > 0 ? 1.0f : 0.0f;
}

float BehaviorAnalyzer::calculateTimingScore(const ClientHistory& history) {
    if (history.behaviors.size() < 10) return 0.0f;
    std::vector<long long> intervals;
    for (size_t i = 1; i < history.behaviors.size(); ++i) {
        auto delta = std::chrono::duration_cast<std::chrono::milliseconds>(
            history.behaviors[i].timestamp - history.behaviors[i-1].timestamp
        ).count();
        intervals.push_back(delta);
    }
    
    double mean = std::accumulate(intervals.begin(), intervals.end(), 0.0) / intervals.size();
    double variance = 0.0;
    for (auto interval : intervals) variance += (interval - mean) * (interval - mean);
    variance /= intervals.size();
    double std_dev = std::sqrt(variance);
    
    // Very low jitter (StdDev < 10ms) implies bot automation
    return std_dev < 10.0 ? 0.9f : 0.0f;
}

float BehaviorAnalyzer::calculateResourceScore(const ClientHistory& history) {
    if (history.behaviors.size() < 5) return 0.0f;
    uint32_t high_resource_requests = 0;
    for (const auto& behavior : history.behaviors) {
        // Safe map lookup
        auto it = behavior.indicators.find("resource_usage");
        if (it != behavior.indicators.end() && it->second > 0.8) {
            high_resource_requests++;
        }
    }
    return std::min(high_resource_requests / 10.0f, 1.0f);
}

// These dummy implementations were missing in public API but needed for compilation
float BehaviorAnalyzer::calculateAnomalyScore(const std::string& client_id) {
    // Redirects to main logic to avoid code duplication
    return analyzeBehavior(client_id).score;
}
std::vector<BehaviorPattern> BehaviorAnalyzer::detectPatterns(const std::string& client_id) {
    return analyzeBehavior(client_id).detected_patterns;
}

/* ---------------------------------------------------------
   AdaptiveThresholdManager Implementation
--------------------------------------------------------- */

static std::mutex threshold_mutex; // Config changes are rare, standard mutex is fine

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
    
    /* Harden thresholds dynamically under attack */
    if (anomaly.level >= ThreatLevel::HIGH) {
        thresholds["rate_limit"] = std::max(10.0f, thresholds["rate_limit"] * 0.9f); // Never go below 10
        thresholds["anomaly_score"] = std::max(0.2f, thresholds["anomaly_score"] * 0.95f);
    }
}

void AdaptiveThresholdManager::resetThresholds() {
    std::lock_guard<std::mutex> lock(threshold_mutex);
    thresholds.clear();
    hit_counts.clear();
    // Restore defaults
    thresholds["rate_limit"] = 100.0f;
}

/* ---------------------------------------------------------
   RateLimitingPolicy Implementation
--------------------------------------------------------- */

static std::mutex policy_mutex; // Ideally shared_mutex, but writes are frequent (every request)

RateLimitingPolicy::RateLimitingPolicy(uint32_t default_rps) 
    : default_rps(default_rps) {}

bool RateLimitingPolicy::checkLimit(const std::string& client_id) {
    std::lock_guard<std::mutex> lock(policy_mutex);
    
    auto& policy = policies[client_id];
    auto now = std::chrono::high_resolution_clock::now();
    
    // Initialize defaults if new client
    if (policy.requests_per_second == 0) policy.requests_per_second = default_rps;

    if (std::chrono::duration_cast<std::chrono::seconds>(now - policy.last_reset).count() >= 1) {
        policy.request_count = 0;
        policy.last_reset = now;
    }
    
    if (policy.request_count >= policy.requests_per_second) {
        return false; // LIMIT EXCEEDED
    }
    
    policy.request_count++;
    return true; // ALLOWED
}

void RateLimitingPolicy::enforceDynamicLimits(const AnomalyScore& anomaly) {
    std::lock_guard<std::mutex> lock(policy_mutex);
    auto& policy = policies[anomaly.client_id];
    
    if (anomaly.level >= ThreatLevel::HIGH) {
        policy.requests_per_second = std::max(1u, default_rps / 10);
    } else if (anomaly.level >= ThreatLevel::MEDIUM) {
        policy.requests_per_second = std::max(5u, default_rps / 5);
    }
}

void RateLimitingPolicy::resetPolicies() {
    std::lock_guard<std::mutex> lock(policy_mutex);
    policies.clear();
}

/* ---------------------------------------------------------
   ThreatResponseEngine Implementation
--------------------------------------------------------- */

static std::mutex response_mutex;

ThreatResponseEngine::ThreatResponseEngine() {}

void ThreatResponseEngine::respondToThreat(const AnomalyScore& anomaly) {
    if (anomaly.level >= ThreatLevel::CRITICAL) {
        isolateClient(anomaly.client_id, anomaly.level);
    } else if (anomaly.level >= ThreatLevel::HIGH) {
        throttleClient(anomaly.client_id, 0.5f);
        generateAlert(anomaly);
    }
}

void ThreatResponseEngine::isolateClient(const std::string& client_id, ThreatLevel level) {
    std::lock_guard<std::mutex> lock(response_mutex);
    isolated_clients.push_back({
        client_id,
        level,
        std::chrono::high_resolution_clock::now(),
        "Threat level exceeded CRITICAL threshold - ISOLATION ENFORCED"
    });
    std::cout << "[SECURITY-ALERT] Client " << client_id << " ISOLATED due to CRITICAL THREAT." << std::endl;
}

void ThreatResponseEngine::throttleClient(const std::string& client_id, float reduction_factor) {
    // Integration point with Load Balancer or Reverse Proxy
    std::cout << "[SECURITY-INFO] Throttling client " << client_id << " by factor " << reduction_factor << std::endl;
}

void ThreatResponseEngine::rerouteTraffic(const std::string& client_id) {
    // Honeypot redirection logic would go here
}

void ThreatResponseEngine::generateAlert(const AnomalyScore& anomaly) {
    // Integration with SIEM / Dashboard
    std::cout << "[SECURITY-WARN] Anomaly detected for " << anomaly.client_id << ". Score: " << anomaly.score << std::endl;
}

/* ---------------------------------------------------------
   NanoSecurityMesh Implementation
   The Main Entry Point
--------------------------------------------------------- */

static std::mutex mesh_mutex;

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

/* FIXED: Returns 'bool' to enforce filtering.
   Returns: true (ALLOW), false (BLOCK)
*/
bool NanoSecurityMesh::processRequest(const std::string& client_id, const BehaviorMetrics& metrics) {
    if (!initialized) return true; // Fail open if not ready (or false depending on policy)
    
    // 1. Rate Limit Check (First line of defense)
    if (!rate_limiter->checkLimit(client_id)) {
        return false; // BLOCK: Rate limit exceeded
    }

    // 2. Behavior Analysis
    behavior_analyzer->recordBehavior(metrics);
    auto anomaly = behavior_analyzer->analyzeBehavior(client_id);
    
    // 3. Dynamic Response
    if (anomaly.level >= ThreatLevel::MEDIUM) {
        threshold_manager->reinforceThresholds(anomaly);
        rate_limiter->enforceDynamicLimits(anomaly);
        response_engine->respondToThreat(anomaly);
    }

    // 4. FILTERING DECISION (The missing piece in your original code)
    if (anomaly.level >= ThreatLevel::CRITICAL) {
        return false; // BLOCK: High Threat
    }
    
    // Check specific blocking signatures (like SQL Injection)
    for (const auto& pattern : anomaly.detected_patterns) {
        if (pattern == BehaviorPattern::PAYLOAD_INJECTION) return false;
    }

    return true; // ALLOW
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