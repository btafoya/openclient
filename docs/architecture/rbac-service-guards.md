# RBAC Layer 3: Service-Level Authorization Guards

## Overview

This document describes RBAC Layer 3 - the service-level authorization guards that provide fine-grained permission checks at the business logic layer. These guards implement resource-specific authorization rules and work in conjunction with Layer 1 (Database RLS) and Layer 2 (HTTP Middleware).

## Architecture Position

```
┌─────────────────────────────────────────────────────────────┐
│                    HTTP Request Flow                         │
├─────────────────────────────────────────────────────────────┤
│                                                               │
│  1. Authentication (LoginFilter)                             │
│     ↓                                                         │
│  2. HTTP Authorization (RBACFilter) ← Layer 2               │
│     ↓                                                         │
│  3. Controller Logic                                         │
│     ↓                                                         │
│  4. Authorization Guards ← Layer 3 (THIS DOCUMENT)          │
│     ↓                                                         │
│  5. Business Logic / Model Operations                        │
│     ↓                                                         │
│  6. Database RLS Enforcement ← Layer 1                      │
│                                                               │
└─────────────────────────────────────────────────────────────┘
```

### Defense-in-Depth Strategy

**Layer 1: Database RLS** (Weeks 7-8)
- PostgreSQL Row-Level Security policies
- Filters queries by agency_id at SQL level
- Last line of defense, cannot be bypassed

**Layer 2: HTTP Middleware** (Weeks 9-10)
- Route-level authorization
- Blocks End Clients from financial routes
- Blocks non-Owners from admin routes
- Prevents unauthorized requests from reaching controllers

**Layer 3: Service Guards** (Weeks 11-12 - THIS LAYER)
- Fine-grained resource-specific authorization
- Checks permissions before business logic
- Handles complex authorization rules
- Provides permission summaries for UI

## Authorization Guard Pattern

### Interface Contract

All authorization guards implement `AuthorizationGuardInterface`:

```php
interface AuthorizationGuardInterface
{
    /**
     * Check if user can view a specific resource
     */
    public function canView(array $user, $resource): bool;

    /**
     * Check if user can create new resources of this type
     */
    public function canCreate(array $user): bool;

    /**
     * Check if user can edit a specific resource
     */
    public function canEdit(array $user, $resource): bool;

    /**
     * Check if user can delete a specific resource
     */
    public function canDelete(array $user, $resource): bool;
}
```

### Guard Implementations

The openclient platform implements three authorization guards:

1. **InvoiceGuard**: `app/Domain/Invoices/Authorization/InvoiceGuard.php`
2. **ProjectGuard**: `app/Domain/Projects/Authorization/ProjectGuard.php`
3. **ClientGuard**: `app/Domain/Clients/Authorization/ClientGuard.php`

## Authorization Rules by Resource

### Invoice Authorization (InvoiceGuard)

| Role | canCreate | canView | canEdit | canDelete |
|------|-----------|---------|---------|-----------|
| **Owner** | ✅ | ✅ All invoices | ✅ All invoices | ✅ All invoices |
| **Agency** | ✅ | ✅ Own agency | ✅ Own agency | ❌ |
| **Direct Client** | ❌ | ✅ Assigned clients | ❌ | ❌ |
| **End Client** | ❌ | ❌ Financial restriction | ❌ | ❌ |

**Special Rules**:
- End Clients are completely blocked from invoice access (financial restriction)
- Direct Clients can only view invoices for clients they're assigned to
- Direct Client assignment checked via `client_users` table
- Agency access automatically filtered by RLS at database level

**Code Example**:
```php
$guard = new InvoiceGuard();
$user = session()->get('user');
$invoice = $invoiceModel->find($id);

if (!$guard->canView($user, $invoice)) {
    return redirect()->to('/invoices')
        ->with('error', 'You do not have permission to view this invoice.');
}
```

### Project Authorization (ProjectGuard)

| Role | canCreate | canView | canEdit | canDelete | canManageMembers |
|------|-----------|---------|---------|-----------|------------------|
| **Owner** | ✅ | ✅ All projects | ✅ All projects | ✅ All projects | ✅ All projects |
| **Agency** | ✅ | ✅ Own agency | ✅ Own agency | ❌ | ✅ Own agency |
| **Direct Client** | ❌ | ✅ If member | ❌ | ❌ | ❌ |
| **End Client** | ❌ | ✅ If member | ❌ | ❌ | ❌ |
| **Project Manager** | — | ✅ | ✅ | ❌ | ✅ |

**Special Rules**:
- Projects use member-based access beyond agency boundaries
- Any user assigned as a project member can view the project
- Project managers (role in `project_members` table) can edit and manage members
- Membership checked via `project_members` table with `is_active = true`

**Code Example**:
```php
$guard = new ProjectGuard();
$user = session()->get('user');
$project = $projectModel->find($id);

if (!$guard->canEdit($user, $project)) {
    SecurityLogger::logAccessDenied($user, "Project #{$id}", 'Edit attempt blocked');
    return redirect()->back()->with('error', 'Access denied.');
}
```

### Client Authorization (ClientGuard)

| Role | canCreate | canView | canEdit | canDelete | canManageUsers |
|------|-----------|---------|---------|-----------|----------------|
| **Owner** | ✅ | ✅ All clients | ✅ All clients | ✅ All clients | ✅ All clients |
| **Agency** | ✅ | ✅ Own agency | ✅ Own agency | ❌ | ✅ Own agency |
| **Direct Client** | ❌ | ✅ Own record | ❌ | ❌ | ❌ |
| **End Client** | ❌ | ✅ Own record | ❌ | ❌ | ❌ |

**Special Rules**:
- Direct Clients and End Clients can only view their own client record
- Client assignment checked via `client_users` table
- Only Owner and Agency roles can edit client data
- User management (adding/removing users to clients) restricted to Owner/Agency

**Code Example**:
```php
$guard = new ClientGuard();
$user = session()->get('user');
$client = $clientModel->find($id);

if (!$guard->canView($user, $client)) {
    return redirect()->to('/dashboard')
        ->with('error', 'You do not have permission to view this client.');
}
```

## Controller Integration Pattern

### Step-by-Step Integration

**1. Inject Guard in Constructor**
```php
use App\Domain\Invoices\Authorization\InvoiceGuard;

class InvoicesController extends BaseController
{
    private InvoiceGuard $guard;

    public function __construct()
    {
        $this->guard = new InvoiceGuard();
    }
}
```

**2. Check Permissions Before Business Logic**
```php
public function show($id)
{
    $user = session()->get('user');
    $invoice = $this->model->find($id);

    // Guard check BEFORE showing data
    if (!$this->guard->canView($user, $invoice)) {
        SecurityLogger::logAccessDenied($user, "Invoice #{$id}", 'View attempt blocked');
        return redirect()->to('/invoices')->with('error', 'Access denied.');
    }

    // Business logic continues...
    return view('invoices/show', ['invoice' => $invoice]);
}
```

**3. Handle Authorization Failures**
```php
// For HTML responses
if (!$this->guard->canEdit($user, $resource)) {
    SecurityLogger::logAccessDenied($user, $resource, $reason);
    return redirect()->back()->with('error', 'Access denied.');
}

// For API responses
if (!$this->guard->canEdit($user, $resource)) {
    SecurityLogger::logAccessDenied($user, $resource, $reason);
    return $this->response->setStatusCode(403)->setJSON([
        'error' => 'You do not have permission to perform this action.'
    ]);
}
```

**4. Pass Permissions to Views**
```php
public function show($id)
{
    $user = session()->get('user');
    $invoice = $this->model->find($id);

    return view('invoices/show', [
        'invoice' => $invoice,
        'permissions' => $this->guard->getPermissionSummary($user, $invoice),
    ]);
}
```

### View Layer Integration

```php
<!-- In views, use permission summary to show/hide UI elements -->
<?php if ($permissions['canEdit']): ?>
    <a href="/invoices/<?= $invoice['id'] ?>/edit" class="btn btn-primary">Edit</a>
<?php endif; ?>

<?php if ($permissions['canDelete']): ?>
    <button type="button" class="btn btn-danger" data-action="delete">Delete</button>
<?php endif; ?>
```

## Permission Summary

Each guard provides a `getPermissionSummary()` method that returns all permissions for a user:

```php
$summary = $guard->getPermissionSummary($user, $resource);

// Returns:
[
    'canCreate' => true,
    'canView' => true,
    'canEdit' => true,
    'canDelete' => false,
    // Resource-specific permissions may also be included
]
```

**Use Cases**:
- **UI Control**: Show/hide buttons based on permissions
- **API Responses**: Include permissions in JSON responses for client-side logic
- **Debugging**: Log permission state for troubleshooting
- **Testing**: Validate permission logic in unit tests

## Security Logging

All authorization failures should be logged using `SecurityLogger`:

```php
use App\Helpers\SecurityLogger;

if (!$this->guard->canEdit($user, $resource)) {
    SecurityLogger::logAccessDenied(
        user: $user,
        resource: "Invoice #{$invoice['id']}",
        reason: "User role '{$user['role']}' attempted to edit invoice outside their scope"
    );

    return redirect()->back()->with('error', 'Access denied.');
}
```

**Log Format**: JSON lines in `writable/logs/security-YYYY-MM-DD.log`
```json
{
    "timestamp": "2025-01-15 14:32:45",
    "event": "ACCESS_DENIED",
    "user_id": "42",
    "user_email": "user@example.com",
    "user_role": "direct_client",
    "agency_id": "5",
    "attempted_resource": "Invoice #123",
    "reason": "User role 'direct_client' attempted to edit invoice outside their scope",
    "ip_address": "192.168.1.100",
    "user_agent": "Mozilla/5.0...",
    "request_method": "PUT"
}
```

## Testing Strategy

### Unit Tests

Unit tests validate guard business logic without database dependencies:

```php
// tests/Unit/Domain/Authorization/InvoiceGuardTest.php
public function test_owner_can_view_any_invoice(): void
{
    $owner = ['id' => '1', 'role' => 'owner', 'agency_id' => '1'];
    $invoice = ['id' => '100', 'agency_id' => '2', 'client_id' => '50'];

    $this->assertTrue($this->guard->canView($owner, $invoice));
}

public function test_end_client_cannot_view_any_invoice(): void
{
    $endClient = ['id' => '5', 'role' => 'end_client', 'agency_id' => '1'];
    $invoice = ['id' => '100', 'agency_id' => '1', 'client_id' => '50'];

    $this->assertFalse($this->guard->canView($endClient, $invoice));
}
```

### Integration Tests

Integration tests validate controller-guard interaction:

```php
// tests/Integration/Controllers/InvoicesControllerTest.php
public function test_agency_cannot_view_other_agency_invoice(): void
{
    $this->actingAs($agencyUser);

    $response = $this->get("/invoices/{$otherAgencyInvoiceId}");

    $response->assertStatus(403);
    $this->assertSecurityLogContains('ACCESS_DENIED', 'Invoice');
}
```

## Performance Considerations

### Guard Instantiation

**Recommendation**: Instantiate guards once per request in controller constructor

```php
// ✅ Good - reuse guard instance
private InvoiceGuard $guard;

public function __construct()
{
    $this->guard = new InvoiceGuard();
}

public function show($id) { /* use $this->guard */ }
public function edit($id) { /* use $this->guard */ }
```

```php
// ❌ Bad - creates new instance on every check
public function show($id)
{
    $guard = new InvoiceGuard();  // Wasteful
    if (!$guard->canView($user, $invoice)) { /* ... */ }
}
```

### Database Queries

Guard methods like `isProjectMember()` may query the database. Consider caching for repeated checks:

```php
// Example caching pattern for project membership
private array $membershipCache = [];

private function isProjectMember(string $userId, string $projectId): bool
{
    $key = "{$userId}:{$projectId}";

    if (!isset($this->membershipCache[$key])) {
        $this->membershipCache[$key] = $this->queryProjectMembership($userId, $projectId);
    }

    return $this->membershipCache[$key];
}
```

### Bulk Permission Checks

For list views with many resources, perform permission checks efficiently:

```php
// ✅ Good - filter in PHP after single query
$invoices = $this->model->findAll();  // RLS filters by agency_id
$visibleInvoices = array_filter($invoices, fn($invoice) =>
    $this->guard->canView($user, $invoice)
);

// ❌ Bad - N+1 query problem
foreach ($invoiceIds as $id) {
    $invoice = $this->model->find($id);  // Separate query per invoice
    if ($this->guard->canView($user, $invoice)) { /* ... */ }
}
```

## Compliance Considerations

### Audit Requirements

Authorization guards support audit and compliance requirements by:

1. **Explicit Permission Checks**: Every resource access has explicit authorization check
2. **Security Logging**: All failures logged with user, resource, reason, timestamp
3. **Permission Summaries**: Track what users can/cannot do for compliance reporting
4. **Defense in Depth**: Guards are Layer 3 of triple-redundant authorization system

### SOC 2 Type II Alignment

- **Access Control**: Guards enforce least-privilege principle
- **Audit Logging**: SecurityLogger provides comprehensive audit trail
- **Separation of Duties**: Only Owner can delete, Agency can edit, Clients view only
- **Data Classification**: Financial data (invoices) has strictest access (End Client blocked)

### GDPR Alignment

- **Data Minimization**: Users only see data they need access to
- **Access Controls**: Guards enforce who can view/edit personal data
- **Audit Trail**: Security logs track all data access attempts
- **Purpose Limitation**: Permissions aligned with user's role and purpose

## Troubleshooting

### Authorization Failures

**Symptom**: User sees "Access denied" when they should have access

**Diagnosis Steps**:
1. Check user session data: `session()->get('user')`
2. Verify user role and agency_id are set correctly
3. Check resource's agency_id matches user's agency_id (for agency role)
4. For clients, verify assignment in `client_users` or `project_members` table
5. Review security logs for detailed reason: `writable/logs/security-YYYY-MM-DD.log`

**Common Issues**:
- Session missing `agency_id` for agency/direct_client/end_client roles
- User not assigned to client/project in junction table
- Resource has null `agency_id` (orphaned record)

### Permission Summary Not Working

**Symptom**: UI buttons show incorrectly (edit button for read-only user)

**Diagnosis**:
1. Verify controller passes permission summary to view: `'permissions' => $guard->getPermissionSummary($user, $resource)`
2. Check view correctly references permissions: `<?php if ($permissions['canEdit']): ?>`
3. Ensure resource is passed to `getPermissionSummary()` (not just user)

### Database Query Permissions

**Symptom**: Guard returns true but database query returns empty

**This is correct behavior**. Guards check business logic, RLS enforces at SQL level:

```php
$guard->canView($user, $invoice);  // Returns true (business logic allows)
$invoice = $model->find($id);       // Returns null (RLS blocks SQL query)
```

**Solution**: Always fetch resource first, then check guard:

```php
$invoice = $this->model->find($id);
if (!$invoice) {
    return redirect()->back()->with('error', 'Invoice not found.');
}

if (!$this->guard->canView($user, $invoice)) {
    return redirect()->back()->with('error', 'Access denied.');
}
```

## Migration from HTTP-Only Authorization

If you have controllers that only use HTTP middleware (Layer 2), migrate to service guards:

**Before (HTTP middleware only)**:
```php
// RBACFilter blocks End Clients from /invoices routes
public function show($id)
{
    $invoice = $this->model->find($id);  // Hope RLS protects us
    return view('invoices/show', ['invoice' => $invoice]);
}
```

**After (with service guards)**:
```php
private InvoiceGuard $guard;

public function show($id)
{
    $user = session()->get('user');
    $invoice = $this->model->find($id);

    if (!$invoice || !$this->guard->canView($user, $invoice)) {
        SecurityLogger::logAccessDenied($user, "Invoice #{$id}", 'Access attempt blocked');
        return redirect()->to('/invoices')->with('error', 'Access denied.');
    }

    return view('invoices/show', [
        'invoice' => $invoice,
        'permissions' => $this->guard->getPermissionSummary($user, $invoice),
    ]);
}
```

## Best Practices

### ✅ DO

- **Inject guards in constructor** for reuse across controller methods
- **Check permissions before business logic** in every controller action
- **Log authorization failures** with SecurityLogger for audit trail
- **Pass permission summaries to views** for UI control (show/hide buttons)
- **Fetch resource first, then check permissions** to handle RLS filtering
- **Use guard interface** to enable testing with mock guards
- **Cache membership checks** for performance in high-traffic methods

### ❌ DON'T

- **Don't rely only on HTTP middleware** - always use service guards in controllers
- **Don't skip permission checks** because "RLS will protect us" - defense in depth
- **Don't create new guard instance** on every method call - reuse from constructor
- **Don't assume guard returns true** means database will return data - RLS may still block
- **Don't expose internal error details** to users - log details, show generic message
- **Don't hard-code permission checks** - always use guard methods
- **Don't forget to update tests** when adding new guard logic

## Summary

Service-level authorization guards (RBAC Layer 3) provide the final layer of authorization before business logic execution. They implement fine-grained, resource-specific permission checks that complement database RLS (Layer 1) and HTTP middleware (Layer 2) in a defense-in-depth security architecture.

**Key Benefits**:
- **Fine-Grained Control**: Resource-specific permissions beyond HTTP route patterns
- **Business Logic Integration**: Check permissions in context of specific data
- **Explicit Authorization**: Every controller action has visible permission check
- **Permission Summaries**: Enable intelligent UI and debugging
- **Audit Trail**: Security logging captures all authorization attempts
- **Testability**: Unit tests validate authorization logic independently

**Implementation Checklist**:
- [x] AuthorizationGuardInterface defined
- [x] InvoiceGuard, ProjectGuard, ClientGuard implemented
- [x] Unit tests covering all guard methods
- [x] Example controller demonstrating integration pattern
- [x] Documentation complete with troubleshooting guide

For questions or issues, review security logs in `writable/logs/security-YYYY-MM-DD.log` or refer to the troubleshooting section above.
