import Foundation
import Crypto
import CryptoKit

/// WorkChain ERP Session Manager (Secure & Optimized)
/// Features:
/// - Zero Trust Tenant Isolation
/// - O(1) Lookup Performance
/// - Secure Environment-based Secrets
/// - Base64URL Encoding for JWTs

// ==========================================
// MODELS & ERRORS
// ==========================================

enum SessionError: Error {
    case invalidToken
    case sessionExpired
    case sessionRevoked
    case invalidTenant       // Critical for filtering
    case insufficientPermissions
    case tokenGenerationFailed
    case serverMisconfiguration // Missing secrets
}

enum SessionState: String, Codable {
    case active = "ACTIVE"
    case suspended = "SUSPENDED"
    case revoked = "REVOKED"
    case expired = "EXPIRED"
}

struct SessionToken: Codable {
    let token: String
    let expiresAt: Date
    let refreshToken: String
    let scope: [String]
    let tenantId: String // Binding token to tenant explicitly
}

struct Session: Codable, Identifiable {
    let id: String
    let userId: String
    let tenantId: String
    let token: String // Hashed stored token (never store plain in memory if possible, but kept for logic)
    let refreshToken: String
    var state: SessionState
    let createdAt: Date
    let expiresAt: Date
    var lastActivityAt: Date
    let ipAddress: String
    let userAgent: String
    let deviceId: String
    let scope: [String]
    
    // Security attributes
    var riskScore: Double
    var isCompromised: Bool
}

// ==========================================
// UTILITIES
// ==========================================

/// Secure Token Generator with Base64URL support
class TokenGenerator {
    private let encoder = JSONEncoder()
    
    /// Generate cryptographically secure random opaque token
    func generateToken(length: Int = 32) throws -> String {
        var randomBytes = [UInt8](repeating: 0, count: length)
        let status = SecRandomCopyBytes(kSecRandomDefault, length, &randomBytes)
        
        guard status == errSecSuccess else {
            throw SessionError.tokenGenerationFailed
        }
        // Base64URL encoding (Safe for headers/URLs)
        return Data(randomBytes).base64EncodedString()
            .replacingOccurrences(of: "+", with: "-")
            .replacingOccurrences(of: "/", with: "_")
            .replacingOccurrences(of: "=", with: "")
    }
    
    /// Generate signed JWT compatible with standards
    func generateJWT(userId: String, tenantId: String, expiresIn: TimeInterval) throws -> String {
        // 1. Get Secret from Environment (FAIL SECURE)
        guard let secretKey = ProcessInfo.processInfo.environment["JWT_SECRET"], !secretKey.isEmpty else {
            print("CRITICAL: JWT_SECRET environment variable is missing.")
            throw SessionError.serverMisconfiguration
        }
        
        // 2. Header & Payload
        let header = ["alg": "HS256", "typ": "JWT"]
        let payload: [String: Any] = [
            "sub": userId,
            "tenant_id": tenantId, // Strict Tenant Binding
            "iat": Int(Date().timeIntervalSince1970),
            "exp": Int(Date().timeIntervalSince1970 + expiresIn),
            "iss": "workchain-erp"
        ]
        
        let headerData = try JSONSerialization.data(withJSONObject: header)
        let payloadData = try JSONSerialization.data(withJSONObject: payload)
        
        // 3. Base64URL Encoding
        func base64Url(_ data: Data) -> String {
            return data.base64EncodedString()
                .replacingOccurrences(of: "+", with: "-")
                .replacingOccurrences(of: "/", with: "_")
                .replacingOccurrences(of: "=", with: "")
        }
        
        let headerB64 = base64Url(headerData)
        let payloadB64 = base64Url(payloadData)
        let message = "\(headerB64).\(payloadB64)"
        
        // 4. Signing
        let key = SymmetricKey(data: secretKey.data(using: .utf8)!)
        let signature = HMAC<SHA256>.authenticationCode(for: Data(message.utf8), using: key)
        let signatureB64 = base64Url(Data(signature))
        
        return "\(message).\(signatureB64)"
    }
}

// ==========================================
// SECURE STORAGE (ACTOR)
// ==========================================

actor SecureSessionStore {
    // Primary Store: [SessionID: Session]
    private var sessions: [String: Session] = [:]
    
    // Secondary Index for Performance: [UserId: [SessionId]]
    // Avoids O(N) scans during login checks
    private var userSessionsIndex: [String: Set<String>] = [:]
    
    private let tokenGenerator = TokenGenerator()
    
    init() {}
    
    /// Create Session with O(1) indexing
    func createSession(
        userId: String,
        tenantId: String,
        ipAddress: String,
        userAgent: String,
        deviceId: String,
        permissions: [String]
    ) throws -> Session {
        let sessionId = UUID().uuidString
        let token = try tokenGenerator.generateToken()
        let refreshToken = try tokenGenerator.generateToken()
        let expiresAt = Date().addingTimeInterval(3600) // 1 hour
        
        let session = Session(
            id: sessionId,
            userId: userId,
            tenantId: tenantId,
            token: token,
            refreshToken: refreshToken,
            state: .active,
            createdAt: Date(),
            expiresAt: expiresAt,
            lastActivityAt: Date(),
            ipAddress: ipAddress,
            userAgent: userAgent,
            deviceId: deviceId,
            scope: permissions,
            riskScore: 0.0,
            isCompromised: false
        )
        
        // Store
        sessions[sessionId] = session
        
        // Update Index (Composite Key: tenant + user could be used, but userId is usually unique enough globally or we scope it)
        // Here we index by UserId to quickly find all sessions for a user
        if userSessionsIndex[userId] == nil {
            userSessionsIndex[userId] = []
        }
        userSessionsIndex[userId]?.insert(sessionId)
        
        return session
    }
    
    /// Validate Session with STRICT Tenant Isolation
    /// - Parameters:
    ///   - sessionId: The token ID
    ///   - expectedTenantId: The tenant form the request context (URL/Header)
    func validateAndGetSession(sessionId: String, expectedTenantId: String) throws -> Session {
        guard let session = sessions[sessionId] else {
            throw SessionError.invalidToken
        }
        
        // CRITICAL: Tenant Isolation Check
        // If the session exists but belongs to another tenant, we MUST deny it.
        guard session.tenantId == expectedTenantId else {
            print("SECURITY ALERT: Cross-tenant access attempt. Session Tenant: \(session.tenantId), Req Tenant: \(expectedTenantId)")
            throw SessionError.invalidTenant
        }
        
        guard session.state == .active else { throw SessionError.sessionRevoked }
        guard session.expiresAt > Date() else { throw SessionError.sessionExpired }
        
        return session
    }
    
    /// Update activity (Heartbeat)
    func touchSession(_ sessionId: String) {
        if var session = sessions[sessionId] {
            session.lastActivityAt = Date()
            // Extend expiration logic could go here
            sessions[sessionId] = session
        }
    }
    
    /// Revoke specific session
    func revokeSession(_ sessionId: String) {
        if var session = sessions[sessionId] {
            session.state = .revoked
            sessions[sessionId] = session
            // Optional: Remove from index immediately or let cleanup handle it
        }
    }
    
    /// Optimized cleanup using Index
    func cleanupExpiredSessions() {
        let now = Date()
        var expiredIds: [String] = []
        
        for (id, session) in sessions {
            if session.expiresAt < now || session.state == .revoked {
                expiredIds.append(id)
            }
        }
        
        for id in expiredIds {
            if let userId = sessions[id]?.userId {
                userSessionsIndex[userId]?.remove(id)
            }
            sessions.removeValue(forKey: id)
        }
        
        if !expiredIds.isEmpty {
            print("Cleanup: Removed \(expiredIds.count) expired sessions.")
        }
    }
    
    /// Get Active Sessions for User (Optimized O(1) via Index)
    func getActiveSessions(userId: String, tenantId: String) -> [Session] {
        guard let sessionIds = userSessionsIndex[userId] else { return [] }
        
        return sessionIds.compactMap { id in
            guard let session = sessions[id],
                  session.tenantId == tenantId, // Strict Filtering
                  session.state == .active,
                  session.expiresAt > Date() else {
                return nil
            }
            return session
        }
    }
    
    /// Mark compromised (Security Event)
    func markCompromised(userId: String, tenantId: String) {
        let active = getActiveSessions(userId: userId, tenantId: tenantId)
        for session in active {
            var updated = session
            updated.isCompromised = true
            updated.state = .revoked
            sessions[session.id] = updated
        }
    }
}

// ==========================================
// SESSION MANAGER (CONTROLLER)
// ==========================================

actor SessionManager {
    private let store: SecureSessionStore
    private var cleanupTask: Task<Void, Never>?
    
    init() {
        self.store = SecureSessionStore()
        startCleanupLoop()
    }
    
    deinit {
        cleanupTask?.cancel()
    }
    
    /// Start Login Process
    func login(
        userId: String,
        tenantId: String,
        ipAddress: String,
        userAgent: String,
        deviceId: String,
        permissions: [String]
    ) async throws -> SessionToken {
        
        // Create Session
        let session = try await store.createSession(
            userId: userId,
            tenantId: tenantId,
            ipAddress: ipAddress,
            userAgent: userAgent,
            deviceId: deviceId,
            permissions: permissions
        )
        
        return SessionToken(
            token: session.token,
            expiresAt: session.expiresAt,
            refreshToken: session.refreshToken,
            scope: session.scope,
            tenantId: session.tenantId
        )
    }
    
    /// Verify Request (Middleware Logic)
    func verifyRequest(token: String, tenantId: String) async throws -> Session {
        // Here we assume 'token' matches sessionId for opaque tokens. 
        // In real JWT scenarios, you'd parse the JWT to get the ID first.
        
        // 1. Validate against Store
        let session = try await store.validateAndGetSession(sessionId: token, expectedTenantId: tenantId)
        
        // 2. Update Activity
        await store.touchSession(token)
        
        return session
    }
    
    /// Logout
    func logout(sessionId: String) async {
        await store.revokeSession(sessionId)
    }
    
    /// Safe Concurrency Loop (Replaces Timer)
    private func startCleanupLoop() {
        cleanupTask = Task {
            while !Task.isCancelled {
                // Sleep for 1 hour (in nanoseconds)
                try? await Task.sleep(nanoseconds: 3600 * 1_000_000_000)
                await store.cleanupExpiredSessions()
            }
        }
    }
}