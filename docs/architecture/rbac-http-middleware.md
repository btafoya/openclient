# RBAC HTTP Middleware Layer

## Overview

This document describes **RBAC Layer 2** in the openclient security architecture: HTTP middleware-level authorization that blocks unauthorized requests before they reach controllers.

## Architecture Position

```
HTTP Request Flow:
1. Network → Web Server
2. Web Server → CodeIgniter Framework
3. Framework → LoginFilter (authentication check)
4. Framework → RBACFilter (authorization check) ← THIS LAYER
5. RBACFilter → Controller (if authorized)
6. Controller → Business Logic → Database (RLS enforces at DB level)
```

**Defense in Depth Layers**:
- **Layer 1** (Database): PostgreSQL RLS - Last line of defense, always active
- **Layer 2** (HTTP): RBACFilter - Blocks early, logs violations ← THIS DOCUMENT
- **Layer 3** (Service): Authorization Guards - Fine-grained business logic checks
- **Layer 4** (View): UI - Hide unauthorized UI elements (user experience)

## Components

### 1. RBACFilter (`app/Filters/RBACFilter.php`)

HTTP middleware that runs on every request after authentication.

**Purpose**:
- Block unauthorized routes early in request lifecycle
- Prevent unnecessary database queries for forbidden actions
- Create audit trail of access attempts
- Provide clear user feedback for authorization failures

**Authorization Rules**:

| User Role | Financial Routes | Admin Routes | Client Routes | Notes |
|-----------|------------------|--------------|---------------|-------|
| Owner | ✅ Allow | ✅ Allow | ✅ Allow | Superuser - full access |
| Agency | ✅ Allow | ❌ Block | ✅ Allow | Must have agency_id |
| Direct Client | ✅ Allow | ❌ Block | ✅ Allow | Must have agency_id |
| End Client | ❌ Block | ❌ Block | ✅ Allow | Limited access |

**Protected Route Categories**:

#### Financial Routes (End Client Blocked)
- `/invoices` - Invoice management
- `/quotes` - Quote generation
- `/billing` - Billing information
- `/payments` - Payment processing
- `/reports/financial` - Financial reports

**Rationale**: End Clients represent direct customers who shouldn't see invoices or financial details (they're managed by agencies or direct client accounts).

#### Admin Routes (Owner Only)
- `/admin` - Admin dashboard
- `/settings` - System settings
- `/users` - User management
- `/agencies` - Agency management

**Rationale**: Only platform Owner should manage system-wide configuration and user accounts.

### 2. SecurityLogger (`app/Helpers/SecurityLogger.php`)

Centralized security audit logging helper.

**Purpose**:
- Track all authorization failures
- Create forensic audit trail
- Support compliance requirements (SOC 2, GDPR, etc.)
- Enable security monitoring and alerting

**Log Event Types**:

1. **ACCESS_DENIED** - Route authorization failure
2. **AUTHENTICATION_FAILURE** - Login attempt failed
3. **PRIVILEGE_ESCALATION_ATTEMPT** - User tried higher-privilege action
4. **DATA_ACCESS** - Sensitive data accessed (audit trail)
5. **SUSPICIOUS_ACTIVITY** - Detected potential malicious behavior

**Log Format**: JSON Lines (one JSON object per line)

```json
{
  "timestamp": "2025-12-08 14:23:45",
  "event": "ACCESS_DENIED",
  "user_id": "uuid",
  "user_email": "user@example.com",
  "user_role": "end_client",
  "agency_id": "agency-uuid",
  "attempted_resource": "/invoices",
  "reason": "End Client attempted to access financial route",
  "ip_address": "192.168.1.100",
  "user_agent": "Mozilla/5.0...",
  "request_method": "GET"
}
```

**Log Files**: `writable/logs/security-YYYY-MM-DD.log` (daily rotation)

### 3. Filter Registration (`app/Config/Filters.php`)

RBACFilter is registered as a global filter that runs on all routes except auth pages and dashboard.

```php
public array $aliases = [
    'login' => \App\Filters\LoginFilter::class,
    'rbac' => \App\Filters\RBACFilter::class,
];

public array $globals = [
    'before' => [
        'login' => ['except' => ['auth/*', '/']],
        'rbac' => ['except' => ['auth/*', '/', '/dashboard']],
    ],
];
```

**Execution Order**:
1. `LoginFilter` runs first - ensures user is authenticated
2. `RBACFilter` runs second - checks if authenticated user is authorized
3. Controller executes - only if both filters pass

**Exceptions**:
- `/auth/*` - Login pages (no auth required)
- `/` - Homepage (public)
- `/dashboard` - Dashboard home (allowed for all authenticated users)

## Request Flow Examples

### Example 1: End Client Attempts /invoices

```
1. Request: GET /invoices
2. LoginFilter: ✅ User authenticated (end_client)
3. RBACFilter:
   - Check user role: "end_client"
   - Check route: "/invoices" in FINANCIAL_ROUTES
   - Decision: BLOCK (end_client + financial route)
   - Action: Redirect to /dashboard with error message
   - Log: Write ACCESS_DENIED to security log
4. Result: 302 Redirect to /dashboard, no controller execution
```

### Example 2: Agency User Attempts /admin

```
1. Request: GET /admin/settings
2. LoginFilter: ✅ User authenticated (agency)
3. RBACFilter:
   - Check user role: "agency" (not "owner")
   - Check route: "/admin/settings" in ADMIN_ROUTES
   - Decision: BLOCK (only owner can access admin)
   - Action: Redirect to /dashboard with error message
   - Log: Write ACCESS_DENIED to security log
4. Result: 302 Redirect to /dashboard, no controller execution
```

### Example 3: Owner Accesses /admin

```
1. Request: GET /admin/settings
2. LoginFilter: ✅ User authenticated (owner)
3. RBACFilter:
   - Check user role: "owner"
   - Check route: "/admin/settings" in ADMIN_ROUTES
   - Decision: ALLOW (owner can access admin)
   - Action: Continue to controller
4. Result: Controller executes, page rendered
```

### Example 4: Agency User WITHOUT agency_id

```
1. Request: GET /clients
2. LoginFilter: ✅ User authenticated (agency)
3. RBACFilter:
   - Check user role: "agency"
   - Check agency_id: NULL (not assigned)
   - Decision: BLOCK (agency users must have agency assignment)
   - Action: Redirect to /dashboard with error
   - Log: Write warning to application log
4. Result: 302 Redirect to /dashboard
```

## Security Properties

### 1. Early Blocking

**Benefit**: Unauthorized requests blocked at HTTP layer, before:
- Controller code execution
- Business logic processing
- Database queries
- Expensive operations

**Impact**: Reduces attack surface and prevents resource waste on unauthorized attempts.

### 2. Audit Trail

Every authorization failure is logged with:
- Who attempted (user ID, email, role)
- What was attempted (resource URI)
- When (timestamp)
- From where (IP address, user agent)
- Why denied (human-readable reason)

**Use Cases**:
- Security monitoring and alerting
- Incident investigation and forensics
- Compliance audits (SOC 2, GDPR, etc.)
- User behavior analysis

### 3. Defense in Depth

HTTP middleware works with other layers:

| Layer | Bypass Scenario | Protection Result |
|-------|----------------|-------------------|
| HTTP (RBACFilter) | Attacker bypasses | Database RLS still enforces |
| Database (RLS) | Bug in RLS policies | HTTP middleware blocks |
| Service (Guards) | Controller called directly | HTTP and DB both enforce |

**Result**: Multiple independent enforcement layers prevent single points of failure.

### 4. Role Validation

**Agency Users** require `agency_id`:
- Prevents orphaned agency accounts from accessing data
- Forces proper agency assignment before access
- Database RLS also requires agency_id for queries

**Direct Clients** require `agency_id`:
- Direct Clients belong to specific agencies
- Enforces proper account setup
- Aligns with RLS agency isolation

### 5. Clear User Feedback

Users receive specific, actionable error messages:
- "You do not have permission to access financial features."
- "You do not have permission to access admin features."
- "Your account is not assigned to an agency. Please contact the administrator."

**Benefits**:
- Reduces support requests (users understand why blocked)
- Prevents confusion (clear explanation of limitation)
- Professional UX (not generic "Access Denied")

## Performance Considerations

### Minimal Overhead

RBACFilter is lightweight:
- Simple string comparisons (route matching)
- Session variable checks (already in memory)
- No database queries
- No expensive operations

**Performance Impact**: <1ms per request

### Early Exit

If authorization fails:
- Request stops immediately at middleware
- No controller instantiation
- No database connections
- No business logic execution

**Benefit**: Failed authorization attempts are even faster than successful ones.

### Log File I/O

Security logs use `FILE_APPEND | LOCK_EX`:
- Append-only writes (fast)
- File locking prevents corruption
- Daily rotation limits file size
- JSON lines format (easy parsing)

**Log File Size**: ~200-500 bytes per event, ~10MB per day at 50K events/day

## Monitoring and Alerting

### Security Log Analysis

Use SecurityLogger helper methods:

```php
// Get events by type
$denials = SecurityLogger::getEventsByType('ACCESS_DENIED', $days = 7, $limit = 100);

// Get events for specific user
$userEvents = SecurityLogger::getUserSecurityEvents($userId, $days = 30);

// Parse entire day's log
$events = SecurityLogger::parseSecurityLog('2025-12-08');
```

### Alert Triggers

Monitor for suspicious patterns:

1. **Repeated Access Denials**: User hitting forbidden routes repeatedly
   - Threshold: >5 denials in 5 minutes
   - Action: Alert security team, possible attack

2. **Privilege Escalation Attempts**: Agency user trying admin routes
   - Threshold: Any admin route attempt by non-owner
   - Action: Alert security team immediately

3. **Account Without Agency**: Agency/Direct Client users without agency_id
   - Threshold: Any attempt
   - Action: Alert administrators (account misconfiguration)

### Log Rotation

Security logs rotate daily:
- File: `security-2025-12-08.log`
- Next day: New file `security-2025-12-09.log`
- Old logs: Compress and archive after 30 days
- Retention: 1 year (compliance requirement)

## Testing

### Verification Script

`scripts/verify_rbac_middleware.php` checks:
- ✅ RBACFilter file exists
- ✅ SecurityLogger helper exists
- ✅ Filter registered in Filters.php
- ✅ Filter implements FilterInterface
- ✅ Has before() and after() methods
- ✅ FINANCIAL_ROUTES defined
- ✅ ADMIN_ROUTES defined
- ✅ All protected routes configured
- ✅ Role-based restriction logic present
- ✅ Security logging integrated
- ✅ Log directory writable

**Running Verification**:
```bash
php scripts/verify_rbac_middleware.php
```

**Expected Output**: ✅ All critical checks passed

### Integration Tests

`tests/Integration/RBAC/HttpMiddlewareTest.php` tests:
- End Client blocked from financial routes
- Agency blocked from admin routes
- Direct Client blocked from admin routes
- Owner can access all routes
- Agency users must have agency_id
- Direct Clients must have agency_id
- Security logging records all denials
- Multiple denials logged separately

**Note**: Full integration tests require CodeIgniter framework bootstrap. Verification script provides structural validation.

## Troubleshooting

### Problem: Filter Not Running

**Symptoms**: Users can access unauthorized routes

**Causes**:
1. Filter not registered in `Filters.php`
2. Route in exception list
3. Filter disabled in environment

**Fix**:
```bash
# Verify registration
php scripts/verify_rbac_middleware.php

# Check Filters.php
grep -n "rbac" app/Config/Filters.php

# Ensure not in exceptions
# Check $globals['before']['rbac']['except']
```

### Problem: Security Logs Not Created

**Symptoms**: No log files in `writable/logs/`

**Causes**:
1. Directory doesn't exist
2. Directory not writable
3. Filter not executing
4. Wrong log path

**Fix**:
```bash
# Create directory
mkdir -p writable/logs
chmod 755 writable/logs

# Test log writing
php -r "file_put_contents('writable/logs/test.log', 'test');"

# Check log path constant
grep WRITEPATH app/Config/Constants.php
```

### Problem: All Users Blocked

**Symptoms**: Even Owner can't access admin

**Causes**:
1. Session not set correctly
2. User role not in session
3. Filter logic error

**Debug**:
```php
// Add to RBACFilter::before()
log_message('debug', 'RBAC Filter: ' . json_encode([
    'uri' => $uri,
    'user' => $user,
    'role' => $user['role'] ?? 'NO ROLE',
]));
```

### Problem: Wrong Users Allowed

**Symptoms**: End Clients accessing invoices

**Causes**:
1. Role check logic inverted
2. Wrong route in exception list
3. Filter bypassed

**Verify**:
```bash
# Check route protection
grep -A 5 "FINANCIAL_ROUTES" app/Filters/RBACFilter.php

# Check role logic
grep -A 10 "'end_client'" app/Filters/RBACFilter.php
```

## Future Extensions

### Dynamic Route Protection

Current: Routes hard-coded in filter constants
Future: Load from database or configuration file

**Benefits**:
- No code changes for new protected routes
- Per-agency custom permissions
- Dynamic permission management UI

**Implementation**:
```php
// Load from database
$protectedRoutes = ProtectedRouteModel::getAllRoutes();

// Load from config
$protectedRoutes = config('RBAC')->protectedRoutes;
```

### Permission-Based Authorization

Current: Role-based (owner, agency, end_client, direct_client)
Future: Permission-based (invoice.view, invoice.create, etc.)

**Benefits**:
- Fine-grained control
- Custom roles (manager, accountant, sales)
- Permission inheritance

**Example**:
```php
if (!$user->hasPermission('invoice.view')) {
    return $this->denyAccess('Requires invoice.view permission');
}
```

### IP Whitelisting

**Use Case**: Restrict admin access to office IPs only

**Implementation**:
```php
private const ADMIN_IP_WHITELIST = [
    '192.168.1.0/24',
    '10.0.0.0/8',
];

if ($role === 'owner' && !$this->isIpWhitelisted($_SERVER['REMOTE_ADDR'])) {
    return $this->denyAccess('Admin access restricted to office network');
}
```

### Time-Based Access

**Use Case**: Restrict sensitive operations to business hours

**Implementation**:
```php
if ($route === '/payments/process' && !$this->isDuringBusinessHours()) {
    return $this->denyAccess('Payment processing only available 9AM-5PM EST');
}
```

## Integration with Other Systems

### SIEM Integration

Export security logs to SIEM (Security Information and Event Management):

```bash
# Parse JSON logs and forward to SIEM
tail -f writable/logs/security-*.log | jq . | send-to-siem
```

### Slack Alerts

Alert on critical events:

```php
if ($event === 'PRIVILEGE_ESCALATION_ATTEMPT') {
    SlackNotifier::sendAlert(
        channel: '#security-alerts',
        message: "🚨 Privilege escalation attempt by {$user['email']}"
    );
}
```

### Metrics Dashboard

Track authorization metrics:
- Total access denials per day
- Denials by user role
- Denials by route
- Peak denial times

## Compliance Considerations

### SOC 2 Compliance

**Requirement**: Access control and audit logging

**Satisfied By**:
- ✅ Role-based access control (RBACFilter)
- ✅ Audit trail of all authorization failures (SecurityLogger)
- ✅ Immutable append-only logs
- ✅ Tamper-evident log format (JSON lines with timestamps)

### GDPR Compliance

**Requirement**: Data access logging for user rights

**Satisfied By**:
- ✅ SecurityLogger::logDataAccess() for sensitive data
- ✅ Per-user event retrieval (getUserSecurityEvents)
- ✅ Log retention policy (1 year)

### PCI DSS Compliance

**Requirement**: Restrict access to cardholder data

**Satisfied By**:
- ✅ Payment routes protected (FINANCIAL_ROUTES)
- ✅ Access attempts logged
- ✅ Failed access attempts blocked and alerted

## References

- **RBAC Layer 1 (Database)**: docs/architecture/rbac-database.md
- **RBAC Layer 3 (Service)**: docs/architecture/rbac-service-guards.md (Week 11-12)
- **CodeIgniter Filters**: https://codeigniter.com/user_guide/incoming/filters.html
- **Security Logging Best Practices**: OWASP Logging Cheat Sheet

## Summary

HTTP Middleware RBAC (Layer 2) provides:
- ✅ Early blocking of unauthorized requests
- ✅ Comprehensive security audit logging
- ✅ Clear user feedback for authorization failures
- ✅ Defense in depth with database RLS
- ✅ Minimal performance overhead
- ✅ Compliance-ready audit trail
- ✅ Easy to extend and customize

**Result**: Robust, auditable authorization layer that blocks threats early while maintaining excellent user experience.
