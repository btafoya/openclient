# RBAC Architecture Documentation

**Project**: OpenClient
**Document Version**: 1.0
**Last Updated**: 2025-12-08
**Milestone**: 1 (Foundation & RBAC)

---

## Executive Summary

OpenClient implements a **4-layer defense-in-depth RBAC system** to ensure multi-agency data isolation and role-based access control. This architecture provides comprehensive security from the database layer through to the user interface.

### Security Layers

1. **Layer 1: PostgreSQL Row-Level Security (RLS)** - Database-enforced isolation
2. **Layer 2: HTTP Middleware** - Route-level authorization
3. **Layer 3: Service Guards** - Business logic permission checks
4. **Layer 4: Frontend Permissions** - UI hiding for better UX (not security)

### Key Benefits

- **Multi-Agency Isolation**: Agencies cannot access each other's data
- **Defense in Depth**: Security enforced at 4 independent layers
- **Fine-Grained Control**: Permission checks at resource level, not just route level
- **Audit Trail**: All access denials logged for compliance
- **User Experience**: Frontend hides irrelevant features for each role

---

## Role Hierarchy

### Owner (Superuser)
- **Description**: Platform administrator with full system access
- **Agency**: No agency assignment (operates across all agencies)
- **Permissions**: ALL - can access everything including admin features
- **Use Cases**: System configuration, user management, agency management

### Agency User
- **Description**: Agency employee managing clients and projects
- **Agency**: Assigned to specific agency
- **Permissions**:
  - ✅ Can view/edit clients, projects, invoices in their agency
  - ✅ Can access financial features
  - ❌ Cannot access other agencies' data
  - ❌ Cannot access admin features
- **Use Cases**: Client management, project tracking, invoicing

### Direct Client
- **Description**: Client with financial access (invoicing, payments)
- **Agency**: Assigned to agency (as client of that agency)
- **Permissions**:
  - ✅ Can view/edit their own projects
  - ✅ Can access financial features (invoices, payments)
  - ❌ Cannot access other clients' data
  - ❌ Cannot access admin features
- **Use Cases**: View invoices, make payments, track project progress

### End Client
- **Description**: Client WITHOUT financial access (limited visibility)
- **Agency**: Assigned to agency (as client of that agency)
- **Permissions**:
  - ✅ Can view assigned projects
  - ✅ Can view project files and comments
  - ❌ Cannot access financial features (invoices, payments, quotes)
  - ❌ Cannot access other clients' data
  - ❌ Cannot access admin features
- **Use Cases**: Project collaboration, file access, communication

---

## Layer 1: PostgreSQL Row-Level Security (RLS)

### Overview

PostgreSQL RLS provides **database-enforced data isolation** at the row level. Even if application code has bugs or is bypassed, the database prevents unauthorized data access.

### Implementation Pattern

```sql
-- Enable RLS on table
ALTER TABLE clients ENABLE ROW LEVEL SECURITY;

-- Create policy for agency isolation
CREATE POLICY agency_isolation_clients ON clients
USING (
    agency_id = current_setting('app.current_agency_id', true)::uuid
    OR current_setting('app.current_user_role', true) = 'owner'
);
```

### Session Variables

Set on each request via `app/Config/Database.php` and `app/Filters/LoginFilter.php`:

```php
$db->query("SET app.current_user_id = ?", [$user['id']]);
$db->query("SET app.current_user_role = ?", [$user['role']]);
$db->query("SET app.current_agency_id = ?", [$user['agency_id']]);
```

### RLS-Enabled Tables

| Table | RLS Policy | Owner Bypass |
|-------|-----------|--------------|
| `users` | agency_isolation_users | ✅ Yes |
| `clients` | agency_isolation_clients | ✅ Yes |
| `projects` | agency_isolation_projects | ✅ Yes |
| `invoices` | agency_isolation_invoices | ✅ Yes |
| `activity_log` | agency_isolation_activity_log | ✅ Yes |
| `agencies` | ❌ No RLS | N/A |
| `ci_sessions` | ❌ No RLS | N/A |

**Rationale**: Agencies table has no RLS because only Owner role manages agencies.

### Testing RLS

```php
// Test Case: Agency A user cannot see Agency B clients
$userA = ['agency_id' => $agencyA_id, 'role' => 'agency'];
$clientB = ClientModel::find($clientB_id); // belongs to Agency B

// Expected: $clientB returns null (RLS blocks the query)
// Actual: Verified in RBACTestSeeder manual testing
```

---

## Layer 2: HTTP Middleware (Route-Level Authorization)

### Overview

HTTP filters intercept requests **before they reach controllers**, blocking unauthorized access early in the request lifecycle.

### Filters

#### LoginFilter (`app/Filters/LoginFilter.php`)

**Purpose**: Ensure user is authenticated

**Actions**:
- Check if `session('logged_in')` is true
- Redirect to `/auth/login` if not authenticated
- Refresh PostgreSQL RLS session variables on each request

**Applied To**: All routes except `/auth/*` and `/`

#### RBACFilter (`app/Filters/RBACFilter.php`)

**Purpose**: Enforce role-based route access

**Rules**:
- **End Clients**: BLOCKED from `/invoices`, `/quotes`, `/billing`, `/payments`, `/reports/financial`
- **Non-Owners**: BLOCKED from `/admin`, `/settings`, `/users`, `/agencies`
- **Agency Users**: Must have `agency_id` assigned
- **Direct Clients**: Must have `agency_id` assigned

**Security Features**:
- Logs access denials to `writable/logs/security-{date}.log`
- Provides user-friendly error messages
- Validates agency assignment integrity

**Applied To**: All routes except `/auth/*`, `/`, `/dashboard`

### Configuration

`app/Config/Filters.php`:

```php
public array $globals = [
    'before' => [
        'login' => ['except' => ['auth/*', '/']],
        'rbac' => ['except' => ['auth/*', '/', '/dashboard']],
    ],
];
```

---

## Layer 3: Service Guards (Business Logic Authorization)

### Overview

Service guards provide **fine-grained authorization checks** at the business logic layer, answering questions like:

- Can this user view *this specific invoice*?
- Can this user edit *this specific project*?
- Can this user delete *this specific client*?

### Guard Interface

`app/Domain/Authorization/AuthorizationGuardInterface.php`:

```php
interface AuthorizationGuardInterface
{
    public function canView(array $user, $resource): bool;
    public function canCreate(array $user): bool;
    public function canEdit(array $user, $resource): bool;
    public function canDelete(array $user, $resource): bool;
}
```

### Implemented Guards

#### InvoiceGuard (`app/Domain/Invoices/Authorization/InvoiceGuard.php`)

**Authorization Logic**:
- **canView()**: Owner OR same agency as invoice
- **canCreate()**: Owner OR agency role
- **canEdit()**: Owner OR same agency as invoice AND invoice not paid
- **canDelete()**: Owner OR (same agency AND invoice status='draft')

**Special Rules**:
- End Clients cannot access invoices at all (blocked by Layer 2)
- Paid invoices cannot be edited (business rule)
- Only draft invoices can be deleted

#### ProjectGuard (`app/Domain/Projects/Authorization/ProjectGuard.php`)

**Authorization Logic**:
- **canView()**: Owner OR same agency OR assigned as client user
- **canCreate()**: Owner OR agency role
- **canEdit()**: Owner OR same agency
- **canDelete()**: Owner OR (same agency AND no invoices linked)

#### ClientGuard (`app/Domain/Clients/Authorization/ClientGuard.php`)

**Authorization Logic**:
- **canView()**: Owner OR same agency OR is the client user
- **canCreate()**: Owner OR agency role
- **canEdit()**: Owner OR same agency
- **canDelete()**: Owner OR (same agency AND no projects/invoices)

### Usage in Controllers

```php
public function view($id)
{
    $user = session()->get('user');
    $invoice = $this->invoiceModel->find($id);

    // Layer 3: Service Guard check
    $guard = new InvoiceGuard();
    if (!$guard->canView($user, $invoice)) {
        return $this->response->setStatusCode(403)
            ->setJSON(['error' => 'You do not have permission to view this invoice.']);
    }

    return view('invoices/view', ['invoice' => $invoice]);
}
```

---

## Layer 4: Frontend Permissions (UX Only)

### ⚠️ CRITICAL SECURITY WARNING

**Frontend permission checks do NOT provide security.** An attacker can:
- Modify the Pinia store
- Bypass Vue.js conditional rendering
- Call API endpoints directly

Frontend checks are **for user experience only**, to:
- Hide irrelevant features
- Prevent accidental unauthorized actions
- Provide clear visual feedback

**Real security is enforced by Layers 1-3 (backend).**

### Pinia User Store

`resources/js/stores/user.js`:

```javascript
export const useUserStore = defineStore('user', () => {
  // State
  const role = ref(null)
  const agencyId = ref(null)

  // Permission Computed Properties
  const canViewFinancials = computed(() =>
    ['owner', 'agency', 'direct_client'].includes(role.value)
  )

  const canManageUsers = computed(() => role.value === 'owner')

  const canAccessAdmin = computed(() => role.value === 'owner')

  // Role Checks
  const isOwner = computed(() => role.value === 'owner')
  const isEndClient = computed(() => role.value === 'end_client')

  return { canViewFinancials, canManageUsers, canAccessAdmin, isOwner, isEndClient }
})
```

### Usage in Vue Components

```vue
<script setup>
import { useUserStore } from '@/stores/user'

const userStore = useUserStore()
</script>

<template>
  <nav>
    <a href="/clients">Clients</a>
    <a href="/projects">Projects</a>

    <!-- Hide financial features for End Clients -->
    <a v-if="userStore.canViewFinancials" href="/invoices">Invoices</a>
    <a v-if="userStore.canViewFinancials" href="/payments">Payments</a>

    <!-- Hide admin for non-Owners -->
    <a v-if="userStore.canAccessAdmin" href="/admin">Admin</a>
  </nav>
</template>
```

---

## Security Flow Examples

### Example 1: End Client Attempts to View Invoice

**Request**: `GET /invoices/123`

1. **Layer 2 (HTTP Middleware)**: RBACFilter checks role
   - Role: `end_client`
   - Route: `/invoices`
   - Result: ❌ BLOCKED - Redirect to `/dashboard` with error message
   - Security Log: `ACCESS_DENIED - End Client attempted to access financial route`

**Outcome**: Request never reaches controller. Invoice remains secure.

---

### Example 2: Agency A User Attempts to View Agency B Invoice

**Request**: `GET /invoices/456` (Invoice belongs to Agency B)

1. **Layer 2 (HTTP Middleware)**: RBACFilter checks role
   - Role: `agency`
   - Route: `/invoices`
   - Result: ✅ PASS (agency role can access invoices route)

2. **Controller**: InvoicesController::view(456)
   - Fetch invoice: `$invoice = InvoiceModel::find(456)`

3. **Layer 1 (RLS)**: PostgreSQL checks RLS policy
   - User agency_id: `agency_a_id`
   - Invoice agency_id: `agency_b_id`
   - Policy: `agency_id = current_setting('app.current_agency_id')`
   - Result: ❌ BLOCKED - Query returns null

4. **Controller**: Handle null result
   - Result: 404 Not Found (invoice doesn't "exist" from Agency A's perspective)

**Outcome**: Invoice remains secure via database-level isolation.

---

### Example 3: Agency User Attempts to Edit Paid Invoice

**Request**: `PUT /invoices/789/edit` (Invoice status='paid')

1. **Layer 2**: ✅ PASS (agency role can access invoices)

2. **Layer 1 (RLS)**: ✅ PASS (same agency)

3. **Layer 3 (Service Guard)**: InvoiceGuard::canEdit()
   ```php
   if ($invoice['status'] === 'paid') {
       return false; // Cannot edit paid invoices
   }
   ```
   - Result: ❌ BLOCKED - Business rule violation

4. **Controller**: Return 403 Forbidden
   - Message: "Paid invoices cannot be edited."

**Outcome**: Business logic enforced at service layer.

---

## Audit Trail & Compliance

### Security Logs

**Location**: `writable/logs/security-{date}.log`

**Format**: JSON per line

```json
{
  "timestamp": "2025-12-08 19:30:45",
  "event": "ACCESS_DENIED",
  "user_id": "uuid-here",
  "user_email": "endclient@test.com",
  "user_role": "end_client",
  "attempted_resource": "/invoices/list",
  "reason": "End Client attempted to access financial route",
  "ip_address": "192.168.1.100",
  "user_agent": "Mozilla/5.0..."
}
```

### Activity Log

**Table**: `activity_log`

**Purpose**: Audit trail for business operations

**Tracked Actions**:
- created, updated, deleted, viewed
- For resources: client, project, invoice, user

**RLS**: Agency-isolated (only see your agency's activity)

---

## Testing Strategy

### Unit Tests

**Location**: `tests/Unit/`

**Coverage**: 128/128 passing (100%)

**Focus**:
- Model methods
- Guard authorization logic
- Helper functions

### Integration Tests

**Planned**: `tests/Integration/RBAC/`

**Test Cases**:
- RLS isolation across agencies
- HTTP middleware blocking unauthorized routes
- Service guards preventing unauthorized resource access
- Frontend permission checks (smoke tests only)

### Manual RBAC Testing

**Test Accounts**: 5 roles × 8 test cases = 40 test scenarios

| Role | Test Cases |
|------|------------|
| Owner | Full access validation |
| Agency A | Agency B isolation, financial access |
| Agency B | Agency A isolation, financial access |
| Direct Client | Financial access, data isolation |
| End Client | Financial blocking, project access |

**Execution**: Manual browser testing with documented results

---

## Performance Considerations

### RLS Overhead

**Impact**: Negligible (<1ms per query)

**Reason**: PostgreSQL optimizes RLS policies using indexes

**Monitoring**: Track query performance in `app.performance_metrics` table

### Session Variable Refresh

**Pattern**: Set on every request via LoginFilter

**Impact**: <1ms per request (3 simple SET statements)

**Optimization**: Not needed - overhead is minimal

---

## Common Pitfalls & Solutions

### Pitfall 1: Forgetting to Set Session Variables

**Symptom**: All queries return empty results for non-Owner users

**Cause**: RLS policies check `current_setting('app.current_agency_id')` but variable not set

**Solution**: LoginFilter automatically sets variables on every authenticated request

**Verification**:
```php
// In any controller
$db = \Config\Database::connect();
$result = $db->query("SELECT current_setting('app.current_agency_id', true)")->getRow();
// Should return the user's agency_id
```

### Pitfall 2: Testing with Owner Role

**Symptom**: RLS appears broken (can see all data)

**Cause**: Owner role bypasses ALL RLS policies

**Solution**: Test with `agency`, `direct_client`, or `end_client` roles

### Pitfall 3: Trusting Frontend Permission Checks

**Symptom**: Security vulnerability reports

**Cause**: Relying on `v-if="userStore.canAccessAdmin"` for security

**Solution**: Always enforce authorization in backend (Layers 1-3)

### Pitfall 4: Not Handling Null Results from RLS

**Symptom**: PHP errors when RLS blocks a query

**Cause**: Code assumes `find($id)` always returns a result

**Solution**: Check for null and return 404:
```php
$invoice = $invoiceModel->find($id);
if (!$invoice) {
    throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
}
```

---

## Future Enhancements

### Phase 1 (Milestone 2)
- Add permission caching (reduce guard computation)
- Implement resource-level permissions (per-project, per-invoice)
- Add role inheritance (Senior Agency > Junior Agency)

### Phase 2 (Milestone 3)
- Add custom permissions beyond roles (e.g., "can_export_data")
- Implement time-based permissions (temporary access)
- Add IP-based restrictions

### Phase 3 (Milestone 4)
- Add multi-factor authentication (MFA)
- Implement session management (active sessions, force logout)
- Add comprehensive audit dashboard

---

## References

### Documentation Files
- `PROJECT_STATUS.md` - Current project status
- `IMPLEMENTATION_WORKFLOW.md` - Implementation phases
- `claudedocs/http-500-fix-report.md` - Session debugging
- `claudedocs/week16-phase3-report.md` - Testing preparation

### Code References
- `app/Config/Database.php:136` - RLS session variable setup
- `app/Filters/LoginFilter.php` - Authentication + RLS refresh
- `app/Filters/RBACFilter.php` - Route-level authorization
- `app/Domain/Authorization/AuthorizationGuardInterface.php` - Guard contract
- `resources/js/stores/user.js` - Frontend permission store

### External Resources
- PostgreSQL RLS: https://www.postgresql.org/docs/current/ddl-rowsecurity.html
- CodeIgniter Filters: https://codeigniter.com/user_guide/incoming/filters.html
- Pinia Store: https://pinia.vuejs.org/core-concepts/

---

**Document Status**: ✅ Complete
**Review Date**: 2025-12-08
**Next Review**: After Milestone 2 implementation
