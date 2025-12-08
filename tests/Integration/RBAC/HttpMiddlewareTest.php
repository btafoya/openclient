<?php

namespace Tests\Integration\RBAC;

use PHPUnit\Framework\TestCase;
use PDO;

/**
 * HTTP Middleware RBAC Integration Tests
 *
 * Tests RBAC Layer 2 (HTTP middleware) authorization enforcement.
 * Verifies that the RBACFilter correctly blocks unauthorized access
 * and logs security violations to audit trail.
 *
 * Test Coverage:
 * - End Clients blocked from financial routes
 * - Agency users blocked from admin routes
 * - Direct Clients blocked from admin routes
 * - Owner can access all routes
 * - Agency users must have agency_id assigned
 * - Security audit logging for all violations
 */
class HttpMiddlewareTest extends TestCase
{

    private static PDO $pdo;
    private static string $agencyId;
    private static string $ownerUserId;
    private static string $agencyUserId;
    private static string $directClientUserId;
    private static string $endClientUserId;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        // Create PDO connection for direct database operations
        $dbConfig = config('Database');
        $host = $dbConfig->default['hostname'];
        $dbname = $dbConfig->default['database'];
        $username = $dbConfig->default['username'];
        $password = $dbConfig->default['password'];

        self::$pdo = new PDO(
            "pgsql:host={$host};dbname={$dbname}",
            $username,
            $password,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]
        );

        // Set owner role to bypass RLS during test setup
        self::$pdo->exec("SET app.current_user_role = 'owner'");

        // Create test agency
        self::$pdo->exec("DELETE FROM users WHERE email LIKE 'rbactest%'");
        self::$pdo->exec("DELETE FROM agencies WHERE name = 'RBAC Test Agency'");

        self::$pdo->exec("
            INSERT INTO agencies (id, name)
            VALUES ('99999999-9999-9999-9999-999999999999', 'RBAC Test Agency')
            ON CONFLICT (id) DO UPDATE SET name = EXCLUDED.name
        ");
        self::$agencyId = '99999999-9999-9999-9999-999999999999';

        // Create test users with different roles
        self::createTestUser('owner');
        self::createTestUser('agency');
        self::createTestUser('direct_client');
        self::createTestUser('end_client');
    }

    public static function tearDownAfterClass(): void
    {
        // Set owner role for cleanup
        self::$pdo->exec("SET app.current_user_role = 'owner'");

        // Clean up test data
        self::$pdo->exec("DELETE FROM users WHERE email LIKE 'rbactest%'");
        self::$pdo->exec("DELETE FROM agencies WHERE id = '99999999-9999-9999-9999-999999999999'");
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Clear session before each test
        session()->destroy();

        // Delete previous security logs
        $logFile = WRITEPATH . 'logs/security-' . date('Y-m-d') . '.log';
        if (file_exists($logFile)) {
            unlink($logFile);
        }
    }

    /**
     * Create test user with specified role
     */
    private static function createTestUser(string $role): void
    {
        $userId = match($role) {
            'owner' => 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
            'agency' => 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb',
            'direct_client' => 'cccccccc-cccc-cccc-cccc-cccccccccccc',
            'end_client' => 'dddddddd-dddd-dddd-dddd-dddddddddddd',
        };

        $agencyIdValue = in_array($role, ['agency', 'direct_client']) ? self::$agencyId : null;

        $stmt = self::$pdo->prepare("
            INSERT INTO users (id, name, email, password_hash, role, agency_id, is_active)
            VALUES (?, ?, ?, ?, ?, ?, true)
            ON CONFLICT (id) DO UPDATE SET
                name = EXCLUDED.name,
                email = EXCLUDED.email,
                role = EXCLUDED.role,
                agency_id = EXCLUDED.agency_id
        ");

        $passwordHash = password_hash('TestPassword123', PASSWORD_BCRYPT);
        $stmt->execute([
            $userId,
            "Test {$role} User",
            "rbactest.{$role}@example.com",
            $passwordHash,
            $role,
            $agencyIdValue
        ]);

        // Store user IDs for tests
        match($role) {
            'owner' => self::$ownerUserId = $userId,
            'agency' => self::$agencyUserId = $userId,
            'direct_client' => self::$directClientUserId = $userId,
            'end_client' => self::$endClientUserId = $userId,
        };
    }

    /**
     * Simulate user login by setting session variables
     */
    private function simulateLogin(string $userId, string $role, ?string $agencyId = null): void
    {
        $user = [
            'id' => $userId,
            'email' => "rbactest.{$role}@example.com",
            'name' => "Test {$role} User",
            'role' => $role,
            'agency_id' => $agencyId,
        ];

        session()->set('logged_in', true);
        session()->set('user', $user);
    }

    /**
     * Test: End Client blocked from accessing invoices
     */
    public function testEndClientBlockedFromInvoices(): void
    {
        $this->simulateLogin(self::$endClientUserId, 'end_client');

        // Create mock request to /invoices
        $request = service('request');
        $request->setMethod('GET');

        $uri = service('uri');
        $uri->setPath('/invoices');
        $request->uri = $uri;

        // Run RBAC filter
        $filter = new \App\Filters\RBACFilter();
        $result = $filter->before($request);

        // Assert: Should redirect to dashboard
        $this->assertInstanceOf('CodeIgniter\HTTP\RedirectResponse', $result);
        $this->assertEquals('/dashboard', $result->getHeaderLine('Location'));

        // Assert: Security log contains denial
        $logFile = WRITEPATH . 'logs/security-' . date('Y-m-d') . '.log';
        $this->assertFileExists($logFile);

        $logContent = file_get_contents($logFile);
        $this->assertStringContainsString('ACCESS_DENIED', $logContent);
        $this->assertStringContainsString('/invoices', $logContent);
        $this->assertStringContainsString('end_client', $logContent);
    }

    /**
     * Test: End Client blocked from accessing payments
     */
    public function testEndClientBlockedFromPayments(): void
    {
        $this->simulateLogin(self::$endClientUserId, 'end_client');

        $request = service('request');
        $request->setMethod('GET');

        $uri = service('uri');
        $uri->setPath('/payments');
        $request->uri = $uri;

        $filter = new \App\Filters\RBACFilter();
        $result = $filter->before($request);

        $this->assertInstanceOf('CodeIgniter\HTTP\RedirectResponse', $result);
        $this->assertStringContainsString('permission', session()->getFlashdata('error'));
    }

    /**
     * Test: Agency user blocked from admin routes
     */
    public function testAgencyBlockedFromAdmin(): void
    {
        $this->simulateLogin(self::$agencyUserId, 'agency', self::$agencyId);

        $request = service('request');
        $request->setMethod('GET');

        $uri = service('uri');
        $uri->setPath('/admin/settings');
        $request->uri = $uri;

        $filter = new \App\Filters\RBACFilter();
        $result = $filter->before($request);

        $this->assertInstanceOf('CodeIgniter\HTTP\RedirectResponse', $result);
        $this->assertEquals('/dashboard', $result->getHeaderLine('Location'));

        // Check security log
        $logFile = WRITEPATH . 'logs/security-' . date('Y-m-d') . '.log';
        $logContent = file_get_contents($logFile);
        $this->assertStringContainsString('admin route', $logContent);
    }

    /**
     * Test: Direct Client blocked from admin routes
     */
    public function testDirectClientBlockedFromAdmin(): void
    {
        $this->simulateLogin(self::$directClientUserId, 'direct_client', self::$agencyId);

        $request = service('request');
        $request->setMethod('GET');

        $uri = service('uri');
        $uri->setPath('/admin/users');
        $request->uri = $uri;

        $filter = new \App\Filters\RBACFilter();
        $result = $filter->before($request);

        $this->assertInstanceOf('CodeIgniter\HTTP\RedirectResponse', $result);
    }

    /**
     * Test: Owner can access all routes
     */
    public function testOwnerCanAccessAllRoutes(): void
    {
        $this->simulateLogin(self::$ownerUserId, 'owner');

        $filter = new \App\Filters\RBACFilter();

        // Owner can access invoices
        $request = service('request');
        $uri = service('uri');
        $uri->setPath('/invoices');
        $request->uri = $uri;
        $result = $filter->before($request);
        $this->assertSame($request, $result);

        // Owner can access admin
        $uri = service('uri');
        $uri->setPath('/admin/settings');
        $request->uri = $uri;
        $result = $filter->before($request);
        $this->assertSame($request, $result);

        // Owner can access users
        $uri = service('uri');
        $uri->setPath('/users');
        $request->uri = $uri;
        $result = $filter->before($request);
        $this->assertSame($request, $result);
    }

    /**
     * Test: Agency user without agency_id is blocked
     */
    public function testAgencyUserWithoutAgencyIdBlocked(): void
    {
        // Create agency user without agency_id
        $userWithoutAgency = [
            'id' => 'eeeeeeee-eeee-eeee-eeee-eeeeeeeeeeee',
            'email' => 'noagency@example.com',
            'name' => 'No Agency User',
            'role' => 'agency',
            'agency_id' => null,
        ];

        session()->set('logged_in', true);
        session()->set('user', $userWithoutAgency);

        $request = service('request');
        $uri = service('uri');
        $uri->setPath('/invoices');
        $request->uri = $uri;

        $filter = new \App\Filters\RBACFilter();
        $result = $filter->before($request);

        $this->assertInstanceOf('CodeIgniter\HTTP\RedirectResponse', $result);
        $this->assertStringContainsString('agency', session()->getFlashdata('error'));
    }

    /**
     * Test: Direct Client without agency_id is blocked
     */
    public function testDirectClientWithoutAgencyIdBlocked(): void
    {
        $userWithoutAgency = [
            'id' => 'ffffffff-ffff-ffff-ffff-ffffffffffff',
            'email' => 'nodcagency@example.com',
            'name' => 'No Agency Direct Client',
            'role' => 'direct_client',
            'agency_id' => null,
        ];

        session()->set('logged_in', true);
        session()->set('user', $userWithoutAgency);

        $request = service('request');
        $uri = service('uri');
        $uri->setPath('/clients');
        $request->uri = $uri;

        $filter = new \App\Filters\RBACFilter();
        $result = $filter->before($request);

        $this->assertInstanceOf('CodeIgniter\HTTP\RedirectResponse', $result);
        $this->assertStringContainsString('agency', session()->getFlashdata('error'));
    }

    /**
     * Test: Security logger records all required fields
     */
    public function testSecurityLoggerRecordsCompleteData(): void
    {
        $this->simulateLogin(self::$endClientUserId, 'end_client');

        $request = service('request');
        $uri = service('uri');
        $uri->setPath('/billing');
        $request->uri = $uri;

        $filter = new \App\Filters\RBACFilter();
        $filter->before($request);

        $logFile = WRITEPATH . 'logs/security-' . date('Y-m-d') . '.log';
        $this->assertFileExists($logFile);

        $logContent = file_get_contents($logFile);
        $logData = json_decode($logContent, true);

        // Verify all required fields present
        $this->assertArrayHasKey('timestamp', $logData);
        $this->assertArrayHasKey('event', $logData);
        $this->assertArrayHasKey('user_id', $logData);
        $this->assertArrayHasKey('user_email', $logData);
        $this->assertArrayHasKey('user_role', $logData);
        $this->assertArrayHasKey('attempted_resource', $logData);
        $this->assertArrayHasKey('reason', $logData);
        $this->assertArrayHasKey('ip_address', $logData);
        $this->assertArrayHasKey('user_agent', $logData);

        // Verify correct values
        $this->assertEquals('ACCESS_DENIED', $logData['event']);
        $this->assertEquals('end_client', $logData['user_role']);
        $this->assertEquals('/billing', $logData['attempted_resource']);
    }

    /**
     * Test: Multiple access denials are logged separately
     */
    public function testMultipleAccessDenialsLogged(): void
    {
        $this->simulateLogin(self::$endClientUserId, 'end_client');

        $filter = new \App\Filters\RBACFilter();

        // Attempt multiple financial routes
        $routes = ['/invoices', '/payments', '/billing'];

        foreach ($routes as $route) {
            $request = service('request');
            $uri = service('uri');
            $uri->setPath($route);
            $request->uri = $uri;
            $filter->before($request);
        }

        $logFile = WRITEPATH . 'logs/security-' . date('Y-m-d') . '.log';
        $logContent = file_get_contents($logFile);
        $logLines = explode(PHP_EOL, trim($logContent));

        // Should have 3 separate log entries
        $this->assertCount(3, $logLines);

        // Each route should be logged
        foreach ($routes as $route) {
            $this->assertStringContainsString($route, $logContent);
        }
    }
}
