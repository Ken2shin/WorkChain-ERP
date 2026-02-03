import Foundation
import Crypto
import CryptoKit

/// WorkChain ERP Session Manager
/// Manages secure session tokens, lifecycle, and validation
/// Uses Apple CryptoKit for hardware-accelerated cryptography

enum SessionError: Error {
    case invalidToken
    case sessionExpired
    case sessionRevoked
    case invalidTenant
    case insufficientPermissions
    case tokenGenerationFailed
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
    let metadata: [String: String]
}

struct Session: Codable, Identifiable {
    let id: String
    let userId: String
    let tenantId: String
    let token: String
    let refreshToken: String
    let state: SessionState
    let createdAt: Date
    let expiresAt: Date
    let lastActivityAt: Date
    let ipAddress: String
    let userAgent: String
    let deviceId: String
    let scope: [String]
    
    // Security attributes
    let riskScore: Double
    let isCompromised: Bool
    let deviceTrusted: Bool
}

/// Cryptographically secure random token generator
class TokenGenerator {
    private let encoder = JSONEncoder()
    private let decoder = JSONDecoder()
    
    /// Generate secure random token
    func generateToken(length: Int = 32) throws -> String {
        var randomBytes = [UInt8](repeating: 0, count: length)
        
        let status = SecRandomCopyBytes(kSecRandomDefault, length, &randomBytes)
        guard status == errSecSuccess else {
            throw SessionError.tokenGenerationFailed
        }
        
        return Data(randomBytes).base64EncodedString()
    }
    
    /// Create JWT-like token with signature
    func generateJWT(userId: String, tenantId: String, expiresIn: TimeInterval) throws -> String {
        let header = ["alg": "HS256", "typ": "JWT"]
        let payload = [
            "sub": userId,
            "tenant": tenantId,
            "iat": Int(Date().timeIntervalSince1970),
            "exp": Int(Date().timeIntervalSince1970 + expiresIn)
        ] as [String: Any]
        
        let headerData = try encoder.encode(header)
        let payloadData = try encoder.encode(payload)
        
        let headerEncoded = headerData.base64EncodedString()
        let payloadEncoded = payloadData.base64EncodedString()
        
        let message = "\(headerEncoded).\(payloadEncoded)"
        
        // Sign with HMAC-SHA256
        guard let secretData = "your-secret-key".data(using: .utf8) else {
            throw SessionError.tokenGenerationFailed
        }
        
        let signature = HMAC<SHA256>.authenticationCode(
            for: Data(message.utf8),
            using: SymmetricKey(data: secretData)
        )
        
        let signatureEncoded = Data(signature).base64EncodedString()
        
        return "\(message).\(signatureEncoded)"
    }
}

/// Session storage with encryption
actor SecureSessionStore {
    private var sessions: [String: Session] = [:]
    private let tokenGenerator = TokenGenerator()
    
    // Encryption for sensitive data
    private let encryptionKey: SymmetricKey
    
    init() throws {
        // In production, this should be loaded from secure storage
        self.encryptionKey = SymmetricKey(size: .bits256)
    }
    
    /// Create and store new session
    func createSession(
        userId: String,
        tenantId: String,
        ipAddress: String,
        userAgent: String,
        deviceId: String,
        permissions: [String]
    ) async throws -> Session {
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
            isCompromised: false,
            deviceTrusted: false
        )
        
        sessions[sessionId] = session
        
        return session
    }
    
    /// Validate and retrieve session
    func getSession(_ sessionId: String) throws -> Session {
        guard let session = sessions[sessionId] else {
            throw SessionError.invalidToken
        }
        
        // Check if session is still valid
        guard session.state == .active else {
            throw SessionError.sessionRevoked
        }
        
        // Check expiration
        guard session.expiresAt > Date() else {
            throw SessionError.sessionExpired
        }
        
        return session
    }
    
    /// Update session activity timestamp
    func updateActivity(_ sessionId: String) throws {
        guard var session = sessions[sessionId] else {
            throw SessionError.invalidToken
        }
        
        session = Session(
            id: session.id,
            userId: session.userId,
            tenantId: session.tenantId,
            token: session.token,
            refreshToken: session.refreshToken,
            state: session.state,
            createdAt: session.createdAt,
            expiresAt: session.expiresAt,
            lastActivityAt: Date(),
            ipAddress: session.ipAddress,
            userAgent: session.userAgent,
            deviceId: session.deviceId,
            scope: session.scope,
            riskScore: session.riskScore,
            isCompromised: session.isCompromised,
            deviceTrusted: session.deviceTrusted
        )
        
        sessions[sessionId] = session
    }
    
    /// Revoke session
    func revokeSession(_ sessionId: String) throws {
        guard var session = sessions[sessionId] else {
            throw SessionError.invalidToken
        }
        
        session = Session(
            id: session.id,
            userId: session.userId,
            tenantId: session.tenantId,
            token: session.token,
            refreshToken: session.refreshToken,
            state: .revoked,
            createdAt: session.createdAt,
            expiresAt: session.expiresAt,
            lastActivityAt: session.lastActivityAt,
            ipAddress: session.ipAddress,
            userAgent: session.userAgent,
            deviceId: session.deviceId,
            scope: session.scope,
            riskScore: session.riskScore,
            isCompromised: session.isCompromised,
            deviceTrusted: session.deviceTrusted
        )
        
        sessions[sessionId] = session
    }
    
    /// Refresh token
    func refreshToken(_ sessionId: String) async throws -> SessionToken {
        guard let session = sessions[sessionId] else {
            throw SessionError.invalidToken
        }
        
        let newToken = try tokenGenerator.generateToken()
        let newRefreshToken = try tokenGenerator.generateToken()
        let newExpiresAt = Date().addingTimeInterval(3600)
        
        var updatedSession = session
        updatedSession = Session(
            id: session.id,
            userId: session.userId,
            tenantId: session.tenantId,
            token: newToken,
            refreshToken: newRefreshToken,
            state: session.state,
            createdAt: session.createdAt,
            expiresAt: newExpiresAt,
            lastActivityAt: Date(),
            ipAddress: session.ipAddress,
            userAgent: session.userAgent,
            deviceId: session.deviceId,
            scope: session.scope,
            riskScore: session.riskScore,
            isCompromised: session.isCompromised,
            deviceTrusted: session.deviceTrusted
        )
        
        sessions[sessionId] = updatedSession
        
        return SessionToken(
            token: newToken,
            expiresAt: newExpiresAt,
            refreshToken: newRefreshToken,
            scope: session.scope,
            metadata: [
                "sessionId": sessionId,
                "userId": session.userId,
                "tenantId": session.tenantId
            ]
        )
    }
    
    /// Mark session as compromised
    func markAsCompromised(_ sessionId: String) throws {
        guard var session = sessions[sessionId] else {
            throw SessionError.invalidToken
        }
        
        session = Session(
            id: session.id,
            userId: session.userId,
            tenantId: session.tenantId,
            token: session.token,
            refreshToken: session.refreshToken,
            state: .revoked,
            createdAt: session.createdAt,
            expiresAt: session.expiresAt,
            lastActivityAt: session.lastActivityAt,
            ipAddress: session.ipAddress,
            userAgent: session.userAgent,
            deviceId: session.deviceId,
            scope: session.scope,
            riskScore: 1.0,
            isCompromised: true,
            deviceTrusted: false
        )
        
        sessions[sessionId] = session
    }
    
    /// Get all active sessions for user
    func getActiveSessions(userId: String, tenantId: String) -> [Session] {
        return sessions.values.filter { session in
            session.userId == userId &&
            session.tenantId == tenantId &&
            session.state == .active &&
            session.expiresAt > Date()
        }
    }
    
    /// Cleanup expired sessions
    func cleanupExpiredSessions() {
        let now = Date()
        sessions = sessions.filter { $0.value.expiresAt > now }
    }
}

/// Main Session Manager
actor SessionManager {
    private let store: SecureSessionStore
    private let tokenGenerator = TokenGenerator()
    private var cleanupTimer: Timer?
    
    init() async throws {
        self.store = try SecureSessionStore()
        startCleanupTimer()
    }
    
    /// Authenticate and create session
    func authenticate(
        userId: String,
        tenantId: String,
        ipAddress: String,
        userAgent: String,
        deviceId: String,
        permissions: [String]
    ) async throws -> SessionToken {
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
            metadata: [
                "sessionId": session.id,
                "userId": userId,
                "tenantId": tenantId
            ]
        )
    }
    
    /// Validate request
    func validateRequest(_ sessionId: String, tenantId: String) async throws {
        let session = try await store.getSession(sessionId)
        
        guard session.tenantId == tenantId else {
            throw SessionError.invalidTenant
        }
        
        try await store.updateActivity(sessionId)
    }
    
    /// Logout user
    func logout(_ sessionId: String) async throws {
        try await store.revokeSession(sessionId)
    }
    
    /// Start automatic cleanup of expired sessions
    private func startCleanupTimer() {
        cleanupTimer = Timer.scheduledTimer(withTimeInterval: 3600, repeats: true) { [weak self] _ in
            Task {
                await self?.store.cleanupExpiredSessions()
            }
        }
    }
    
    deinit {
        cleanupTimer?.invalidate()
    }
}
