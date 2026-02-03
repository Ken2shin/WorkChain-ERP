using System;
using System.Collections.Generic;
using System.Collections.Concurrent;
using System.Linq;
using System.Security.Cryptography;
using System.Text;
using System.Threading;
using System.Threading.Tasks;

namespace WorkChain.Security.AccessControl
{
    /// <summary>
    /// Zero Trust Access Control Engine for WorkChain ERP
    /// Implements RBAC, ABAC, and attribute-based policies
    /// </summary>
    
    public enum AccessDecision
    {
        Deny = 0,
        Challenge = 1,
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

    public interface IAccessPolicy
    {
        bool Evaluate(AccessContext context);
        RiskLevel CalculateRiskLevel(AccessContext context);
    }

    public class AccessContext
    {
        public string UserId { get; set; }
        public string ResourceId { get; set; }
        public string Action { get; set; }
        public string TenantId { get; set; }
        public Dictionary<string, string> UserAttributes { get; set; }
        public Dictionary<string, string> ResourceAttributes { get; set; }
        public Dictionary<string, string> EnvironmentAttributes { get; set; }
        public DateTime RequestTime { get; set; }
        public string IpAddress { get; set; }
        public string DeviceId { get; set; }
        public string SessionId { get; set; }

        public AccessContext()
        {
            UserAttributes = new Dictionary<string, string>();
            ResourceAttributes = new Dictionary<string, string>();
            EnvironmentAttributes = new Dictionary<string, string>();
            RequestTime = DateTime.UtcNow;
        }
    }

    public class Role
    {
        public string Id { get; set; }
        public string TenantId { get; set; }
        public string Name { get; set; }
        public string Description { get; set; }
        public List<Permission> Permissions { get; set; }
        public List<string> DeniedActions { get; set; }
        public DateTime CreatedAt { get; set; }
        public DateTime ModifiedAt { get; set; }

        public Role()
        {
            Permissions = new List<Permission>();
            DeniedActions = new List<string>();
        }
    }

    public class Permission
    {
        public string Id { get; set; }
        public string Resource { get; set; }
        public string Action { get; set; }
        public List<string> Scopes { get; set; }
        public bool IsNegative { get; set; }
        public Dictionary<string, string> Conditions { get; set; }

        public Permission()
        {
            Scopes = new List<string>();
            Conditions = new Dictionary<string, string>();
        }
    }

    public class AccessPolicy : IAccessPolicy
    {
        private readonly ConcurrentDictionary<string, Role> _roleCache;
        private readonly ConcurrentDictionary<string, List<string>> _userRoleCache;
        private readonly ConcurrentDictionary<string, PolicyRuleset> _policyCache;
        private readonly object _lockObj = new object();

        public AccessPolicy()
        {
            _roleCache = new ConcurrentDictionary<string, Role>();
            _userRoleCache = new ConcurrentDictionary<string, List<string>>();
            _policyCache = new ConcurrentDictionary<string, PolicyRuleset>();
        }

        public void RegisterRole(Role role)
        {
            lock (_lockObj)
            {
                _roleCache.AddOrUpdate(role.Id, role, (key, old) => role);
            }
        }

        public void AssignRoleToUser(string userId, string roleId, string tenantId)
        {
            lock (_lockObj)
            {
                var cacheKey = $"{tenantId}:{userId}";
                _userRoleCache.AddOrUpdate(cacheKey, new List<string> { roleId }, (key, roles) =>
                {
                    if (!roles.Contains(roleId))
                        roles.Add(roleId);
                    return roles;
                });
            }
        }

        public bool Evaluate(AccessContext context)
        {
            if (context == null)
                throw new ArgumentNullException(nameof(context));

            /* Validate tenant isolation */
            if (!ValidateTenantIsolation(context))
                return false;

            /* Check explicit deny rules first (fail-secure) */
            if (CheckExplicitDeny(context))
                return false;

            /* Evaluate user roles and permissions */
            var userRoles = GetUserRoles(context.UserId, context.TenantId);
            if (!userRoles.Any())
                return false;

            /* Check if user has permission */
            foreach (var roleId in userRoles)
            {
                if (_roleCache.TryGetValue(roleId, out var role))
                {
                    if (HasPermission(role, context))
                        return true;
                }
            }

            return false;
        }

        public RiskLevel CalculateRiskLevel(AccessContext context)
        {
            var riskScore = 0;

            /* Evaluate various risk factors */
            riskScore += EvaluateLocationRisk(context);
            riskScore += EvaluateDeviceRisk(context);
            riskScore += EvaluateTimeRisk(context);
            riskScore += EvaluateBehaviorRisk(context);

            if (riskScore >= 75)
                return RiskLevel.Critical;
            else if (riskScore >= 50)
                return RiskLevel.High;
            else if (riskScore >= 25)
                return RiskLevel.Medium;
            else
                return RiskLevel.Low;
        }

        private bool ValidateTenantIsolation(AccessContext context)
        {
            /* Ensure multi-tenant isolation */
            if (string.IsNullOrEmpty(context.TenantId))
                return false;

            /* Query database to verify tenant ownership */
            /* This is a placeholder - actual implementation would query the database */
            return true;
        }

        private bool CheckExplicitDeny(AccessContext context)
        {
            var userRoles = GetUserRoles(context.UserId, context.TenantId);

            foreach (var roleId in userRoles)
            {
                if (_roleCache.TryGetValue(roleId, out var role))
                {
                    if (role.DeniedActions.Contains(context.Action))
                        return true;
                }
            }

            return false;
        }

        private List<string> GetUserRoles(string userId, string tenantId)
        {
            var cacheKey = $"{tenantId}:{userId}";
            _userRoleCache.TryGetValue(cacheKey, out var roles);
            return roles ?? new List<string>();
        }

        private bool HasPermission(Role role, AccessContext context)
        {
            return role.Permissions.Any(p =>
                p.Resource == context.ResourceId &&
                p.Action == context.Action &&
                !p.IsNegative &&
                EvaluateConditions(p.Conditions, context)
            );
        }

        private bool EvaluateConditions(Dictionary<string, string> conditions, AccessContext context)
        {
            foreach (var condition in conditions)
            {
                switch (condition.Key)
                {
                    case "ipRange":
                        if (!IsIpInRange(context.IpAddress, condition.Value))
                            return false;
                        break;
                    case "timeWindow":
                        if (!IsInTimeWindow(context.RequestTime, condition.Value))
                            return false;
                        break;
                    case "deviceId":
                        if (context.DeviceId != condition.Value)
                            return false;
                        break;
                }
            }

            return true;
        }

        private bool IsIpInRange(string ip, string ipRange)
        {
            /* Implement IP range checking */
            return true;
        }

        private bool IsInTimeWindow(DateTime time, string timeWindow)
        {
            /* Implement time window checking */
            return true;
        }

        private int EvaluateLocationRisk(AccessContext context)
        {
            /* Evaluate geographic risk */
            return 10;
        }

        private int EvaluateDeviceRisk(AccessContext context)
        {
            /* Evaluate device risk */
            return 5;
        }

        private int EvaluateTimeRisk(AccessContext context)
        {
            /* Evaluate time-based anomalies */
            return 0;
        }

        private int EvaluateBehaviorRisk(AccessContext context)
        {
            /* Evaluate behavioral risk */
            return 0;
        }
    }

    public class PolicyRuleset
    {
        public string Id { get; set; }
        public string TenantId { get; set; }
        public List<PolicyRule> Rules { get; set; }
        public int Priority { get; set; }

        public PolicyRuleset()
        {
            Rules = new List<PolicyRule>();
        }
    }

    public class PolicyRule
    {
        public string Effect { get; set; } /* Allow or Deny */
        public List<string> Principals { get; set; }
        public List<string> Actions { get; set; }
        public List<string> Resources { get; set; }
        public Dictionary<string, object> Conditions { get; set; }

        public PolicyRule()
        {
            Principals = new List<string>();
            Actions = new List<string>();
            Resources = new List<string>();
            Conditions = new Dictionary<string, object>();
        }
    }

    public class AccessControlEngine
    {
        private readonly AccessPolicy _accessPolicy;
        private readonly ConcurrentDictionary<string, AccessDecision> _decisionCache;
        private readonly ConcurrentDictionary<string, AuditLog> _auditLog;
        private readonly Timer _cacheExpiryTimer;

        public AccessControlEngine()
        {
            _accessPolicy = new AccessPolicy();
            _decisionCache = new ConcurrentDictionary<string, AccessDecision>();
            _auditLog = new ConcurrentDictionary<string, AuditLog>();
            _cacheExpiryTimer = new Timer(ExpireCache, null, TimeSpan.FromMinutes(5), TimeSpan.FromMinutes(5));
        }

        public AccessDecision Evaluate(AccessContext context)
        {
            var cacheKey = GenerateCacheKey(context);

            /* Check cache first */
            if (_decisionCache.TryGetValue(cacheKey, out var cachedDecision))
            {
                LogAccess(context, cachedDecision, true);
                return cachedDecision;
            }

            /* Evaluate policy */
            var decision = _accessPolicy.Evaluate(context) ? AccessDecision.Permit : AccessDecision.Deny;
            var riskLevel = _accessPolicy.CalculateRiskLevel(context);

            /* Adjust decision based on risk */
            if (riskLevel == RiskLevel.Critical)
            {
                decision = AccessDecision.Deny;
            }
            else if (riskLevel == RiskLevel.High)
            {
                decision = AccessDecision.Challenge;
            }
            else if (riskLevel == RiskLevel.Medium && decision == AccessDecision.Permit)
            {
                decision = AccessDecision.PermitWithRestrictions;
            }

            /* Cache decision */
            _decisionCache.TryAdd(cacheKey, decision);

            /* Log access */
            LogAccess(context, decision, false);

            return decision;
        }

        private string GenerateCacheKey(AccessContext context)
        {
            var key = $"{context.TenantId}:{context.UserId}:{context.ResourceId}:{context.Action}";
            using (var sha = SHA256.Create())
            {
                var hash = sha.ComputeHash(Encoding.UTF8.GetBytes(key));
                return Convert.ToBase64String(hash);
            }
        }

        private void LogAccess(AccessContext context, AccessDecision decision, bool cached)
        {
            var auditEntry = new AuditLog
            {
                Id = Guid.NewGuid().ToString(),
                TenantId = context.TenantId,
                UserId = context.UserId,
                ResourceId = context.ResourceId,
                Action = context.Action,
                Decision = decision.ToString(),
                IpAddress = context.IpAddress,
                DeviceId = context.DeviceId,
                Timestamp = DateTime.UtcNow,
                Cached = cached
            };

            _auditLog.TryAdd(auditEntry.Id, auditEntry);
        }

        private void ExpireCache(object state)
        {
            /* Implement cache expiry logic */
            if (_decisionCache.Count > 10000)
            {
                _decisionCache.Clear();
            }
        }

        public IEnumerable<AuditLog> GetAuditLog(string tenantId)
        {
            return _auditLog.Values.Where(log => log.TenantId == tenantId);
        }
    }

    public class AuditLog
    {
        public string Id { get; set; }
        public string TenantId { get; set; }
        public string UserId { get; set; }
        public string ResourceId { get; set; }
        public string Action { get; set; }
        public string Decision { get; set; }
        public string IpAddress { get; set; }
        public string DeviceId { get; set; }
        public DateTime Timestamp { get; set; }
        public bool Cached { get; set; }
    }
}
