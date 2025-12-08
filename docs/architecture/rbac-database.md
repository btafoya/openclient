# Database-Level RBAC with PostgreSQL Row-Level Security

## Overview

This document describes the implementation of database-enforced multi-agency data isolation using PostgreSQL Row-Level Security (RLS). This forms **RBAC Layer 1** in the openclient security architecture, providing defense-in-depth protection against unauthorized data access.

## Architecture Goals

1. **Database-Enforced Isolation**: Multi-agency data isolation enforced at the database level, independent of application code
2. **Defense in Depth**: Protection continues even if application code has vulnerabilities or bugs
3. **SQL Injection Protection**: RLS policies cannot be bypassed by malicious SQL injection attacks
4. **Performance**: Efficient query execution through proper indexing and policy design
5. **Maintainability**: Clear patterns for extending RLS to all tenant-scoped tables

## System Components

### 1. Session Variable Management

**File**: `app/Config/Database.php`

The custom Database configuration overrides CodeIgniter's connection method to automatically set PostgreSQL session variables whenever a database connection is established.

```php
public static function connect($group = null, bool $getShared = true)
{
    $db = parent::connect($group, $getShared);

    // Set session variables from logged-in user
    $user = session()->get('user');
    if ($user && is_array($user)) {
        $db->query("SET app.current_user_id = ?", [$user['id']]);
        $db->query("SET app.current_user_role = ?", [$user['role']]);
        $db->query("SET app.current_agency_id = ?", [$user['agency_id']]);
    }

    return $db;
}
```

**Session Variables**:
- `app.current_user_id`: UUID of currently logged-in user (for audit trails)
- `app.current_user_role`: User role ('owner', 'agency', 'client')
- `app.current_agency_id`: UUID of user's agency (NULL for owner users)

**When Variables Are Set**:
- On every database connection establishment
- Automatically when user logs in via `LoginController::login()`
- User session data drives the values

**Testing Considerations**:
- Session variable setup is skipped in ENVIRONMENT === 'testing'
- Tests must manually set session variables using PDO exec

### 2. Row-Level Security Policies

**Implementation**: `app/Database/Migrations/2025-01-15-000000_CreateClientsTable.php`

Each tenant-scoped table requires RLS to be enabled and policies created for all CRUD operations.

#### Enabling RLS

```sql
-- Enable RLS on table
ALTER TABLE clients ENABLE ROW LEVEL SECURITY;

-- CRITICAL: Force RLS even for table owner
ALTER TABLE clients FORCE ROW LEVEL SECURITY;
```

**Why FORCE ROW LEVEL SECURITY is Critical**:
- Without FORCE, table owners bypass RLS policies entirely
- openclient_user owns all tables, so RLS would be ineffective
- FORCE ensures policies apply to ALL users, including owner
- This is the single most important security setting

#### SELECT Policy (USING clause)

```sql
CREATE POLICY agency_isolation_clients ON clients
USING (
    agency_id = current_setting('app.current_agency_id', true)::uuid
    OR current_setting('app.current_user_role', true) = 'owner'
);
```

**Behavior**:
- Agency users only see rows where `agency_id` matches their session variable
- Owner users see all rows regardless of `agency_id`
- Applies to SELECT queries automatically

#### INSERT Policy (WITH CHECK clause)

```sql
CREATE POLICY agency_isolation_clients_insert ON clients
FOR INSERT
WITH CHECK (
    agency_id = current_setting('app.current_agency_id', true)::uuid
    OR current_setting('app.current_user_role', true) = 'owner'
);
```

**Behavior**:
- Agency users can only insert rows with their own `agency_id`
- Owner users can insert rows with any `agency_id`
- INSERT fails if check condition not satisfied

#### UPDATE Policy (USING clause)

```sql
CREATE POLICY agency_isolation_clients_update ON clients
FOR UPDATE
USING (
    agency_id = current_setting('app.current_agency_id', true)::uuid
    OR current_setting('app.current_user_role', true) = 'owner'
);
```

**Behavior**:
- Agency users can only update rows they can see (matching `agency_id`)
- Owner users can update any row
- UPDATE fails if row doesn't satisfy USING condition

#### DELETE Policy (USING clause)

```sql
CREATE POLICY agency_isolation_clients_delete ON clients
FOR DELETE
USING (
    agency_id = current_setting('app.current_agency_id', true)::uuid
    OR current_setting('app.current_user_role', true) = 'owner'
);
```

**Behavior**:
- Agency users can only delete rows they can see (matching `agency_id`)
- Owner users can delete any row
- DELETE fails if row doesn't satisfy USING condition

### 3. Performance Considerations

#### Critical Index for RLS

```sql
CREATE INDEX idx_clients_agency_id ON clients(agency_id);
```

**Why This Index is Critical**:
- RLS policies filter on `agency_id` for EVERY query
- Without index, every query becomes a full table scan
- Index allows PostgreSQL to efficiently filter rows
- Performance degradation without this index can be severe (10-100x slower)

**Index Strategy for All Tenant-Scoped Tables**:
- ALWAYS create index on `agency_id` column
- Place index creation before enabling RLS
- Consider composite indexes if queries filter on agency_id + other columns

#### Query Planning

PostgreSQL query planner automatically incorporates RLS policies:

```sql
-- What application writes:
SELECT * FROM clients WHERE name = 'Acme Corp';

-- What PostgreSQL executes (agency user):
SELECT * FROM clients
WHERE name = 'Acme Corp'
AND agency_id = '11111111-1111-1111-1111-111111111111';

-- What PostgreSQL executes (owner user):
SELECT * FROM clients WHERE name = 'Acme Corp';
```

## Security Properties

### 1. SQL Injection Protection

RLS policies use parameterized session variables that cannot be modified via SQL injection:

```sql
-- Malicious attempt to bypass RLS
SET app.current_agency_id = 'hacker-uuid'; -- Permission denied
SELECT * FROM clients; -- Still filtered by original agency_id
```

**Why This Works**:
- Session variables are connection-level, set during connection establishment
- Application code sets them via separate `SET` statements
- User input never touches session variable setting code
- Even if attacker injects SQL in queries, session variables remain unchanged

### 2. Application Bug Protection

Even if application code has vulnerabilities, RLS provides protection:

```php
// Buggy code that forgets to filter by agency
$clients = $db->query("SELECT * FROM clients")->getResultArray();

// RLS still enforces filtering
// Agency users only see their clients
// Owner sees all clients
```

### 3. Defense in Depth

**Security Layers**:
1. **Application Layer** (RBAC Layer 2): CodeIgniter filters, authorization checks
2. **Database Layer** (RBAC Layer 1): PostgreSQL RLS policies ← This document
3. **Network Layer**: Firewall rules, VPN access
4. **Authentication Layer**: Session management, password hashing

RLS ensures that even if application layer is bypassed, database enforces isolation.

## Implementation Pattern for New Tables

When creating new tenant-scoped tables, follow this pattern:

### 1. Table Structure

```php
$this->forge->addField([
    'id' => ['type' => 'UUID', 'null' => false],
    'agency_id' => [
        'type' => 'UUID',
        'null' => false,
        'comment' => 'REQUIRED for RLS - links to owning agency'
    ],
    // ... other fields
]);

$this->forge->addPrimaryKey('id');
$this->forge->addForeignKey('agency_id', 'agencies', 'id', 'CASCADE', 'CASCADE');
```

**Requirements**:
- `agency_id` column is REQUIRED, NOT NULL
- Foreign key to agencies table with CASCADE
- UUID primary key with default uuid_generate_v4()

### 2. Performance Indexes

```php
// CRITICAL: agency_id index for RLS performance
$this->db->query("CREATE INDEX idx_{table}_agency_id ON {table}(agency_id)");

// Additional indexes for common queries
$this->db->query("CREATE INDEX idx_{table}_{column} ON {table}({column})");
```

### 3. Enable RLS with FORCE

```php
$this->db->query("ALTER TABLE {table} ENABLE ROW LEVEL SECURITY");
$this->db->query("ALTER TABLE {table} FORCE ROW LEVEL SECURITY");
```

### 4. Create Four Policies

```php
// SELECT policy
$this->db->query("
    CREATE POLICY agency_isolation_{table} ON {table}
    USING (
        agency_id = current_setting('app.current_agency_id', true)::uuid
        OR current_setting('app.current_user_role', true) = 'owner'
    )
");

// INSERT policy
$this->db->query("
    CREATE POLICY agency_isolation_{table}_insert ON {table}
    FOR INSERT
    WITH CHECK (
        agency_id = current_setting('app.current_agency_id', true)::uuid
        OR current_setting('app.current_user_role', true) = 'owner'
    )
");

// UPDATE policy
$this->db->query("
    CREATE POLICY agency_isolation_{table}_update ON {table}
    FOR UPDATE
    USING (
        agency_id = current_setting('app.current_agency_id', true)::uuid
        OR current_setting('app.current_user_role', true) = 'owner'
    )
");

// DELETE policy
$this->db->query("
    CREATE POLICY agency_isolation_{table}_delete ON {table}
    FOR DELETE
    USING (
        agency_id = current_setting('app.current_agency_id', true)::uuid
        OR current_setting('app.current_user_role', true) = 'owner'
    )
");
```

## Testing RLS Implementation

### 1. Manual SQL Verification

**Script**: `scripts/verify_rls.php`

This script verifies RLS configuration:
1. Confirms RLS is enabled and forced
2. Lists all policies on table
3. Checks database user permissions
4. Tests agency isolation (Agency A can only see their data)
5. Tests owner access (Owner can see all data)

**Running Verification**:
```bash
php scripts/verify_rls.php
```

**Expected Output**:
```
✓ RLS is ENABLED on clients table
Found 4 policies: agency_isolation_clients, *_insert, *_update, *_delete
Current user: openclient_user (not superuser)
✓ RLS is working! Only Agency A's client visible
✓ Owner can see all clients
```

### 2. Integration Tests

**File**: `tests/Integration/RowLevelSecurityTest.php`

Comprehensive test suite covering:
- Agency A isolation (can only see their clients)
- Agency B isolation (can only see their clients)
- Owner access (can see all agencies' clients)
- SQL injection protection
- INSERT restrictions (cannot insert for different agency)
- UPDATE restrictions (cannot update different agency's clients)
- DELETE restrictions (cannot delete different agency's clients)

**Running Tests**:
```bash
vendor/bin/phpunit tests/Integration/RowLevelSecurityTest.php
```

**Test Setup Requirements**:
- Tests use direct PDO connections (bypass CodeIgniter session logic)
- Must manually set session variables with `SET app.current_user_role = 'owner'`
- Setup/teardown operations require owner role to bypass RLS
- Each test sets appropriate role and agency_id for scenario

### 3. Testing Pattern

```php
public function testAgencyIsolation(): void
{
    // Simulate agency user logged in
    self::$pdo->exec("SET app.current_user_role = 'agency'");
    self::$pdo->exec("SET app.current_agency_id = '{$agencyUUID}'");

    // Execute query
    $result = self::$pdo->query("SELECT * FROM clients")->fetchAll();

    // Assert only own agency's data visible
    $this->assertCount(1, $result);
    $this->assertEquals($agencyUUID, $result[0]['agency_id']);
}
```

## Troubleshooting

### Problem: RLS Not Enforcing

**Symptoms**: All users can see all data regardless of agency

**Common Causes**:
1. **Missing FORCE ROW LEVEL SECURITY** ← Most common
   - Check: `SELECT relforcerowsecurity FROM pg_class WHERE relname = 'table_name'`
   - Fix: `ALTER TABLE table_name FORCE ROW LEVEL SECURITY`

2. **User is database superuser**
   - Check: `SELECT usesuper FROM pg_user WHERE usename = current_user`
   - Fix: Remove superuser privilege (not recommended for application user)

3. **Session variables not set**
   - Check: `SELECT current_setting('app.current_agency_id', true)`
   - Fix: Verify `app/Config/Database.php` is setting variables correctly

4. **Policies don't exist**
   - Check: `SELECT * FROM pg_policies WHERE tablename = 'table_name'`
   - Fix: Run migration to create policies

### Problem: Performance Degradation

**Symptoms**: Queries on tenant-scoped tables are very slow

**Causes**:
1. **Missing agency_id index** ← Most common
   - Check: `SELECT * FROM pg_indexes WHERE tablename = 'table_name'`
   - Fix: `CREATE INDEX idx_table_agency_id ON table_name(agency_id)`

2. **Table statistics out of date**
   - Check: `SELECT last_analyze FROM pg_stat_user_tables WHERE relname = 'table_name'`
   - Fix: `ANALYZE table_name`

### Problem: INSERT/UPDATE/DELETE Fails

**Symptoms**: `ERROR: new row violates row-level security policy`

**Causes**:
1. **Attempting to insert wrong agency_id**
   - Agency users can only insert rows with their own agency_id
   - Fix: Ensure application code sets agency_id correctly

2. **Session variables not set**
   - Operations fail if app.current_agency_id not set
   - Fix: Verify user is logged in and session variables are set

3. **FORCE RLS blocking legitimate operations**
   - Test setup/cleanup may need owner role
   - Fix: Set `app.current_user_role = 'owner'` for setup/teardown

## Future Extensions

### Additional Tables Requiring RLS

Based on IMPLEMENTATION_WORKFLOW.md, these tables will need RLS:
- **projects**: Projects belong to agencies, need isolation
- **invoices**: Financial data must be isolated by agency
- **payments**: Payment records need strict isolation
- **users**: Agency staff should only see own agency users
- **webhooks**: Webhook logs should be agency-isolated
- **audit_logs**: Audit trails need isolation (but owner can see all)

### Pattern Variations

**Global Tables (No RLS)**:
- agencies: Managed by owner, no RLS needed
- system_settings: Global configuration, no RLS
- payment_gateways: Shared payment processor configurations

**Special Cases**:
- **audit_logs**: RLS for agencies, but owner can see all for compliance
- **users**: RLS with additional role-based filtering (managers see team members)

## References

- **PostgreSQL RLS Documentation**: https://www.postgresql.org/docs/current/ddl-rowsecurity.html
- **CodeIgniter 4 Database**: https://codeigniter.com/user_guide/database/index.html
- **IMPLEMENTATION_WORKFLOW.md**: Week 7-8 requirements for RBAC Layer 1
- **Test Suite**: tests/Integration/RowLevelSecurityTest.php
- **Verification Script**: scripts/verify_rls.php

## Summary

PostgreSQL Row-Level Security provides database-enforced multi-agency data isolation that:
- Cannot be bypassed by application bugs or SQL injection
- Requires minimal application code changes
- Performs efficiently with proper indexing
- Provides defense-in-depth security architecture
- Follows clear, repeatable patterns for all tenant-scoped tables

**Critical Success Factors**:
1. ✅ `ALTER TABLE FORCE ROW LEVEL SECURITY` on all tenant tables
2. ✅ Index on `agency_id` column for performance
3. ✅ Four policies per table (SELECT, INSERT, UPDATE, DELETE)
4. ✅ Session variables set on every database connection
5. ✅ Comprehensive testing to verify isolation

This forms the foundation of openclient's multi-agency security architecture.
