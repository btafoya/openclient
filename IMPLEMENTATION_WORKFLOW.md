# openclient Implementation Workflow

**Generated**: 2025-12-08
**Based on**: PR.md + PRD_IMPROVEMENTS.md + Stakeholder Questionnaire
**Timeline**: 12 months (52 weeks) for Full MVP
**Strategy**: Systematic phased delivery with quality gates

---

## Executive Summary

This workflow provides a **week-by-week implementation plan** for building openclient from foundation to production launch. The plan follows 4 major milestones with embedded quality gates, testing strategies, and risk mitigation.

**Timeline Overview**:
- **Milestone 1** (Months 1-3): Foundation & RBAC
- **Milestone 2** (Months 4-6): Core revenue features (CRM, Projects, Invoices, Stripe)
- **Milestone 3** (Months 7-9): Expansion (Pipelines, Proposals, Recurring invoices, Portal)
- **Milestone 4** (Months 10-12): Remaining features + Production launch

**Success Criteria**: All 11 features complete, 95% test coverage, WCAG 2.1 AA compliant, 99% uptime, 10 test agencies validated

---

## Dependency Graph

```
Foundation (Auth + RBAC + Database)
    │
    ├─→ CRM (Clients & Contacts)
    │       │
    │       ├─→ Projects & Tasks
    │       │       │
    │       │       └─→ Time Tracking
    │       │
    │       ├─→ Invoices
    │       │       │
    │       │       ├─→ Stripe Integration
    │       │       │       │
    │       │       │       └─→ Webhook Handling
    │       │       │
    │       │       └─→ Recurring Invoices
    │       │
    │       ├─→ Proposals
    │       │
    │       └─→ Pipelines & Deals
    │
    ├─→ Client Portal (depends on Auth + Invoices)
    │
    ├─→ Tickets & Support (depends on CRM)
    │
    ├─→ Documents (depends on CRM + Projects)
    │
    ├─→ Forms & Onboarding (depends on CRM)
    │
    ├─→ Discussions (depends on CRM + Projects)
    │
    └─→ Meetings & Calendar (depends on CRM + Projects)

Testing (continuous throughout all phases)
Deployment (continuous integration + staging validation)
```

---

## Milestone 1: Foundation & RBAC (Months 1-3, Weeks 1-12)

### Objective
Establish technical foundation for all features: authentication, multi-layer RBAC, database schema with RLS, frontend layout, CI/CD pipeline.

### Success Criteria
- ✅ Authentication working (login, logout, session management, password reset)
- ✅ RBAC fully implemented (HTTP middleware, service guards, PostgreSQL RLS, frontend permissions)
- ✅ PostgreSQL schema deployed with migrations
- ✅ Vue.js 3 + TailAdmin layout rendering
- ✅ 95% test coverage (unit + integration tests)
- ✅ CI/CD pipeline functional (GitHub Actions)
- ✅ Deployment guide tested on bare metal Ubuntu server

---

### Week 1-2: Environment & Repository Setup

**Focus**: Initialize development environment and project structure

**Tasks**:

1. **Git Repository**
   - Initialize repository: `git init && git remote add origin <repo-url>`
   - Create .gitignore (PHP, Node.js, IDE files, .env)
   - Create README.md with project overview
   - Setup branch protection (main requires PR approval)

2. **CodeIgniter 4 Backend**
   - Install CI4: `composer create-project codeigniter4/appstarter openclient`
   - Configure app/Config/App.php (base URL, timezone UTC)
   - Setup domain-oriented structure: `app/Domain/` for business logic
   - Configure app/Config/Database.php for PostgreSQL

3. **PostgreSQL Database**
   - Install PostgreSQL 15+ on development machine
   - Create database: `CREATE DATABASE openclient_db;`
   - Create user: `CREATE USER openclient_user WITH PASSWORD 'secure_password';`
   - Grant permissions: `GRANT ALL PRIVILEGES ON DATABASE openclient_db TO openclient_user;`

4. **Vue.js 3 Frontend**
   - Initialize Vite: `npm init vite@latest resources/js --template vue`
   - Install dependencies: `npm install vue@3 vue-router@4 pinia axios`
   - Install TailAdmin template: Download from tailadmin.com → copy to resources/
   - Configure vite.config.js (build output to public/assets/)

5. **.env Configuration**
   ```env
   CI_ENVIRONMENT = development
   app.baseURL = 'http://localhost:8080/'

   database.default.hostname = localhost
   database.default.database = openclient_db
   database.default.username = openclient_user
   database.default.password = secure_password
   database.default.DBDriver = Postgre

   STRIPE_SECRET_KEY = sk_test_...
   STRIPE_PUBLISHABLE_KEY = pk_test_...
   STRIPE_WEBHOOK_SECRET = whsec_...
   ```

6. **Testing Framework**
   - Install PHPUnit: `composer require --dev phpunit/phpunit`
   - Create phpunit.xml with test database config
   - Install Vitest: `npm install --save-dev vitest @vue/test-utils`
   - Create vitest.config.js

7. **CI/CD Pipeline**
   - Create .github/workflows/tests.yml
   - Configure: `composer install → phpunit → npm install → vitest → build`
   - Add code coverage reporting (Codecov or Coveralls)

**Deliverables**:
- ✅ Repository with CI4 + Vue.js structure
- ✅ PostgreSQL database ready for migrations
- ✅ .env file configured (not committed)
- ✅ CI/CD pipeline passing

**Time**: 2 weeks (80 hours)

---

### Week 3-4: Database Schema Foundation

**Focus**: Design and implement core database schema with audit fields and RLS

**Tasks**:

1. **Create Migrations**

```bash
# Generate migration files
php spark make:migration create_agencies_table
php spark make:migration create_users_table
php spark make:migration create_sessions_table
php spark make:migration create_webhook_events_table
```

2. **Agencies Table**

```php
// app/Database/Migrations/2025-01-01-create_agencies_table.php
public function up()
{
    $this->forge->addField([
        'id' => ['type' => 'UUID', 'null' => false],
        'name' => ['type' => 'VARCHAR', 'constraint' => 255],
        'email' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
        'phone' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
        'created_at' => ['type' => 'TIMESTAMP', 'null' => false],
        'updated_at' => ['type' => 'TIMESTAMP', 'null' => false],
        'deleted_at' => ['type' => 'TIMESTAMP', 'null' => true],
    ]);
    $this->forge->addPrimaryKey('id');
    $this->forge->createTable('agencies');
}
```

3. **Users Table**

```php
// app/Database/Migrations/2025-01-02-create_users_table.php
public function up()
{
    $this->forge->addField([
        'id' => ['type' => 'UUID', 'null' => false],
        'agency_id' => ['type' => 'UUID', 'null' => true],
        'email' => ['type' => 'VARCHAR', 'constraint' => 255, 'unique' => true],
        'password_hash' => ['type' => 'VARCHAR', 'constraint' => 255],
        'role' => ['type' => 'ENUM', 'constraint' => ['owner', 'agency', 'end_client', 'direct_client']],
        'first_name' => ['type' => 'VARCHAR', 'constraint' => 100],
        'last_name' => ['type' => 'VARCHAR', 'constraint' => 100],
        'created_at' => ['type' => 'TIMESTAMP', 'null' => false],
        'updated_at' => ['type' => 'TIMESTAMP', 'null' => false],
        'deleted_at' => ['type' => 'TIMESTAMP', 'null' => true],
    ]);
    $this->forge->addPrimaryKey('id');
    $this->forge->addForeignKey('agency_id', 'agencies', 'id', 'SET NULL', 'CASCADE');
    $this->forge->createTable('users');
}
```

4. **Sessions Table** (for CodeIgniter session storage)

```php
public function up()
{
    $this->forge->addField([
        'id' => ['type' => 'VARCHAR', 'constraint' => 128, 'null' => false],
        'ip_address' => ['type' => 'VARCHAR', 'constraint' => 45],
        'timestamp' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
        'data' => ['type' => 'TEXT'],
    ]);
    $this->forge->addPrimaryKey('id');
    $this->forge->addKey('timestamp');
    $this->forge->createTable('ci_sessions');
}
```

5. **Webhook Events Table** (idempotency tracking)

```php
public function up()
{
    $this->forge->addField([
        'event_id' => ['type' => 'VARCHAR', 'constraint' => 255],
        'gateway' => ['type' => 'VARCHAR', 'constraint' => 50],
        'invoice_id' => ['type' => 'UUID', 'null' => true],
        'payload' => ['type' => 'JSON', 'null' => true],
        'processed_at' => ['type' => 'TIMESTAMP', 'null' => false],
    ]);
    $this->forge->addPrimaryKey('event_id');
    $this->forge->addKey('processed_at');
    $this->forge->createTable('webhook_events');
}
```

6. **Run Migrations**

```bash
php spark migrate
# Verify tables created: psql -U openclient_user -d openclient_db -c "\dt"
```

7. **Create Database Indexes**

```sql
-- app/Database/Migrations/2025-01-03-add_indexes.php
CREATE INDEX idx_users_agency ON users(agency_id);
CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_users_role ON users(role);
CREATE INDEX idx_webhook_events_processed ON webhook_events(processed_at);
```

8. **Enable PostgreSQL Row-Level Security**

```sql
-- app/Database/Migrations/2025-01-04-enable_rls.php

-- Enable RLS on users table (agencies not needed, only Owner manages agencies)
ALTER TABLE users ENABLE ROW LEVEL SECURITY;

-- Policy: Agency users only see users in their agency, Owner sees all
CREATE POLICY agency_isolation_users ON users
    USING (
        agency_id = current_setting('app.current_agency_id', true)::uuid
        OR current_setting('app.current_user_role', true) = 'owner'
        OR id = current_setting('app.current_user_id', true)::uuid
    );
```

9. **Create Model Factories for Testing**

```php
// tests/Support/Factories/AgencyFactory.php
<?php
namespace Tests\Support\Factories;

use CodeIgniter\Test\Fabricator;
use Faker\Generator;

class AgencyFactory extends Fabricator
{
    public function fake(Generator $faker): array
    {
        return [
            'id' => $faker->uuid,
            'name' => $faker->company,
            'email' => $faker->companyEmail,
            'phone' => $faker->phoneNumber,
        ];
    }
}
```

10. **Test Database Schema**

```php
// tests/Database/MigrationTest.php
<?php
namespace Tests\Database;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

class MigrationTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    public function testAgenciesTableExists()
    {
        $this->assertTrue($this->db->tableExists('agencies'));
    }

    public function testUsersTableHasRoleEnum()
    {
        $fields = $this->db->getFieldNames('users');
        $this->assertContains('role', $fields);
    }
}
```

**Deliverables**:
- ✅ PostgreSQL schema with agencies, users, sessions, webhook_events
- ✅ Database migrations runnable and reversible
- ✅ Indexes created for common queries
- ✅ RLS policies enabled (tested with SQL queries)
- ✅ Model factories for test data generation

**Time**: 2 weeks (80 hours)

---

### Week 5-6: Authentication System

**Focus**: Implement login/logout, session management, password security

**Tasks**:

1. **User Model**

```php
// app/Models/UserModel.php
<?php
namespace App\Models;

use CodeIgniter\Model;

class UserModel extends Model
{
    protected $table = 'users';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = false;
    protected $returnType = 'array';
    protected $useSoftDeletes = true;
    protected $allowedFields = ['id', 'agency_id', 'email', 'password_hash', 'role', 'first_name', 'last_name'];
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
    protected $deletedField = 'deleted_at';

    protected $beforeInsert = ['hashPassword', 'generateUUID'];
    protected $beforeUpdate = ['hashPassword'];

    protected function hashPassword(array $data): array
    {
        if (isset($data['data']['password'])) {
            $data['data']['password_hash'] = password_hash($data['data']['password'], PASSWORD_BCRYPT);
            unset($data['data']['password']);
        }
        return $data;
    }

    protected function generateUUID(array $data): array
    {
        if (!isset($data['data']['id'])) {
            $data['data']['id'] = \Ramsey\Uuid\Uuid::uuid4()->toString();
        }
        return $data;
    }
}
```

2. **Login Controller**

```php
// app/Controllers/AuthController.php
<?php
namespace App\Controllers;

use App\Models\UserModel;

class AuthController extends BaseController
{
    public function showLogin()
    {
        return view('auth/login');
    }

    public function login()
    {
        $email = $this->request->getPost('email');
        $password = $this->request->getPost('password');

        $userModel = new UserModel();
        $user = $userModel->where('email', $email)->first();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            // Increment failed login attempts
            $this->incrementFailedAttempts($email);
            return redirect()->back()->with('error', 'Invalid email or password.');
        }

        // Check if account is locked due to brute force
        if ($this->isAccountLocked($email)) {
            return redirect()->back()->with('error', 'Account locked due to too many failed attempts. Try again in 15 minutes.');
        }

        // Reset failed attempts on successful login
        $this->resetFailedAttempts($email);

        // Set session variables
        session()->set([
            'user' => [
                'id' => $user['id'],
                'email' => $user['email'],
                'role' => $user['role'],
                'agency_id' => $user['agency_id'],
                'first_name' => $user['first_name'],
                'last_name' => $user['last_name'],
            ],
            'logged_in' => true
        ]);

        // Set PostgreSQL session variables for RLS
        $this->db->query("SET app.current_user_id = ?", [$user['id']]);
        $this->db->query("SET app.current_user_role = ?", [$user['role']]);
        if ($user['agency_id']) {
            $this->db->query("SET app.current_agency_id = ?", [$user['agency_id']]);
        }

        return redirect()->to('/dashboard');
    }

    public function logout()
    {
        session()->destroy();
        return redirect()->to('/login');
    }

    private function incrementFailedAttempts(string $email): void
    {
        // Implementation: Store failed attempts in cache with 15-minute TTL
        $attempts = cache()->get("failed_login_{$email}") ?? 0;
        cache()->save("failed_login_{$email}", $attempts + 1, 900); // 15 minutes
    }

    private function isAccountLocked(string $email): bool
    {
        $attempts = cache()->get("failed_login_{$email}") ?? 0;
        return $attempts >= 5;
    }

    private function resetFailedAttempts(string $email): void
    {
        cache()->delete("failed_login_{$email}");
    }
}
```

3. **Login View**

```php
<!-- app/Views/auth/login.php -->
<!DOCTYPE html>
<html>
<head>
    <title>Login - openclient</title>
    <link rel="stylesheet" href="/assets/app.css">
</head>
<body class="bg-gray-100">
    <div class="min-h-screen flex items-center justify-center">
        <div class="max-w-md w-full bg-white rounded-lg shadow-md p-8">
            <h2 class="text-2xl font-bold mb-6">Login to openclient</h2>

            <?php if (session()->getFlashdata('error')): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                    <?= session()->getFlashdata('error') ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="/auth/login">
                <?= csrf_field() ?>

                <div class="mb-4">
                    <label class="block text-gray-700 mb-2" for="email">Email</label>
                    <input
                        type="email"
                        name="email"
                        id="email"
                        class="w-full px-3 py-2 border border-gray-300 rounded"
                        required
                        autocomplete="email"
                    >
                </div>

                <div class="mb-6">
                    <label class="block text-gray-700 mb-2" for="password">Password</label>
                    <input
                        type="password"
                        name="password"
                        id="password"
                        class="w-full px-3 py-2 border border-gray-300 rounded"
                        required
                        autocomplete="current-password"
                    >
                </div>

                <button
                    type="submit"
                    class="w-full bg-blue-600 text-white py-2 px-4 rounded hover:bg-blue-700"
                >
                    Sign In
                </button>

                <div class="mt-4 text-center">
                    <a href="/auth/forgot-password" class="text-blue-600 hover:underline">
                        Forgot password?
                    </a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
```

4. **Login Filter** (authentication middleware)

```php
// app/Filters/LoginFilter.php
<?php
namespace App\Filters;

use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Filters\FilterInterface;

class LoginFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        if (!session()->get('logged_in')) {
            return redirect()->to('/login');
        }

        // Refresh PostgreSQL session variables on each request
        $user = session()->get('user');
        if ($user) {
            $db = \Config\Database::connect();
            $db->query("SET app.current_user_id = ?", [$user['id']]);
            $db->query("SET app.current_user_role = ?", [$user['role']]);
            if (isset($user['agency_id'])) {
                $db->query("SET app.current_agency_id = ?", [$user['agency_id']]);
            }
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return $response;
    }
}
```

5. **Register Filter in Config**

```php
// app/Config/Filters.php
public $aliases = [
    'login' => \App\Filters\LoginFilter::class,
    'rbac' => \App\Filters\RBACFilter::class,
];

public $globals = [
    'before' => [
        'login' => ['except' => ['auth/login', 'auth/forgot-password', 'auth/reset-password']],
    ],
];
```

6. **Session Configuration**

```php
// app/Config/App.php
public $sessionDriver = 'CodeIgniter\Session\Handlers\DatabaseHandler';
public $sessionCookieName = 'openclient_session';
public $sessionExpiration = 1800; // 30 minutes (per stakeholder requirement)
public $sessionSavePath = 'ci_sessions';
public $sessionMatchIP = false;
public $sessionTimeToUpdate = 300;
public $sessionRegenerateDestroy = false;
```

7. **Password Reset Flow** (basic implementation)

```php
// app/Controllers/AuthController.php
public function showForgotPassword()
{
    return view('auth/forgot_password');
}

public function forgotPassword()
{
    $email = $this->request->getPost('email');
    $userModel = new UserModel();
    $user = $userModel->where('email', $email)->first();

    if (!$user) {
        // Don't reveal if email exists (security best practice)
        return redirect()->back()->with('success', 'If the email exists, a reset link has been sent.');
    }

    // Generate reset token
    $token = bin2hex(random_bytes(32));
    cache()->save("password_reset_{$token}", $user['id'], 3600); // 1 hour expiry

    // Send email with reset link
    $resetUrl = base_url("auth/reset-password/{$token}");
    // TODO: Implement email sending (use CodeIgniter Email library or service like SendGrid)

    return redirect()->back()->with('success', 'If the email exists, a reset link has been sent.');
}

public function showResetPassword(string $token)
{
    $userId = cache()->get("password_reset_{$token}");
    if (!$userId) {
        return redirect()->to('/login')->with('error', 'Invalid or expired reset link.');
    }

    return view('auth/reset_password', ['token' => $token]);
}

public function resetPassword()
{
    $token = $this->request->getPost('token');
    $password = $this->request->getPost('password');
    $passwordConfirm = $this->request->getPost('password_confirm');

    if ($password !== $passwordConfirm) {
        return redirect()->back()->with('error', 'Passwords do not match.');
    }

    // Validate password strength (12+ chars, mixed case, number)
    if (!$this->isPasswordStrong($password)) {
        return redirect()->back()->with('error', 'Password must be at least 12 characters with uppercase, lowercase, and number.');
    }

    $userId = cache()->get("password_reset_{$token}");
    if (!$userId) {
        return redirect()->to('/login')->with('error', 'Invalid or expired reset link.');
    }

    // Update password
    $userModel = new UserModel();
    $userModel->update($userId, ['password' => $password]); // hashPassword hook will bcrypt

    // Invalidate token
    cache()->delete("password_reset_{$token}");

    return redirect()->to('/login')->with('success', 'Password reset successful. Please log in.');
}

private function isPasswordStrong(string $password): bool
{
    return strlen($password) >= 12
        && preg_match('/[A-Z]/', $password) // uppercase
        && preg_match('/[a-z]/', $password) // lowercase
        && preg_match('/[0-9]/', $password); // number
}
```

8. **Unit Tests for Authentication**

```php
// tests/Unit/AuthTest.php
<?php
namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use App\Models\UserModel;

class AuthTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    public function testPasswordIsHashed()
    {
        $userModel = new UserModel();
        $user = $userModel->insert([
            'email' => 'test@example.com',
            'password' => 'PlainPassword123',
            'role' => 'owner',
            'first_name' => 'Test',
            'last_name' => 'User',
        ]);

        $userRecord = $userModel->find($user);
        $this->assertNotEquals('PlainPassword123', $userRecord['password_hash']);
        $this->assertTrue(password_verify('PlainPassword123', $userRecord['password_hash']));
    }

    public function testLoginWithValidCredentials()
    {
        // Create test user
        $userModel = new UserModel();
        $userModel->insert([
            'email' => 'valid@example.com',
            'password' => 'ValidPass123',
            'role' => 'owner',
            'first_name' => 'Valid',
            'last_name' => 'User',
        ]);

        // Simulate login POST request
        $response = $this->post('/auth/login', [
            'email' => 'valid@example.com',
            'password' => 'ValidPass123',
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertTrue(session()->get('logged_in'));
    }

    public function testLoginWithInvalidCredentials()
    {
        $response = $this->post('/auth/login', [
            'email' => 'invalid@example.com',
            'password' => 'WrongPassword',
        ]);

        $response->assertRedirect(); // back to login
        $this->assertFalse(session()->get('logged_in'));
    }

    public function testBruteForceProtection()
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/auth/login', [
                'email' => 'brute@example.com',
                'password' => 'WrongPassword',
            ]);
        }

        // 6th attempt should be blocked
        $response = $this->post('/auth/login', [
            'email' => 'brute@example.com',
            'password' => 'CorrectPassword123',
        ]);

        $response->assertSee('Account locked');
    }
}
```

**Deliverables**:
- ✅ User authentication working (login, logout)
- ✅ Session management (30-minute timeout)
- ✅ Password hashing with bcrypt
- ✅ Brute force protection (5 attempts → 15-minute lockout)
- ✅ Password reset flow (token-based)
- ✅ Unit tests for auth (95% coverage)

**Time**: 2 weeks (80 hours)

---

### Week 7-8: RBAC Layer 1 (PostgreSQL RLS)

**Focus**: Implement database-enforced multi-agency isolation using Row-Level Security

**Tasks**:

1. **Database Connection Hook** (set session variables)

```php
// app/Config/Database.php
<?php
namespace Config;

use CodeIgniter\Database\Config;

class Database extends Config
{
    // ... existing config

    public static function connect($group = null, bool $getShared = true)
    {
        $db = parent::connect($group, $getShared);

        // Set PostgreSQL session variables after connection
        $user = session()->get('user');
        if ($user) {
            $db->query("SET app.current_user_id = ?", [$user['id']]);
            $db->query("SET app.current_user_role = ?", [$user['role']]);
            if (isset($user['agency_id'])) {
                $db->query("SET app.current_agency_id = ?", [$user['agency_id']]);
            }
        }

        return $db;
    }
}
```

2. **Add agency_id to Future Tables** (for RLS)

```php
// Note: This migration will be applied to each tenant-scoped table as we create them
// Example for future clients table:

// app/Database/Migrations/2025-02-01-create_clients_table.php
public function up()
{
    $this->forge->addField([
        'id' => ['type' => 'UUID', 'null' => false],
        'agency_id' => ['type' => 'UUID', 'null' => false], // REQUIRED for RLS
        'name' => ['type' => 'VARCHAR', 'constraint' => 255],
        // ... other fields
    ]);
    $this->forge->addPrimaryKey('id');
    $this->forge->addForeignKey('agency_id', 'agencies', 'id', 'CASCADE', 'CASCADE');
    $this->forge->createTable('clients');

    // Enable RLS
    $this->db->query("ALTER TABLE clients ENABLE ROW LEVEL SECURITY");

    // Create RLS policy
    $this->db->query("
        CREATE POLICY agency_isolation_clients ON clients
        USING (
            agency_id = current_setting('app.current_agency_id', true)::uuid
            OR current_setting('app.current_user_role', true) = 'owner'
        )
    ");
}
```

3. **Test RLS Enforcement** (manual SQL verification)

```sql
-- Connect as test user with agency_id set
SET app.current_agency_id = '<agency-a-uuid>';
SET app.current_user_role = 'agency';

-- Query should only return rows where agency_id matches
SELECT * FROM clients;
-- Result: Only Agency A's clients returned

-- Change to different agency
SET app.current_agency_id = '<agency-b-uuid>';
SELECT * FROM clients;
-- Result: Only Agency B's clients returned (different rows)

-- Test Owner bypass
SET app.current_user_role = 'owner';
SELECT * FROM clients;
-- Result: ALL clients returned (Owner sees everything)
```

4. **Create Integration Test for RLS**

```php
// tests/Integration/RBAC/RowLevelSecurityTest.php
<?php
namespace Tests\Integration\RBAC;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

class RowLevelSecurityTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $agencyA;
    protected $agencyB;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test agencies
        $this->agencyA = $this->createAgency('Agency A');
        $this->agencyB = $this->createAgency('Agency B');

        // Create test users for each agency
        $this->userAgencyA = $this->createUser([
            'email' => 'alice@agencyA.com',
            'role' => 'agency',
            'agency_id' => $this->agencyA['id']
        ]);

        $this->userAgencyB = $this->createUser([
            'email' => 'bob@agencyB.com',
            'role' => 'agency',
            'agency_id' => $this->agencyB['id']
        ]);

        $this->userOwner = $this->createUser([
            'email' => 'owner@openclient.com',
            'role' => 'owner',
            'agency_id' => null
        ]);

        // Create test clients (will be tested once clients table exists)
        $this->clientAlpha = $this->createClient([
            'name' => 'Client Alpha',
            'agency_id' => $this->agencyA['id']
        ]);

        $this->clientBeta = $this->createClient([
            'name' => 'Client Beta',
            'agency_id' => $this->agencyB['id']
        ]);
    }

    public function testAgencyACannotSeeAgencyBClients()
    {
        // Simulate Agency A user logged in
        $this->actingAs($this->userAgencyA);

        // Set PostgreSQL session variables (normally done by LoginFilter)
        $this->db->query("SET app.current_user_role = ?", ['agency']);
        $this->db->query("SET app.current_agency_id = ?", [$this->agencyA['id']]);

        // Query clients
        $clients = $this->db->table('clients')->get()->getResultArray();

        // Assert only Agency A's client returned
        $this->assertCount(1, $clients);
        $this->assertEquals('Client Alpha', $clients[0]['name']);
        $this->assertNotContains('Client Beta', array_column($clients, 'name'));
    }

    public function testOwnerCanSeeAllAgenciesClients()
    {
        // Simulate Owner user logged in
        $this->actingAs($this->userOwner);

        // Set PostgreSQL session variables
        $this->db->query("SET app.current_user_role = ?", ['owner']);

        // Query clients
        $clients = $this->db->table('clients')->get()->getResultArray();

        // Assert both agencies' clients returned
        $this->assertCount(2, $clients);
        $this->assertContains('Client Alpha', array_column($clients, 'name'));
        $this->assertContains('Client Beta', array_column($clients, 'name'));
    }

    public function testDirectSQLInjectionCannotBypassRLS()
    {
        // Simulate malicious Agency A user trying SQL injection
        $this->actingAs($this->userAgencyA);
        $this->db->query("SET app.current_user_role = ?", ['agency']);
        $this->db->query("SET app.current_agency_id = ?", [$this->agencyA['id']]);

        // Attempt to bypass RLS with raw SQL (this should still be filtered by RLS)
        $maliciousQuery = "SELECT * FROM clients WHERE agency_id != '{$this->agencyA['id']}'";
        $clients = $this->db->query($maliciousQuery)->getResultArray();

        // Assert: RLS policy STILL filters results, attacker gets 0 rows (not Agency B's clients)
        $this->assertCount(0, $clients);
    }
}
```

5. **Document RLS Implementation**

```markdown
<!-- docs/architecture/rbac-database.md -->
# RBAC Implementation: Database Layer (PostgreSQL RLS)

## Overview
Multi-agency data isolation is enforced at the **database level** using PostgreSQL Row-Level Security (RLS). This ensures that:
- Agency users can only access data for their assigned agency
- Owner users can access data for all agencies
- RLS policies cannot be bypassed via SQL injection or direct database queries

## How It Works

### 1. Session Variables
When a user logs in, PostgreSQL session variables are set:
```sql
SET app.current_user_id = '<user-uuid>';
SET app.current_user_role = 'agency'; -- or 'owner', 'end_client', 'direct_client'
SET app.current_agency_id = '<agency-uuid>'; -- if user has agency assignment
```

These variables are set in:
- `app/Config/Database.php::connect()` (on connection establishment)
- `app/Filters/LoginFilter.php::before()` (on each HTTP request)

### 2. RLS Policies
Each tenant-scoped table has an RLS policy:
```sql
ALTER TABLE clients ENABLE ROW LEVEL SECURITY;

CREATE POLICY agency_isolation_clients ON clients
USING (
    agency_id = current_setting('app.current_agency_id', true)::uuid
    OR current_setting('app.current_user_role', true) = 'owner'
);
```

**Policy Logic**:
- `agency_id = current_setting('app.current_agency_id')` → Agency users see their agency's data
- `OR current_user_role = 'owner'` → Owner bypasses filter, sees all data

### 3. Automatic Filtering
Every query is automatically filtered by PostgreSQL:
```php
// In code: SELECT * FROM clients;
// PostgreSQL executes: SELECT * FROM clients WHERE (agency_id = '<current-agency-id>' OR '<current-role>' = 'owner');
```

**Result**: Agency A users physically cannot retrieve Agency B's rows, even with SQL injection.

## Security Benefits
- ✅ **Defense in depth**: Even if application code has bugs, database enforces isolation
- ✅ **SQL injection protection**: Attacker cannot bypass RLS with malicious queries
- ✅ **Audit trail**: PostgreSQL logs all queries, security violations visible
- ✅ **Zero trust**: Application code doesn't need to remember to filter by agency_id

## Performance Considerations
- **Index requirement**: All tenant-scoped tables MUST have index on `agency_id`
- **Query planning**: PostgreSQL query planner handles RLS efficiently with proper indexes
- **Benchmarking**: Monitor query performance, ensure no RLS overhead for Owner role
```

**Deliverables**:
- ✅ PostgreSQL session variables set on connection and per-request
- ✅ RLS policies created (tested with manual SQL)
- ✅ Integration test for multi-agency isolation
- ✅ Documentation of RLS implementation

**Time**: 2 weeks (80 hours)

---

### Week 9-10: RBAC Layer 2 (HTTP Middleware)

**Focus**: Implement route-level authorization to block unauthorized access early

**Tasks**:

1. **Create RBAC Filter**

```php
// app/Filters/RBACFilter.php
<?php
namespace App\Filters;

use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Filters\FilterInterface;

class RBACFilter implements FilterInterface
{
    // Financial routes restricted for End Clients
    private const FINANCIAL_ROUTES = [
        '/invoices',
        '/quotes',
        '/billing',
        '/payments',
        '/reports/financial',
    ];

    // Admin routes restricted to Owner only
    private const ADMIN_ROUTES = [
        '/admin',
        '/settings',
        '/users',
    ];

    public function before(RequestInterface $request, $arguments = null)
    {
        $user = session()->get('user');
        if (!$user) {
            // Should not reach here (LoginFilter catches unauthenticated first)
            return redirect()->to('/login');
        }

        $uri = $request->getUri()->getPath();
        $role = $user['role'];

        // End Clients cannot access financial features
        if ($role === 'end_client') {
            foreach (self::FINANCIAL_ROUTES as $route) {
                if (str_starts_with($uri, $route)) {
                    // Log security violation
                    log_message('warning', "Access denied: End Client {$user['email']} attempted to access {$uri}");

                    return redirect()->to('/dashboard')
                        ->with('error', 'You do not have permission to access financial features.');
                }
            }
        }

        // Only Owner can access admin routes
        if ($role !== 'owner') {
            foreach (self::ADMIN_ROUTES as $route) {
                if (str_starts_with($uri, $route)) {
                    log_message('warning', "Access denied: {$role} user {$user['email']} attempted to access {$uri}");

                    return redirect()->to('/dashboard')
                        ->with('error', 'You do not have permission to access admin features.');
                }
            }
        }

        // Agency users must have agency_id assigned
        if ($role === 'agency' && !$user['agency_id']) {
            return redirect()->to('/dashboard')
                ->with('error', 'Your account is not assigned to an agency. Please contact the administrator.');
        }

        return $request;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return $response;
    }
}
```

2. **Register RBAC Filter**

```php
// app/Config/Filters.php
public $aliases = [
    'login' => \App\Filters\LoginFilter::class,
    'rbac' => \App\Filters\RBACFilter::class,
];

public $globals = [
    'before' => [
        'login' => ['except' => ['auth/*']],
        'rbac' => ['except' => ['auth/*', 'dashboard']], // RBAC runs after login
    ],
];
```

3. **Create Security Audit Log**

```php
// app/Helpers/SecurityLogger.php
<?php
namespace App\Helpers;

class SecurityLogger
{
    public static function logAccessDenied(array $user, string $resource, string $reason): void
    {
        $logData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'event' => 'ACCESS_DENIED',
            'user_id' => $user['id'],
            'user_email' => $user['email'],
            'user_role' => $user['role'],
            'attempted_resource' => $resource,
            'reason' => $reason,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        ];

        // Write to security log file
        $logFile = WRITEPATH . 'logs/security-' . date('Y-m-d') . '.log';
        file_put_contents($logFile, json_encode($logData) . PHP_EOL, FILE_APPEND);

        // Also log to CodeIgniter log
        log_message('warning', "Security: {$reason} - User {$user['email']} ({$user['role']}) attempted {$resource}");
    }
}
```

4. **Integration Tests for HTTP Middleware**

```php
// tests/Integration/RBAC/HttpMiddlewareTest.php
<?php
namespace Tests\Integration\RBAC;

use CodeIgniter\Test\FeatureTestTrait;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

class HttpMiddlewareTest extends CIUnitTestCase
{
    use FeatureTestTrait, DatabaseTestTrait;

    public function testEndClientBlockedFromInvoices()
    {
        // Create End Client user and log in
        $endClient = $this->createUser([
            'email' => 'endclient@test.com',
            'role' => 'end_client',
            'password' => 'TestPass123',
        ]);

        // Simulate login
        $_SESSION['logged_in'] = true;
        $_SESSION['user'] = $endClient;

        // Attempt to access invoices
        $response = $this->get('/invoices');

        // Assert: Redirect to dashboard with error
        $response->assertRedirectTo('/dashboard');
        $this->assertStringContainsString('do not have permission', session()->getFlashdata('error'));
    }

    public function testAgencyBlockedFromAdmin()
    {
        $agency = $this->createUser([
            'email' => 'agency@test.com',
            'role' => 'agency',
            'agency_id' => $this->createAgency('Test Agency')['id'],
            'password' => 'TestPass123',
        ]);

        $_SESSION['logged_in'] = true;
        $_SESSION['user'] = $agency;

        $response = $this->get('/admin/settings');

        $response->assertRedirectTo('/dashboard');
        $this->assertStringContainsString('do not have permission', session()->getFlashdata('error'));
    }

    public function testOwnerCanAccessAllRoutes()
    {
        $owner = $this->createUser([
            'email' => 'owner@test.com',
            'role' => 'owner',
            'password' => 'TestPass123',
        ]);

        $_SESSION['logged_in'] = true;
        $_SESSION['user'] = $owner;

        // Owner can access invoices
        $response = $this->get('/invoices');
        $response->assertOK();

        // Owner can access admin
        $response = $this->get('/admin/settings');
        $response->assertOK();
    }

    public function testSecurityLogRecordsAccessDenial()
    {
        $endClient = $this->createUser(['email' => 'test@test.com', 'role' => 'end_client']);
        $_SESSION['logged_in'] = true;
        $_SESSION['user'] = $endClient;

        $this->get('/invoices');

        // Check security log file exists and contains denial
        $logFile = WRITEPATH . 'logs/security-' . date('Y-m-d') . '.log';
        $this->assertFileExists($logFile);

        $logContent = file_get_contents($logFile);
        $this->assertStringContainsString('ACCESS_DENIED', $logContent);
        $this->assertStringContainsString('test@test.com', $logContent);
        $this->assertStringContainsString('/invoices', $logContent);
    }
}
```

**Deliverables**:
- ✅ HTTP middleware blocking unauthorized routes
- ✅ Security audit logging for access denials
- ✅ Integration tests for all RBAC scenarios
- ✅ End Clients blocked from financial routes
- ✅ Non-Owners blocked from admin routes

**Time**: 2 weeks (80 hours)

---

### Week 11-12: RBAC Layer 3 (Service Layer Guards)

**Focus**: Implement fine-grained authorization checks in business logic

**Tasks**:

1. **Authorization Guard Interface**

```php
// app/Domain/Authorization/AuthorizationGuardInterface.php
<?php
namespace App\Domain\Authorization;

interface AuthorizationGuardInterface
{
    public function canView(array $user, $resource): bool;
    public function canCreate(array $user): bool;
    public function canEdit(array $user, $resource): bool;
    public function canDelete(array $user, $resource): bool;
}
```

2. **Invoice Authorization Guard** (example implementation)

```php
// app/Domain/Invoices/Authorization/InvoiceGuard.php
<?php
namespace App\Domain\Invoices\Authorization;

use App\Domain\Authorization\AuthorizationGuardInterface;

class InvoiceGuard implements AuthorizationGuardInterface
{
    public function canView(array $user, $invoice): bool
    {
        // Owner: always can view all invoices
        if ($user['role'] === 'owner') {
            return true;
        }

        // End Client: CANNOT view invoices (financial restriction)
        if ($user['role'] === 'end_client') {
            return false;
        }

        // Agency: can view if invoice belongs to their agency
        if ($user['role'] === 'agency') {
            return $invoice['agency_id'] === $user['agency_id'];
        }

        // Direct Client: can view if invoice is for their client record
        if ($user['role'] === 'direct_client') {
            // Assuming invoice has client_id field
            return $this->isUserAssignedToClient($user['id'], $invoice['client_id']);
        }

        return false;
    }

    public function canCreate(array $user): bool
    {
        // Owner and Agency can create invoices
        return in_array($user['role'], ['owner', 'agency']);
    }

    public function canEdit(array $user, $invoice): bool
    {
        // Only Owner and Agency (if it's their invoice) can edit
        if ($user['role'] === 'owner') {
            return true;
        }

        if ($user['role'] === 'agency') {
            return $invoice['agency_id'] === $user['agency_id'];
        }

        return false;
    }

    public function canDelete(array $user, $invoice): bool
    {
        // Only Owner can delete invoices
        return $user['role'] === 'owner';
    }

    private function isUserAssignedToClient(string $userId, string $clientId): bool
    {
        // Check if user is associated with this client
        $db = \Config\Database::connect();
        $query = $db->table('client_users')
            ->where('user_id', $userId)
            ->where('client_id', $clientId)
            ->get();

        return $query->getNumRows() > 0;
    }
}
```

3. **Project Authorization Guard**

```php
// app/Domain/Projects/Authorization/ProjectGuard.php
<?php
namespace App\Domain\Projects\Authorization;

use App\Domain\Authorization\AuthorizationGuardInterface;

class ProjectGuard implements AuthorizationGuardInterface
{
    public function canView(array $user, $project): bool
    {
        if ($user['role'] === 'owner') {
            return true;
        }

        // Check if user is assigned to this project
        return $this->isUserAssignedToProject($user['id'], $project['id']);
    }

    public function canCreate(array $user): bool
    {
        return in_array($user['role'], ['owner', 'agency']);
    }

    public function canEdit(array $user, $project): bool
    {
        if ($user['role'] === 'owner') {
            return true;
        }

        if ($user['role'] === 'agency' && $project['agency_id'] === $user['agency_id']) {
            return true;
        }

        return false;
    }

    public function canDelete(array $user, $project): bool
    {
        return $user['role'] === 'owner';
    }

    private function isUserAssignedToProject(string $userId, string $projectId): bool
    {
        $db = \Config\Database::connect();
        $query = $db->table('project_members')
            ->where('user_id', $userId)
            ->where('project_id', $projectId)
            ->get();

        return $query->getNumRows() > 0;
    }
}
```

4. **Use Guards in Controllers**

```php
// app/Controllers/InvoicesController.php
<?php
namespace App\Controllers;

use App\Domain\Invoices\Authorization\InvoiceGuard;
use App\Models\InvoiceModel;

class InvoicesController extends BaseController
{
    protected $invoiceGuard;

    public function __construct()
    {
        $this->invoiceGuard = new InvoiceGuard();
    }

    public function index()
    {
        // List all invoices (filtered by RLS automatically)
        $invoiceModel = new InvoiceModel();
        $invoices = $invoiceModel->findAll();

        return $this->response->setJSON([
            'success' => true,
            'data' => $invoices
        ]);
    }

    public function show($invoiceId)
    {
        $user = session()->get('user');
        $invoiceModel = new InvoiceModel();
        $invoice = $invoiceModel->find($invoiceId);

        if (!$invoice) {
            return $this->response->setStatusCode(404)->setJSON([
                'success' => false,
                'error' => 'Invoice not found.'
            ]);
        }

        // Service layer authorization check
        if (!$this->invoiceGuard->canView($user, $invoice)) {
            \App\Helpers\SecurityLogger::logAccessDenied($user, "invoice:{$invoiceId}", "Insufficient permissions");

            return $this->response->setStatusCode(403)->setJSON([
                'success' => false,
                'error' => 'You do not have permission to view this invoice.'
            ]);
        }

        return $this->response->setJSON([
            'success' => true,
            'data' => $invoice
        ]);
    }

    public function create()
    {
        $user = session()->get('user');

        if (!$this->invoiceGuard->canCreate($user)) {
            return $this->response->setStatusCode(403)->setJSON([
                'success' => false,
                'error' => 'You do not have permission to create invoices.'
            ]);
        }

        $data = $this->request->getJSON(true);
        // ... validation and invoice creation logic

        return $this->response->setJSON([
            'success' => true,
            'data' => $createdInvoice
        ]);
    }

    public function delete($invoiceId)
    {
        $user = session()->get('user');
        $invoiceModel = new InvoiceModel();
        $invoice = $invoiceModel->find($invoiceId);

        if (!$invoice) {
            return $this->response->setStatusCode(404)->setJSON([
                'success' => false,
                'error' => 'Invoice not found.'
            ]);
        }

        if (!$this->invoiceGuard->canDelete($user, $invoice)) {
            \App\Helpers\SecurityLogger::logAccessDenied($user, "invoice:{$invoiceId}", "Delete restricted to Owner");

            return $this->response->setStatusCode(403)->setJSON([
                'success' => false,
                'error' => 'Only Owners can delete invoices.'
            ]);
        }

        $invoiceModel->delete($invoiceId);

        return $this->response->setJSON([
            'success' => true,
            'message' => 'Invoice deleted successfully.'
        ]);
    }
}
```

5. **Unit Tests for Guards**

```php
// tests/Unit/InvoiceGuardTest.php
<?php
namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use App\Domain\Invoices\Authorization\InvoiceGuard;

class InvoiceGuardTest extends CIUnitTestCase
{
    protected $guard;

    protected function setUp(): void
    {
        parent::setUp();
        $this->guard = new InvoiceGuard();
    }

    public function testOwnerCanViewAllInvoices()
    {
        $owner = ['id' => 'owner-123', 'role' => 'owner'];
        $invoice = ['id' => 'inv-123', 'agency_id' => 'agency-456'];

        $this->assertTrue($this->guard->canView($owner, $invoice));
    }

    public function testEndClientCannotViewInvoices()
    {
        $endClient = ['id' => 'user-123', 'role' => 'end_client'];
        $invoice = ['id' => 'inv-123', 'agency_id' => 'agency-456'];

        $this->assertFalse($this->guard->canView($endClient, $invoice));
    }

    public function testAgencyCanViewOwnInvoice()
    {
        $agency = ['id' => 'user-123', 'role' => 'agency', 'agency_id' => 'agency-456'];
        $invoice = ['id' => 'inv-123', 'agency_id' => 'agency-456'];

        $this->assertTrue($this->guard->canView($agency, $invoice));
    }

    public function testAgencyCannotViewOtherAgencyInvoice()
    {
        $agency = ['id' => 'user-123', 'role' => 'agency', 'agency_id' => 'agency-456'];
        $invoice = ['id' => 'inv-123', 'agency_id' => 'agency-789'];

        $this->assertFalse($this->guard->canView($agency, $invoice));
    }

    public function testOnlyOwnerCanDeleteInvoices()
    {
        $owner = ['id' => 'owner-123', 'role' => 'owner'];
        $agency = ['id' => 'agency-123', 'role' => 'agency', 'agency_id' => 'agency-456'];
        $invoice = ['id' => 'inv-123', 'agency_id' => 'agency-456'];

        $this->assertTrue($this->guard->canDelete($owner, $invoice));
        $this->assertFalse($this->guard->canDelete($agency, $invoice));
    }
}
```

6. **Documentation**

```markdown
<!-- docs/architecture/rbac-service.md -->
# RBAC Implementation: Service Layer Guards

## Overview
Service layer authorization provides **fine-grained access control** at the business logic level. Guards implement specific permission logic for each resource type (invoices, projects, tasks, etc.).

## Architecture

### Guard Interface
All guards implement `AuthorizationGuardInterface`:
```php
public function canView(array $user, $resource): bool;
public function canCreate(array $user): bool;
public function canEdit(array $user, $resource): bool;
public function canDelete(array $user, $resource): bool;
```

### Guard Responsibilities
- **canView**: Determine if user can view a specific resource
- **canCreate**: Determine if user can create new resources
- **canEdit**: Determine if user can modify a specific resource
- **canDelete**: Determine if user can delete a specific resource

### Usage in Controllers
1. Inject guard into controller constructor
2. Call guard method before performing action
3. Return 403 Forbidden if guard returns false
4. Log security violation with SecurityLogger

### Testing Strategy
- Unit test each guard method with all role combinations
- Integration test controller enforcement (403 responses)
- Verify security audit log captures denials

## Benefits
- ✅ **Explicit authorization**: Permission logic is visible and testable
- ✅ **Reusable**: Guards used across controllers and services
- ✅ **Auditable**: All denials logged for security review
- ✅ **Type-safe**: PHP type hints prevent errors
```

**Deliverables**:
- ✅ Authorization guard interface defined
- ✅ InvoiceGuard and ProjectGuard implemented
- ✅ Controllers use guards for all CRUD operations
- ✅ Unit tests for all guard methods (95% coverage)
- ✅ Security logging for authorization failures

**Time**: 2 weeks (80 hours)

---

### Week 13: RBAC Layer 4 (Frontend Permissions)

**Focus**: Implement UI permission checks for better UX (with understanding that frontend is NOT security)

**Tasks**:

1. **Pinia User Store**

```javascript
// resources/js/stores/user.js
import { defineStore } from 'pinia'
import { ref, computed } from 'vue'

export const useUserStore = defineStore('user', () => {
  // State
  const id = ref(null)
  const email = ref(null)
  const role = ref(null)
  const agencyId = ref(null)
  const firstName = ref(null)
  const lastName = ref(null)

  // Computed properties (permission checks)
  const canViewFinancials = computed(() => {
    return ['owner', 'agency', 'direct_client'].includes(role.value)
  })

  const canManageUsers = computed(() => {
    return role.value === 'owner'
  })

  const canManageAgencySettings = computed(() => {
    return role.value === 'agency'
  })

  const isOwner = computed(() => role.value === 'owner')
  const isAgency = computed(() => role.value === 'agency')
  const isEndClient = computed(() => role.value === 'end_client')
  const isDirectClient = computed(() => role.value === 'direct_client')

  const fullName = computed(() => {
    return `${firstName.value} ${lastName.value}`
  })

  // Actions
  function init(userData) {
    id.value = userData.id
    email.value = userData.email
    role.value = userData.role
    agencyId.value = userData.agency_id
    firstName.value = userData.first_name
    lastName.value = userData.last_name
  }

  function clear() {
    id.value = null
    email.value = null
    role.value = null
    agencyId.value = null
    firstName.value = null
    lastName.value = null
  }

  return {
    // State
    id,
    email,
    role,
    agencyId,
    firstName,
    lastName,
    // Computed
    canViewFinancials,
    canManageUsers,
    canManageAgencySettings,
    isOwner,
    isAgency,
    isEndClient,
    isDirectClient,
    fullName,
    // Actions
    init,
    clear
  }
})
```

2. **Initialize User Store from Server Data**

```php
<!-- app/Views/layouts/app.php -->
<!DOCTYPE html>
<html>
<head>
    <title><?= $title ?? 'openclient' ?></title>
    <link rel="stylesheet" href="/assets/app.css">
</head>
<body>
    <div id="app">
        <?= $this->renderSection('content') ?>
    </div>

    <script type="module">
        import { createApp } from 'vue'
        import { createPinia } from 'pinia'
        import { useUserStore } from '/assets/js/stores/user.js'

        const app = createApp({})
        const pinia = createPinia()
        app.use(pinia)

        // Initialize user store with server-side data
        const userStore = useUserStore()
        userStore.init(<?= json_encode(session()->get('user')) ?>)

        app.mount('#app')
    </script>

    <?= $this->renderSection('scripts') ?>
</body>
</html>
```

3. **Sidebar Component with Permission Checks**

```vue
<!-- resources/js/components/layout/Sidebar.vue -->
<script setup>
import { useUserStore } from '@/stores/user'
import { useRouter, useRoute } from 'vue-router'

const userStore = useUserStore()
const router = useRouter()
const route = useRoute()

const isActive = (path) => route.path === path
</script>

<template>
  <aside class="w-64 bg-gray-800 text-white min-h-screen">
    <!-- Logo -->
    <div class="p-4 border-b border-gray-700">
      <h1 class="text-2xl font-bold">openclient</h1>
    </div>

    <!-- Navigation -->
    <nav class="p-4">
      <!-- Dashboard (everyone) -->
      <router-link
        to="/dashboard"
        class="flex items-center px-4 py-3 mb-2 rounded"
        :class="isActive('/dashboard') ? 'bg-blue-600' : 'hover:bg-gray-700'"
      >
        <svg class="w-5 h-5 mr-3" fill="currentColor" viewBox="0 0 20 20">
          <path d="M3 4a1 1 0 011-1h12a1 1 0 011 1v2a1 1 0 01-1 1H4a1 1 0 01-1-1V4z" />
        </svg>
        Dashboard
      </router-link>

      <!-- Clients (everyone) -->
      <router-link
        to="/clients"
        class="flex items-center px-4 py-3 mb-2 rounded hover:bg-gray-700"
      >
        Clients
      </router-link>

      <!-- Projects (everyone) -->
      <router-link
        to="/projects"
        class="flex items-center px-4 py-3 mb-2 rounded hover:bg-gray-700"
      >
        Projects
      </router-link>

      <!-- Financial section (hidden for End Clients) -->
      <template v-if="userStore.canViewFinancials">
        <div class="mt-6 mb-2 px-4 text-xs font-semibold text-gray-400 uppercase">
          Financial
        </div>

        <router-link
          to="/invoices"
          class="flex items-center px-4 py-3 mb-2 rounded hover:bg-gray-700"
        >
          Invoices
        </router-link>

        <router-link
          to="/quotes"
          class="flex items-center px-4 py-3 mb-2 rounded hover:bg-gray-700"
        >
          Quotes
        </router-link>

        <router-link
          to="/payments"
          class="flex items-center px-4 py-3 mb-2 rounded hover:bg-gray-700"
        >
          Payments
        </router-link>
      </template>

      <!-- Admin section (Owner only) -->
      <template v-if="userStore.isOwner">
        <div class="mt-6 mb-2 px-4 text-xs font-semibold text-gray-400 uppercase">
          Administration
        </div>

        <router-link
          to="/admin/users"
          class="flex items-center px-4 py-3 mb-2 rounded hover:bg-gray-700"
        >
          Users
        </router-link>

        <router-link
          to="/admin/settings"
          class="flex items-center px-4 py-3 mb-2 rounded hover:bg-gray-700"
        >
          Settings
        </router-link>
      </template>

      <!-- Logout (everyone) -->
      <form method="POST" action="/auth/logout">
        <button
          type="submit"
          class="w-full flex items-center px-4 py-3 mt-6 rounded hover:bg-gray-700 text-left"
        >
          Logout
        </button>
      </form>
    </nav>

    <!-- User info footer -->
    <div class="absolute bottom-0 left-0 right-0 p-4 bg-gray-900">
      <p class="text-sm font-semibold">{{ userStore.fullName }}</p>
      <p class="text-xs text-gray-400">{{ userStore.role }}</p>
    </div>
  </aside>
</template>
```

4. **Permission Composable** (reusable permission logic)

```javascript
// resources/js/composables/usePermissions.js
import { useUserStore } from '@/stores/user'

export function usePermissions() {
  const userStore = useUserStore()

  const can = (permission) => {
    const permissions = {
      'view-financials': userStore.canViewFinancials,
      'manage-users': userStore.canManageUsers,
      'manage-agency-settings': userStore.canManageAgencySettings,
      'create-invoices': ['owner', 'agency'].includes(userStore.role),
      'delete-invoices': userStore.isOwner,
      'create-projects': ['owner', 'agency'].includes(userStore.role),
      'delete-projects': userStore.isOwner,
    }

    return permissions[permission] ?? false
  }

  return {
    can
  }
}
```

5. **Usage in Components**

```vue
<!-- resources/js/components/invoices/InvoiceActions.vue -->
<script setup>
import { usePermissions } from '@/composables/usePermissions'

const { can } = usePermissions()
const props = defineProps(['invoice'])

const deleteInvoice = async () => {
  if (!confirm('Are you sure you want to delete this invoice?')) return

  try {
    await axios.delete(`/api/invoices/${props.invoice.id}`)
    // Emit event or reload list
  } catch (error) {
    alert(error.response.data.error)
  }
}
</script>

<template>
  <div class="flex gap-2">
    <button class="btn btn-primary">View</button>
    <button class="btn btn-secondary">Edit</button>

    <!-- Delete button only shown if user can delete (Owner) -->
    <button
      v-if="can('delete-invoices')"
      class="btn btn-danger"
      @click="deleteInvoice"
    >
      Delete
    </button>
  </div>
</template>
```

6. **Security Warning Documentation**

```markdown
<!-- docs/architecture/rbac-frontend.md -->
# RBAC Implementation: Frontend Layer

## ⚠️ CRITICAL SECURITY WARNING

**Frontend permission checks are for USER EXPERIENCE ONLY.**

They do NOT provide security. An attacker with browser DevTools can:
- Unhide permission-restricted UI elements
- Modify Vue.js component state
- Call API endpoints directly (bypassing frontend)

**Real security is enforced by**:
1. HTTP middleware (route-level blocking)
2. Service layer guards (business logic authorization)
3. PostgreSQL RLS (database-level isolation)

**Frontend permissions improve UX by**:
- Hiding irrelevant features for current role
- Preventing accidental unauthorized actions
- Providing clear visual feedback on capabilities

## Implementation

### Pinia User Store
Central store for user data and permission computed properties.

### Permission Composable
Reusable `usePermissions()` composable for checking permissions in components.

### v-if Directives
Hide/show UI elements based on permissions:
```vue
<button v-if="userStore.canViewFinancials">View Invoice</button>
```

## Testing Strategy
- Unit test computed properties in user store
- Visual testing: Verify UI hides correctly for each role
- E2E testing: Verify backend STILL enforces permissions even if frontend bypassed

## Common Mistakes to Avoid
❌ **Don't** rely on frontend checks for security
❌ **Don't** make API calls without backend authorization checks
❌ **Don't** hide elements with CSS (use v-if to remove from DOM)

✅ **Do** use frontend checks for better UX
✅ **Do** trust backend authorization (HTTP + Service + DB)
✅ **Do** test that backend blocks access even if frontend broken
```

**Deliverables**:
- ✅ Pinia user store with permission computed properties
- ✅ Sidebar component with role-based navigation
- ✅ Permission composable for reusable checks
- ✅ Documentation emphasizing frontend ≠ security

**Time**: 1 week (40 hours)

---

### Week 14-15: Frontend Layout & Navigation

**Focus**: Integrate TailAdmin template and create responsive navigation

**Tasks**:

1. **Install TailAdmin Dependencies**

```bash
npm install tailwindcss@3 @tailwindcss/forms autoprefixer postcss
npm install @headlessui/vue @heroicons/vue
```

2. **Configure Tailwind**

```javascript
// tailwind.config.js
/** @type {import('tailwindcss').Config} */
export default {
  content: [
    './resources/**/*.{vue,js,ts,jsx,tsx}',
    './app/Views/**/*.php',
  ],
  theme: {
    extend: {
      colors: {
        primary: {
          50: '#eff6ff',
          100: '#dbeafe',
          200: '#bfdbfe',
          300: '#93c5fd',
          400: '#60a5fa',
          500: '#3b82f6', // Primary brand color
          600: '#2563eb',
          700: '#1d4ed8',
          800: '#1e40af',
          900: '#1e3a8a',
        },
      },
    },
  },
  plugins: [
    require('@tailwindcss/forms'),
  ],
}
```

3. **Create Main Layout Component**

```vue
<!-- resources/js/components/layout/MainLayout.vue -->
<script setup>
import { ref } from 'vue'
import Sidebar from './Sidebar.vue'
import Header from './Header.vue'

const sidebarOpen = ref(false)
</script>

<template>
  <div class="flex h-screen bg-gray-100">
    <!-- Sidebar -->
    <Sidebar />

    <!-- Main content area -->
    <div class="flex-1 flex flex-col overflow-hidden">
      <!-- Header -->
      <Header @toggle-sidebar="sidebarOpen = !sidebarOpen" />

      <!-- Page content -->
      <main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-100 p-6">
        <slot />
      </main>
    </div>
  </div>
</template>
```

4. **Header Component**

```vue
<!-- resources/js/components/layout/Header.vue -->
<script setup>
import { useUserStore } from '@/stores/user'
import { Menu, MenuButton, MenuItem, MenuItems } from '@headlessui/vue'
import { BellIcon, UserCircleIcon } from '@heroicons/vue/24/outline'

const userStore = useUserStore()
const emit = defineEmits(['toggle-sidebar'])
</script>

<template>
  <header class="flex items-center justify-between px-6 py-4 bg-white border-b">
    <!-- Mobile menu button -->
    <button
      class="text-gray-500 focus:outline-none lg:hidden"
      @click="emit('toggle-sidebar')"
    >
      <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
      </svg>
    </button>

    <div class="flex items-center flex-1">
      <!-- Search bar (future feature) -->
      <div class="relative w-full max-w-md ml-4">
        <input
          type="text"
          placeholder="Search..."
          class="w-full py-2 pl-10 pr-4 text-gray-700 bg-gray-100 border-0 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
        >
      </div>
    </div>

    <div class="flex items-center">
      <!-- Notifications (future feature) -->
      <button class="flex mx-4 text-gray-600 focus:outline-none">
        <BellIcon class="w-6 h-6" />
      </button>

      <!-- User menu -->
      <Menu as="div" class="relative">
        <MenuButton class="flex items-center text-gray-600 focus:outline-none">
          <UserCircleIcon class="w-8 h-8" />
        </MenuButton>

        <transition
          enter-active-class="transition ease-out duration-100"
          enter-from-class="transform opacity-0 scale-95"
          enter-to-class="transform opacity-100 scale-100"
          leave-active-class="transition ease-in duration-75"
          leave-from-class="transform opacity-100 scale-100"
          leave-to-class="transform opacity-0 scale-95"
        >
          <MenuItems class="absolute right-0 w-48 mt-2 origin-top-right bg-white rounded-md shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none">
            <div class="px-4 py-3 border-b">
              <p class="text-sm font-medium text-gray-900">{{ userStore.fullName }}</p>
              <p class="text-xs text-gray-500">{{ userStore.email }}</p>
            </div>

            <MenuItem v-slot="{ active }">
              <a
                href="/profile"
                :class="[active ? 'bg-gray-100' : '', 'block px-4 py-2 text-sm text-gray-700']"
              >
                Profile
              </a>
            </MenuItem>

            <MenuItem v-if="userStore.canManageAgencySettings" v-slot="{ active }">
              <a
                href="/settings"
                :class="[active ? 'bg-gray-100' : '', 'block px-4 py-2 text-sm text-gray-700']"
              >
                Settings
              </a>
            </MenuItem>

            <MenuItem v-slot="{ active }">
              <form method="POST" action="/auth/logout">
                <button
                  type="submit"
                  :class="[active ? 'bg-gray-100' : '', 'block w-full text-left px-4 py-2 text-sm text-gray-700']"
                >
                  Logout
                </button>
              </form>
            </MenuItem>
          </MenuItems>
        </transition>
      </Menu>
    </div>
  </header>
</template>
```

5. **Dashboard View (Example Page)**

```php
<!-- app/Views/dashboard/index.php -->
<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>

<div id="dashboard-app">
    <dashboard-component :initial-data='<?= json_encode($dashboardData) ?>'></dashboard-component>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script type="module">
import { createApp } from 'vue'
import { createPinia } from 'pinia'
import DashboardComponent from '/assets/js/components/dashboard/DashboardComponent.vue'
import MainLayout from '/assets/js/components/layout/MainLayout.vue'

const app = createApp({
    components: {
        MainLayout,
        DashboardComponent
    }
})
app.use(createPinia())
app.mount('#dashboard-app')
</script>
<?= $this->endSection() ?>
```

```vue
<!-- resources/js/components/dashboard/DashboardComponent.vue -->
<script setup>
import { ref, onMounted } from 'vue'
import { useUserStore } from '@/stores/user'
import MainLayout from '../layout/MainLayout.vue'
import StatsCard from './StatsCard.vue'

const props = defineProps(['initialData'])
const userStore = useUserStore()

const stats = ref(props.initialData.stats)

onMounted(() => {
  // Can fetch additional data via API if needed
})
</script>

<template>
  <MainLayout>
    <div>
      <h1 class="text-3xl font-bold text-gray-900 mb-6">
        Welcome back, {{ userStore.firstName }}!
      </h1>

      <!-- Stats grid -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
        <StatsCard
          title="Total Clients"
          :value="stats.totalClients"
          icon="users"
          color="blue"
        />
        <StatsCard
          title="Active Projects"
          :value="stats.activeProjects"
          icon="briefcase"
          color="green"
        />
        <StatsCard
          v-if="userStore.canViewFinancials"
          title="Pending Invoices"
          :value="stats.pendingInvoices"
          icon="document"
          color="yellow"
        />
        <StatsCard
          v-if="userStore.canViewFinancials"
          title="Revenue (MTD)"
          :value="`$${stats.revenueMTD}`"
          icon="currency-dollar"
          color="purple"
        />
      </div>

      <!-- Recent activity -->
      <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-xl font-semibold mb-4">Recent Activity</h2>
        <div class="space-y-4">
          <div v-for="activity in stats.recentActivities" :key="activity.id" class="flex items-center">
            <div class="flex-shrink-0">
              <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center">
                <span class="text-blue-600 text-sm font-semibold">{{ activity.icon }}</span>
              </div>
            </div>
            <div class="ml-4 flex-1">
              <p class="text-sm font-medium text-gray-900">{{ activity.title }}</p>
              <p class="text-sm text-gray-500">{{ activity.description }}</p>
            </div>
            <div class="text-sm text-gray-400">{{ activity.timestamp }}</div>
          </div>
        </div>
      </div>
    </div>
  </MainLayout>
</template>
```

6. **Responsive Mobile Navigation**

```vue
<!-- Update Sidebar.vue for mobile -->
<script setup>
import { ref } from 'vue'
import { Dialog, DialogPanel, TransitionChild, TransitionRoot } from '@headlessui/vue'
import { XMarkIcon } from '@heroicons/vue/24/outline'

const props = defineProps(['open'])
const emit = defineEmits(['close'])
</script>

<template>
  <!-- Mobile sidebar overlay -->
  <TransitionRoot as="template" :show="open">
    <Dialog as="div" class="relative z-40 lg:hidden" @close="emit('close')">
      <TransitionChild
        as="template"
        enter="transition-opacity ease-linear duration-300"
        enter-from="opacity-0"
        enter-to="opacity-100"
        leave="transition-opacity ease-linear duration-300"
        leave-from="opacity-100"
        leave-to="opacity-0"
      >
        <div class="fixed inset-0 bg-gray-600 bg-opacity-75" />
      </TransitionChild>

      <div class="fixed inset-0 z-40 flex">
        <TransitionChild
          as="template"
          enter="transition ease-in-out duration-300 transform"
          enter-from="-translate-x-full"
          enter-to="translate-x-0"
          leave="transition ease-in-out duration-300 transform"
          leave-from="translate-x-0"
          leave-to="-translate-x-full"
        >
          <DialogPanel class="relative flex w-full max-w-xs flex-1 flex-col bg-gray-800 pt-5 pb-4">
            <TransitionChild
              as="template"
              enter="ease-in-out duration-300"
              enter-from="opacity-0"
              enter-to="opacity-100"
              leave="ease-in-out duration-300"
              leave-from="opacity-100"
              leave-to="opacity-0"
            >
              <div class="absolute top-0 right-0 -mr-12 pt-2">
                <button
                  type="button"
                  class="ml-1 flex h-10 w-10 items-center justify-center rounded-full focus:outline-none focus:ring-2 focus:ring-inset focus:ring-white"
                  @click="emit('close')"
                >
                  <span class="sr-only">Close sidebar</span>
                  <XMarkIcon class="h-6 w-6 text-white" aria-hidden="true" />
                </button>
              </div>
            </TransitionChild>

            <!-- Mobile navigation (same as desktop) -->
            <nav class="mt-5 flex-1 space-y-1 px-2">
              <!-- ... same navigation items as desktop sidebar ... -->
            </nav>
          </DialogPanel>
        </TransitionChild>
      </div>
    </Dialog>
  </TransitionRoot>

  <!-- Desktop sidebar (always visible on lg+) -->
  <aside class="hidden lg:flex lg:w-64 lg:flex-col lg:fixed lg:inset-y-0 bg-gray-800">
    <!-- Desktop navigation -->
  </aside>
</template>
```

**Deliverables**:
- ✅ TailAdmin integrated with TailwindCSS 3
- ✅ Main layout with sidebar, header, content area
- ✅ Responsive mobile navigation with overlay
- ✅ Dashboard with stats cards and activity feed
- ✅ Role-based UI hiding (financial stats for End Clients)

**Time**: 2 weeks (80 hours)

---

### Week 16: Milestone 1 Quality Gate

**Focus**: Validate foundation completeness before moving to core features

**Tasks**:

1. **Run Full Test Suite**

```bash
# Run all PHPUnit tests
composer test

# Check code coverage
composer test -- --coverage-html tests/coverage

# Run Vue.js tests
npm run test

# Combined coverage check
# Target: ≥ 95% line coverage
```

2. **Static Analysis**

```bash
# Run PHPStan (static analysis for PHP)
composer require --dev phpstan/phpstan
./vendor/bin/phpstan analyze app tests --level 6

# Run ESLint (linting for JavaScript)
npm run lint

# Fix auto-fixable issues
npm run lint:fix
```

3. **Security Scan**

```bash
# Install OWASP ZAP Docker
docker pull owasp/zap2docker-stable

# Run baseline scan against local dev server
docker run -v $(pwd):/zap/wrk/:rw -t owasp/zap2docker-stable zap-baseline.py \
    -t http://host.docker.internal:8080 \
    -r zap-report.html

# Review report for high/medium vulnerabilities
```

4. **Performance Baseline**

```bash
# Install Lighthouse CI
npm install -g @lhci/cli

# Run Lighthouse audit on dashboard
lhci autorun --collect.url=http://localhost:8080/dashboard

# Check scores (target: 80+ for performance, accessibility, best practices, SEO)
```

5. **Manual RBAC Testing Checklist**

```markdown
## RBAC Manual Test Checklist

### Owner Role
- [x] Can login successfully
- [x] Can access /dashboard
- [x] Can access /clients
- [x] Can access /projects
- [x] Can access /invoices (financial feature)
- [x] Can access /admin/settings (admin feature)
- [x] Sidebar shows all menu items
- [x] Can view clients from all agencies (database query check)

### Agency Role (Agency A)
- [x] Can login successfully
- [x] Can access /dashboard
- [x] Can access /clients
- [x] Can access /projects
- [x] Can access /invoices (financial feature)
- [x] Cannot access /admin/settings (redirects to /dashboard with error)
- [x] Sidebar shows financial menu items
- [x] Sidebar hides admin menu items
- [x] Can only see clients for Agency A (not Agency B)

### End Client Role
- [x] Can login successfully
- [x] Can access /dashboard
- [x] Can access /clients
- [x] Can access /projects
- [x] Cannot access /invoices (redirects to /dashboard with error)
- [x] Cannot access /admin/settings (redirects to /dashboard with error)
- [x] Sidebar hides financial menu items
- [x] Sidebar hides admin menu items
- [x] Dashboard does not show financial stats (revenue, pending invoices)

### Direct Client Role
- [x] Can login successfully
- [x] Can access /dashboard
- [x] Can access /clients (only their own client record)
- [x] Can access /projects (only assigned projects)
- [x] Can access /invoices (only their invoices)
- [x] Cannot access /admin/settings
- [x] Sidebar shows financial menu items
```

6. **Documentation Review**

- ✅ `README.md` has setup instructions
- ✅ `docs/architecture/rbac-database.md` complete
- ✅ `docs/architecture/rbac-http.md` complete
- ✅ `docs/architecture/rbac-service.md` complete
- ✅ `docs/architecture/rbac-frontend.md` complete
- ✅ `docs/deployment/bare-metal.md` started (will complete in Month 12)

7. **CI/CD Pipeline Validation**

```yaml
# .github/workflows/tests.yml should pass all checks
- name: Run tests
  run: |
    composer install
    php spark migrate --env=testing
    composer test

- name: Check coverage
  run: |
    composer test -- --coverage-text
    # Fail if coverage < 95%

- name: Static analysis
  run: ./vendor/bin/phpstan analyze

- name: Lint
  run: |
    npm install
    npm run lint
```

8. **Milestone 1 Sign-Off Meeting**

- Demo authentication (login, logout, session timeout)
- Demo RBAC (all 4 roles, show access restrictions)
- Review test coverage report (must be ≥ 95%)
- Review security scan report (zero high-severity issues)
- Review documentation completeness
- Decision: **GO / NO-GO** for Milestone 2

**Deliverables**:
- ✅ All tests passing with 95%+ coverage
- ✅ Static analysis clean (PHPStan level 6)
- ✅ Security scan clean (no high/medium vulnerabilities)
- ✅ Performance baseline established (Lighthouse scores)
- ✅ Manual RBAC testing complete
- ✅ Documentation reviewed and complete
- ✅ Stakeholder approval to proceed to Milestone 2

**Time**: 1 week (40 hours)

---

## Milestone 1 Summary

**Total Duration**: 16 weeks (12 + 4 weeks quality gate)
**Total Effort**: 640 hours

**Key Achievements**:
- ✅ Authentication system production-ready
- ✅ Multi-layer RBAC implemented (HTTP, Service, Database RLS, Frontend)
- ✅ PostgreSQL schema with RLS policies enforcing multi-agency isolation
- ✅ Vue.js 3 + TailAdmin frontend layout
- ✅ 95% test coverage (unit + integration)
- ✅ CI/CD pipeline operational
- ✅ Security baseline established (OWASP scan clean)

**Foundation Solid - Ready for Milestone 2 (Core Features)** ✅

---

## Milestone 2: Core Revenue Features (Months 4-6) - High-Level Overview

**Objective**: Build core revenue-generating features (CRM, Projects, Invoices, Stripe payments)

**Timeline**: 12 weeks (Months 4-6)

**Features**:
1. **CRM** (Clients, Contacts, Notes, Timeline, CSV import/export)
2. **Projects & Tasks** (Project management, task lists, time tracking, file attachments)
3. **Invoices** (Create, PDF generation, send to client, line items, tax, status workflow)
4. **Stripe Integration** (Checkout, payment, webhook confirmation)

**Quality Gates**:
- E2E test: Client receives invoice → pays online → invoice marked paid
- 95% test coverage maintained
- Performance: Page load < 5s, API response < 2s

---

## Milestone 3: Expansion (Months 7-9) - High-Level Overview

**Objective**: Add sales pipeline, proposals, recurring invoices, client portal, additional payment gateways

**Timeline**: 12 weeks (Months 7-9)

**Features**:
1. **Pipelines & Deals** (Kanban board, sales funnel management)
2. **Proposals** (Template-based, client acceptance tracking)
3. **Recurring Invoices** (Custom intervals, auto-generation)
4. **Client Portal** (Separate client login, view projects/invoices, pay online)
5. **Payment Gateways** (PayPal, Zelle, Stripe ACH)

**Quality Gates**:
- Recurring invoices tested across multiple intervals (daily, weekly, monthly, custom)
- Client portal E2E test: Login → view invoice → pay via PayPal → confirmed
- Webhook monitoring dashboard operational

---

## Milestone 4: Polish & Production Launch (Months 10-12) - High-Level Overview

**Objective**: Complete remaining features, comprehensive testing, security audit, production deployment

**Timeline**: 12 weeks (Months 10-12)

**Features**:
1. **Forms & Onboarding** (Form builder, public links, response handling)
2. **Documents** (File management, per-client/project folders, search)
3. **Tickets & Support** (Support system, internal notes vs public replies)
4. **Discussions** (Threaded conversations, @mentions, notifications)
5. **Meetings & Calendar** (ICS feed, meeting notes)

**Production Readiness**:
- E2E test suite (20 critical flows)
- Security audit (OWASP Top 10 validated)
- Accessibility audit (WCAG 2.1 Level AA)
- Load testing (20 concurrent users)
- Backup automation operational
- Monitoring configured (Netdata/Prometheus)
- Deployment guide complete and tested

**Launch Criteria**:
- 10 test agencies using system for 30 days
- Zero critical bugs
- Stakeholder acceptance sign-off

---

## Risk Management

### Critical Risks

| Risk | Probability | Impact | Mitigation |
|------|-------------|--------|------------|
| RLS policies not filtering correctly | Low | Critical | Extensive integration testing, manual SQL verification |
| Stripe webhook security vulnerability | Medium | Critical | Follow Stripe security best practices, penetration testing |
| Performance degradation with 20 users | Medium | High | Load testing early, query optimization, caching strategy |
| Test coverage drops below 95% | Medium | High | CI/CD blocks merge if coverage < 95%, enforce in code reviews |
| Agency data leakage (RLS bypass) | Low | Critical | Security audit, bug bounty program, regular penetration testing |

### Mitigation Strategies

1. **Weekly Code Reviews**: Peer review all RBAC-related code
2. **Automated Testing**: CI/CD blocks deployment if tests fail
3. **Security Scans**: Monthly OWASP ZAP scans + annual penetration testing
4. **Performance Monitoring**: Netdata/Prometheus alerts for degradation
5. **Backup Validation**: Monthly restore tests to verify backup integrity

---

## Success Metrics

### Technical Metrics
- **Test Coverage**: ≥ 95% line coverage (PHPUnit + Vitest)
- **Code Quality**: PHPStan level 6, ESLint clean
- **Security**: Zero high-severity vulnerabilities (OWASP scan)
- **Performance**: Page load < 5s (p95), API response < 2s (p95)
- **Uptime**: 99% uptime target (monitored via Netdata)

### Business Metrics
- **User Acceptance**: 10 test agencies validate system (30-day trial)
- **Feature Completeness**: All 11 features operational
- **Accessibility**: WCAG 2.1 Level AA compliance (Axe DevTools clean)
- **Documentation**: Deployment guide, API docs, user guide complete

### Quality Gates
- **Milestone 1**: Foundation + RBAC complete (Month 3)
- **Milestone 2**: Core revenue features complete (Month 6)
- **Milestone 3**: Expansion features complete (Month 9)
- **Milestone 4**: Production launch ready (Month 12)

---

## Next Steps

1. **Review & Approve Workflow**: Stakeholder review of implementation plan
2. **Begin Milestone 1**: Start Week 1 (Environment & Repository Setup)
3. **Weekly Check-ins**: Review progress, adjust timeline if needed
4. **Monthly Milestone Reviews**: Validate quality gates, sign-off to proceed

---

**Generated by**: /sc:workflow command
**Version**: 1.0
**Last Updated**: 2025-12-08
