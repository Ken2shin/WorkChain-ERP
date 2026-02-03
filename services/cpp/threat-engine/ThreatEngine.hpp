#ifndef WORKCHAIN_THREAT_ENGINE_HPP
#define WORKCHAIN_THREAT_ENGINE_HPP

#include <string>
#include <vector>
#include <map>
#include <unordered_map>
#include <memory>
#include <chrono>
#include <atomic>
#include <mutex>    // FALTABA ESTA LIBRERIA (Corrige el error de std::mutex)
#include <queue>
#include <algorithm>
#include <cstring>

namespace WorkChain::Security {

/* Threat Detection Engine for Real-time Behavior Analysis */

using TimePoint = std::chrono::high_resolution_clock::time_point;
using Milliseconds = std::chrono::milliseconds;

enum class ThreatLevel : uint8_t {
    SAFE = 0,
    LOW = 1,
    MEDIUM = 2,
    HIGH = 3,
    CRITICAL = 4
};

enum class BehaviorPattern : uint8_t {
    NORMAL = 0,
    RAPID_FAILURES = 1,
    ENUMERATION = 2,
    PAYLOAD_INJECTION = 3,
    TIMING_ATTACK = 4,
    RESOURCE_ABUSE = 5,
    ANOMALOUS_LOCATION = 6,
    DEVICE_CHANGE = 7,
    CREDENTIAL_SPRAY = 8
};

struct ThreatSignature {
    std::string id;
    BehaviorPattern pattern;
    uint32_t threshold;
    uint32_t time_window_ms;
    ThreatLevel severity;
    std::string description;
};

struct BehaviorMetrics {
    std::string client_id;
    std::string resource_id; // AGREGADO: Necesario para detectar "Enumeration Attack"
    TimePoint timestamp;
    BehaviorPattern pattern;
    float confidence;
    std::map<std::string, double> indicators;
};

struct AnomalyScore {
    std::string client_id;
    float score;
    ThreatLevel level;
    std::vector<BehaviorPattern> detected_patterns;
    TimePoint timestamp;
};

class ThreatSignatureDatabase {
public:
    ThreatSignatureDatabase();
    void addSignature(const ThreatSignature& sig);
    const ThreatSignature* getSignature(const std::string& id) const;
    bool matchesPattern(BehaviorPattern pattern) const;
    
private:
    std::unordered_map<std::string, ThreatSignature> signatures;
    mutable std::mutex db_mutex; // 'mutable' permite bloquear el mutex en funciones const
};

class BehaviorAnalyzer {
public:
    BehaviorAnalyzer(size_t history_size = 10000);
    
    void recordBehavior(const BehaviorMetrics& metrics);
    AnomalyScore analyzeBehavior(const std::string& client_id);
    float calculateAnomalyScore(const std::string& client_id);
    std::vector<BehaviorPattern> detectPatterns(const std::string& client_id);
    
private:
    struct ClientHistory {
        std::vector<BehaviorMetrics> behaviors;
        TimePoint first_seen;
        TimePoint last_seen;
        float cumulative_risk;
    };
    
    std::unordered_map<std::string, ClientHistory> history;
    size_t max_history_size;
    std::mutex history_mutex;
    
    float calculateRapidFailureScore(const ClientHistory& history);
    float calculateEnumerationScore(const ClientHistory& history);
    float calculatePayloadScore(const ClientHistory& history);
    float calculateTimingScore(const ClientHistory& history);
    float calculateResourceScore(const ClientHistory& history);
};

class AdaptiveThresholdManager {
public:
    AdaptiveThresholdManager();
    
    void updateThreshold(const std::string& metric, float new_threshold);
    float getThreshold(const std::string& metric) const;
    void reinforceThresholds(const AnomalyScore& anomaly);
    void resetThresholds();
    
private:
    std::map<std::string, float> thresholds;
    std::map<std::string, uint32_t> hit_counts;
    mutable std::mutex threshold_mutex;
};

class RateLimitingPolicy {
public:
    RateLimitingPolicy(uint32_t default_rps = 100);
    
    bool checkLimit(const std::string& client_id);
    void enforceDynamicLimits(const AnomalyScore& anomaly);
    void resetPolicies();
    
private:
    struct ClientPolicy {
        uint32_t requests_per_second;
        uint32_t burst_size;
        TimePoint last_reset;
        uint32_t request_count;
        bool is_compromised;
    };
    
    std::unordered_map<std::string, ClientPolicy> policies;
    uint32_t default_rps;
    std::mutex policy_mutex;
};

class ThreatResponseEngine {
public:
    ThreatResponseEngine();
    
    void respondToThreat(const AnomalyScore& anomaly);
    void isolateClient(const std::string& client_id, ThreatLevel level);
    void throttleClient(const std::string& client_id, float reduction_factor);
    void rerouteTraffic(const std::string& client_id); // CORREGIDO: Quitado el espacio
    void generateAlert(const AnomalyScore& anomaly);
    
private:
    struct ClientIsolation {
        std::string client_id;
        ThreatLevel level;
        TimePoint isolation_start;
        std::string reason;
    };
    
    std::vector<ClientIsolation> isolated_clients;
    std::mutex response_mutex;
};

class NanoSecurityMesh {
public:
    NanoSecurityMesh();
    ~NanoSecurityMesh();
    
    void initialize();
    void processRequest(const std::string& client_id, const BehaviorMetrics& metrics);
    ThreatLevel getThreatLevel(const std::string& client_id);
    AnomalyScore getAnomalyScore(const std::string& client_id);
    void enforceDefense(const AnomalyScore& anomaly);
    
private:
    std::unique_ptr<ThreatSignatureDatabase> signature_db;
    std::unique_ptr<BehaviorAnalyzer> behavior_analyzer;
    std::unique_ptr<AdaptiveThresholdManager> threshold_manager;
    std::unique_ptr<RateLimitingPolicy> rate_limiter;
    std::unique_ptr<ThreatResponseEngine> response_engine;
    
    std::atomic<bool> initialized;
    std::mutex mesh_mutex;
};

} // namespace WorkChain::Security

#endif /* WORKCHAIN_THREAT_ENGINE_HPP */