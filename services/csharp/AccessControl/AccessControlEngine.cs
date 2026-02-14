using System;
using System.Collections.Generic;
using System.Collections.Concurrent;
using System.Linq;
using System.Security.Cryptography;
using System.Text;
using System.Threading.Tasks;
using System.Net;
using System.Net.Sockets;
using Microsoft.Extensions.Caching.Memory; // Requiere NuGet: Microsoft.Extensions.Caching.Memory

namespace WorkChain.Security.AccessControl
{
    // ---------------------------------------------------------
    // Enums & Interfaces
    // ---------------------------------------------------------

    public enum AccessDecision
    {
        Deny = 0,
        Challenge = 1,     // Requiere MFA o Step-up auth
        Permit = 2,
        PermitWithRestrictions = 3
    }

    public enum RiskLevel
    {
        Low = 0,
        Medium = 1,
        High = 2,
        Critical = 3
    }

    /// <summary>
    /// Interfaz para desacoplar el almacenamiento de logs (DB, File, SIEM)
    /// </summary>
    public interface IAuditLogger
    {
        Task LogAsync(AuditLog entry);
    }

    // ---------------------------------------------------------
    // Context & Entities
    // ---------------------------------------------------------

    public class AccessContext
    {
        public string UserId { get; set; }
        public string ResourceId { get; set; }
        public string ResourceTenantId { get; set; } // CRÍTICO: El tenant dueño del recurso
        public string Action { get; set; }
        public string ContextTenantId { get; set; }  // El tenant donde ocurre la acción
        
        public Dictionary<string, string> Attributes { get; set; } = new Dictionary<string, string>();
        public DateTime RequestTime { get; set; } = DateTime.UtcNow;
        public string IpAddress { get; set; }
        public string DeviceId { get; set; }
        public string SessionId { get; set; }

        public bool IsValid()
        {
            return !string.IsNullOrWhiteSpace(UserId) &&
                   !string.IsNullOrWhiteSpace(ResourceId) &&
                   !string.IsNullOrWhiteSpace(ContextTenantId);
        }
    }

    public class Role
    {
        public string Id { get; set; }
        public string TenantId { get; set; } // Role Scoping
        public string Name { get; set; }
        public List<Permission> Permissions { get; set; } = new List<Permission>();
        public List<string> DeniedActions { get; set; } = new List<string>(); // Explicit Deny
    }

    public class Permission
    {
        public string ResourcePattern { get; set; } // Soporta "order:*"
        public string Action { get; set; }
        public bool IsNegative { get; set; } // Si es true, es una regla DENY explícita
        public Dictionary<string, string> Conditions { get; set; } = new Dictionary<string, string>();
    }

    // ---------------------------------------------------------
    // Core Logic: Access Policy Engine
    // ---------------------------------------------------------

    public class AccessPolicy
    {
        // Almacenes thread-safe simulando DB en memoria
        private readonly ConcurrentDictionary<string, Role> _roles = new ConcurrentDictionary<string, Role>();
        
        // Key: "TenantId:UserId", Value: List of RoleIds
        private readonly ConcurrentDictionary<string, HashSet<string>> _userRoleMap = new ConcurrentDictionary<string, HashSet<string>>();

        public void RegisterRole(Role role)
        {
            _roles.AddOrUpdate(role.Id, role, (k, v) => role);
        }

        public void AssignRoleToUser(string userId, string roleId, string tenantId)
        {
            // Verificamos que el rol pertenezca al tenant antes de asignar (Seguridad)
            if (_roles.TryGetValue(roleId, out var role))
            {
                if (role.TenantId != tenantId && role.TenantId != "GLOBAL") 
                    throw new InvalidOperationException("Cross-tenant role assignment attempt detected.");
            }

            string key = $"{tenantId}:{userId}";
            _userRoleMap.AddOrUpdate(key, 
                new HashSet<string> { roleId }, 
                (k, list) => { list.Add(roleId); return list; });
        }

        /// <summary>
        /// Evaluación principal de políticas (Zero Trust)
        /// </summary>
        public bool Evaluate(AccessContext context, out string denyReason)
        {
            denyReason = string.Empty;

            // 1. Validación de Aislamiento de Inquilinos (Tenant Isolation)
            if (context.ResourceTenantId != context.ContextTenantId)
            {
                // Bloqueo duro: Un usuario en el contexto del Tenant A no puede tocar recursos del Tenant B
                denyReason = "Cross-Tenant Resource Access Violation";
                return false;
            }

            // 2. Obtener Roles del usuario EN ESTE TENANT ESPECÍFICO
            string userKey = $"{context.ContextTenantId}:{context.UserId}";
            if (!_userRoleMap.TryGetValue(userKey, out var roleIds) || roleIds.Count == 0)
            {
                denyReason = "User has no roles in this tenant";
                return false;
            }

            // 3. Resolución de Roles y Permisos
            var activeRoles = roleIds
                .Select(rid => _roles.TryGetValue(rid, out var r) ? r : null)
                .Where(r => r != null)
                .ToList();

            // 4. Check EXPLICIT DENY (Prioridad Máxima)
            foreach (var role in activeRoles)
            {
                if (role.DeniedActions.Contains(context.Action) || role.Permissions.Any(p => p.IsNegative && MatchesPermission(p, context)))
                {
                    denyReason = $"Explicit Deny in Role: {role.Name}";
                    return false;
                }
            }

            // 5. Check ALLOW (Permissive)
            bool allowed = false;
            foreach (var role in activeRoles)
            {
                if (role.Permissions.Any(p => !p.IsNegative && MatchesPermission(p, context)))
                {
                    allowed = true;
                    break; // Encontró un permiso válido, pero seguimos buscando DENYs si la lógica fuera más compleja
                }
            }

            if (!allowed) denyReason = "No matching permission found (Implicit Deny)";
            return allowed;
        }

        private bool MatchesPermission(Permission p, AccessContext ctx)
        {
            // Verificación de Recurso (soporta wildcards simples)
            bool resourceMatch = p.ResourcePattern == "*" || p.ResourcePattern == ctx.ResourceId;
            bool actionMatch = p.Action == "*" || p.Action == ctx.Action;

            if (!resourceMatch || !actionMatch) return false;

            // Evaluación de Condiciones Contextuales (ABAC)
            return EvaluateConditions(p.Conditions, ctx);
        }

        private bool EvaluateConditions(Dictionary<string, string> conditions, AccessContext ctx)
        {
            if (conditions == null || conditions.Count == 0) return true;

            foreach (var kvp in conditions)
            {
                switch (kvp.Key)
                {
                    case "IpRange":
                        if (!NetworkUtils.IsIpInRange(ctx.IpAddress, kvp.Value)) return false;
                        break;
                    case "TimeWindow": // Formato "HH:mm-HH:mm"
                        if (!TimeUtils.IsInWindow(ctx.RequestTime, kvp.Value)) return false;
                        break;
                    case "DeviceId":
                        if (ctx.DeviceId != kvp.Value) return false;
                        break;
                }
            }
            return true;
        }

        public RiskLevel CalculateRisk(AccessContext ctx)
        {
            int score = 0;
            
            // Ejemplo de heurística de riesgo
            if (string.IsNullOrEmpty(ctx.DeviceId)) score += 20; // Dispositivo desconocido
            if (ctx.RequestTime.Hour < 6 || ctx.RequestTime.Hour > 22) score += 10; // Horario inusual
            
            // En un entorno real, aquí consultaríamos ThreatIntel o historial de comportamiento
            
            if (score >= 50) return RiskLevel.High;
            if (score >= 20) return RiskLevel.Medium;
            return RiskLevel.Low;
        }
    }

    // ---------------------------------------------------------
    // Main Engine: Access Control Engine
    // ---------------------------------------------------------

    public class AccessControlEngine
    {
        private readonly AccessPolicy _policy;
        private readonly IMemoryCache _decisionCache;
        private readonly IAuditLogger _auditLogger;

        public AccessControlEngine(IMemoryCache cache, IAuditLogger logger)
        {
            _policy = new AccessPolicy();
            _decisionCache = cache ?? throw new ArgumentNullException(nameof(cache));
            _auditLogger = logger ?? throw new ArgumentNullException(nameof(logger));
        }

        // Métodos proxy para configurar políticas
        public void ConfigureRole(Role role) => _policy.RegisterRole(role);
        public void AssignUser(string user, string role, string tenant) => _policy.AssignRoleToUser(user, role, tenant);

        public async Task<AccessDecision> EvaluateAsync(AccessContext context)
        {
            if (!context.IsValid()) return AccessDecision.Deny;

            // Generar clave de caché determinista
            string cacheKey = ComputeCacheKey(context);

            // 1. Consultar Caché (Fast Path)
            if (_decisionCache.TryGetValue(cacheKey, out AccessDecision cachedDecision))
            {
                // Loguear acceso cacheado (puede ser sampling para no saturar I/O)
                await LogAsync(context, cachedDecision, "CacheHit");
                return cachedDecision;
            }

            // 2. Evaluar Políticas (Deep Path)
            bool isAllowed = _policy.Evaluate(context, out string denyReason);
            RiskLevel risk = _policy.CalculateRisk(context);

            AccessDecision decision;

            // 3. Matriz de Decisión basada en Riesgo
            if (!isAllowed)
            {
                decision = AccessDecision.Deny;
            }
            else if (risk == RiskLevel.High || risk == RiskLevel.Critical)
            {
                // Aunque tenga permiso, el riesgo es alto -> Challenge (MFA)
                decision = AccessDecision.Challenge;
            }
            else if (risk == RiskLevel.Medium)
            {
                decision = AccessDecision.PermitWithRestrictions;
            }
            else
            {
                decision = AccessDecision.Permit;
            }

            // 4. Guardar en Caché (TTL corto para seguridad dinámica)
            var cacheOptions = new MemoryCacheEntryOptions()
                .SetAbsoluteExpiration(TimeSpan.FromMinutes(5)) // TTL base
                .SetSlidingExpiration(TimeSpan.FromMinutes(2))  // Si se usa, se mantiene
                .SetSize(1); // Para limitar tamaño total de caché si se configura

            _decisionCache.Set(cacheKey, decision, cacheOptions);

            // 5. Auditoría
            await LogAsync(context, decision, string.IsNullOrEmpty(denyReason) ? "PolicyEval" : denyReason);

            return decision;
        }

        private string ComputeCacheKey(AccessContext ctx)
        {
            // Usamos Hash para clave compacta
            string rawKey = $"{ctx.ContextTenantId}|{ctx.UserId}|{ctx.ResourceId}|{ctx.Action}|{ctx.IpAddress}";
            using (var sha = SHA256.Create())
            {
                byte[] bytes = sha.ComputeHash(Encoding.UTF8.GetBytes(rawKey));
                return Convert.ToBase64String(bytes);
            }
        }

        private async Task LogAsync(AccessContext ctx, AccessDecision decision, string reason)
        {
            var log = new AuditLog
            {
                Id = Guid.NewGuid().ToString(),
                Timestamp = DateTime.UtcNow,
                TenantId = ctx.ContextTenantId,
                UserId = ctx.UserId,
                Action = ctx.Action,
                Resource = ctx.ResourceId,
                Decision = decision.ToString(),
                Reason = reason,
                IpAddress = ctx.IpAddress
            };
            await _auditLogger.LogAsync(log);
        }
    }

    // ---------------------------------------------------------
    // Utilities & Helpers (Network & Time)
    // ---------------------------------------------------------

    public static class NetworkUtils
    {
        public static bool IsIpInRange(string ipAddress, string cidrRange)
        {
            if (string.IsNullOrEmpty(ipAddress) || string.IsNullOrEmpty(cidrRange)) return false;

            try
            {
                string[] parts = cidrRange.Split('/');
                if (parts.Length != 2) return ipAddress == cidrRange; // Comparación simple si no es CIDR

                IPAddress ip = IPAddress.Parse(ipAddress);
                IPAddress baseIp = IPAddress.Parse(parts[0]);
                int bits = int.Parse(parts[1]);

                return IsInSubnet(ip, baseIp, bits);
            }
            catch
            {
                return false; // Fail secure on parse error
            }
        }

        private static bool IsInSubnet(IPAddress address, IPAddress subnet, int maskLength)
        {
            // Implementación robusta de comprobación de bits IP
            // Nota: Para producción, usar librerías como IPNetwork2 para soporte IPv6 completo
            byte[] ipBytes = address.GetAddressBytes();
            byte[] subnetBytes = subnet.GetAddressBytes();

            if (ipBytes.Length != subnetBytes.Length) return false;

            // Check full bytes
            int byteCount = maskLength / 8;
            for (int i = 0; i < byteCount; i++)
            {
                if (ipBytes[i] != subnetBytes[i]) return false;
            }

            // Check remaining bits
            int remainder = maskLength % 8;
            if (remainder > 0)
            {
                byte mask = (byte)(0xFF << (8 - remainder));
                if ((ipBytes[byteCount] & mask) != (subnetBytes[byteCount] & mask)) return false;
            }

            return true;
        }
    }

    public static class TimeUtils
    {
        public static bool IsInWindow(DateTime requestTime, string window)
        {
            try
            {
                // Formato esperado: "09:00-17:00"
                var parts = window.Split('-');
                var start = TimeSpan.Parse(parts[0]);
                var end = TimeSpan.Parse(parts[1]);
                var now = requestTime.TimeOfDay;

                return now >= start && now <= end;
            }
            catch
            {
                return false; // Fail secure
            }
        }
    }

    public class AuditLog
    {
        public string Id { get; set; }
        public DateTime Timestamp { get; set; }
        public string TenantId { get; set; }
        public string UserId { get; set; }
        public string Action { get; set; }
        public string Resource { get; set; }
        public string Decision { get; set; }
        public string Reason { get; set; }
        public string IpAddress { get; set; }
    }
}