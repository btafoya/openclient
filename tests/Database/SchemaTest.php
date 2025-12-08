<?php

namespace Tests\Database;

use PHPUnit\Framework\TestCase;
use PDO;

/**
 * Database Schema Tests
 *
 * Validates database schema using direct PDO connection.
 * Tests table structure, constraints, RLS policies, and indexes.
 */
class SchemaTest extends TestCase
{
    private static ?PDO $pdo = null;

    public static function setUpBeforeClass(): void
    {
        $host = getenv('database.tests.hostname') ?: 'localhost';
        $dbname = getenv('database.tests.database') ?: 'openclient_test';
        $username = getenv('database.tests.username') ?: 'openclient_user';
        $password = getenv('database.tests.password') ?: 'dev_password_change_in_production';

        $dsn = "pgsql:host={$host};dbname={$dbname}";
        self::$pdo = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
    }

    protected function tearDown(): void
    {
        // Clean up test data
        self::$pdo->exec('DELETE FROM users');
        self::$pdo->exec('DELETE FROM agencies');
        self::$pdo->exec('DELETE FROM webhook_events');
        self::$pdo->exec('DELETE FROM ci_sessions');
    }

    /**
     * Test that all required tables exist
     */
    public function testRequiredTablesExist(): void
    {
        $stmt = self::$pdo->query("
            SELECT table_name
            FROM information_schema.tables
            WHERE table_schema = 'public'
            AND table_type = 'BASE TABLE'
        ");

        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $this->assertContains('agencies', $tables);
        $this->assertContains('users', $tables);
        $this->assertContains('ci_sessions', $tables);
        $this->assertContains('webhook_events', $tables);
    }

    /**
     * Test agencies table structure
     */
    public function testAgenciesTableStructure(): void
    {
        $stmt = self::$pdo->query("
            SELECT column_name, data_type
            FROM information_schema.columns
            WHERE table_name = 'agencies'
            ORDER BY ordinal_position
        ");

        $columns = $stmt->fetchAll();
        $columnNames = array_column($columns, 'column_name');

        $this->assertContains('id', $columnNames);
        $this->assertContains('name', $columnNames);
        $this->assertContains('created_at', $columnNames);
        $this->assertContains('updated_at', $columnNames);
        $this->assertContains('deleted_at', $columnNames);

        // Verify id is UUID
        $idCol = array_values(array_filter($columns, fn($c) => $c['column_name'] === 'id'))[0];
        $this->assertEquals('uuid', $idCol['data_type']);
    }

    /**
     * Test users table structure with RBAC fields
     */
    public function testUsersTableStructure(): void
    {
        $stmt = self::$pdo->query("
            SELECT column_name, data_type
            FROM information_schema.columns
            WHERE table_name = 'users'
            ORDER BY ordinal_position
        ");

        $columns = $stmt->fetchAll();
        $columnNames = array_column($columns, 'column_name');

        // Verify RBAC fields
        $this->assertContains('id', $columnNames);
        $this->assertContains('agency_id', $columnNames);
        $this->assertContains('email', $columnNames);
        $this->assertContains('password_hash', $columnNames);
        $this->assertContains('role', $columnNames);
        $this->assertContains('first_name', $columnNames);
        $this->assertContains('last_name', $columnNames);
        $this->assertContains('is_active', $columnNames);
        $this->assertContains('failed_login_attempts', $columnNames);
        $this->assertContains('locked_until', $columnNames);
        $this->assertContains('last_login_at', $columnNames);
        $this->assertContains('last_login_ip', $columnNames);
    }

    /**
     * Test ENUM types exist
     */
    public function testEnumTypesExist(): void
    {
        $stmt = self::$pdo->query("
            SELECT typname FROM pg_type
            WHERE typname IN ('user_role', 'payment_gateway')
        ");

        $types = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $this->assertContains('user_role', $types);
        $this->assertContains('payment_gateway', $types);
    }

    /**
     * Test user_role ENUM values
     */
    public function testUserRoleEnumValues(): void
    {
        $stmt = self::$pdo->query("
            SELECT enumlabel FROM pg_enum
            WHERE enumtypid = (SELECT oid FROM pg_type WHERE typname = 'user_role')
            ORDER BY enumsortorder
        ");

        $roles = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $this->assertContains('owner', $roles);
        $this->assertContains('agency', $roles);
        $this->assertContains('end_client', $roles);
        $this->assertContains('direct_client', $roles);
    }

    /**
     * Test payment_gateway ENUM values
     */
    public function testPaymentGatewayEnumValues(): void
    {
        $stmt = self::$pdo->query("
            SELECT enumlabel FROM pg_enum
            WHERE enumtypid = (SELECT oid FROM pg_type WHERE typname = 'payment_gateway')
            ORDER BY enumsortorder
        ");

        $gateways = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $this->assertContains('stripe', $gateways);
        $this->assertContains('paypal', $gateways);
        $this->assertContains('stripe_ach', $gateways);
        $this->assertContains('zelle', $gateways);
    }

    /**
     * Test foreign key relationship
     */
    public function testUserAgencyForeignKey(): void
    {
        $stmt = self::$pdo->query("
            SELECT
                kcu.column_name,
                ccu.table_name AS foreign_table_name
            FROM information_schema.table_constraints AS tc
            JOIN information_schema.key_column_usage AS kcu
                ON tc.constraint_name = kcu.constraint_name
            JOIN information_schema.constraint_column_usage AS ccu
                ON ccu.constraint_name = tc.constraint_name
            WHERE tc.constraint_type = 'FOREIGN KEY'
                AND tc.table_name = 'users'
                AND kcu.column_name = 'agency_id'
        ");

        $fk = $stmt->fetch();

        $this->assertNotNull($fk);
        $this->assertEquals('agencies', $fk['foreign_table_name']);
    }

    /**
     * Test owner role constraint - owner must have null agency_id
     */
    public function testOwnerRoleConstraint(): void
    {
        self::$pdo->exec("
            INSERT INTO users (email, password_hash, role, first_name, last_name, agency_id)
            VALUES ('owner@test.com', 'hash', 'owner', 'Owner', 'User', NULL)
        ");

        $stmt = self::$pdo->query("SELECT role, agency_id FROM users WHERE email = 'owner@test.com'");
        $user = $stmt->fetch();

        $this->assertEquals('owner', $user['role']);
        $this->assertNull($user['agency_id']);
    }

    /**
     * Test owner role constraint violation
     */
    public function testOwnerRoleConstraintViolation(): void
    {
        self::$pdo->exec("
            INSERT INTO agencies (id, name)
            VALUES ('11111111-1111-1111-1111-111111111111', 'Test Agency')
        ");

        $this->expectException(\PDOException::class);
        $this->expectExceptionMessage('check_owner_no_agency');

        self::$pdo->exec("
            INSERT INTO users (email, password_hash, role, first_name, last_name, agency_id)
            VALUES ('invalid@test.com', 'hash', 'owner', 'Invalid', 'User', '11111111-1111-1111-1111-111111111111')
        ");
    }

    /**
     * Test non-owner roles require agency_id
     */
    public function testNonOwnerRoleRequiresAgency(): void
    {
        self::$pdo->exec("
            INSERT INTO agencies (id, name)
            VALUES ('22222222-2222-2222-2222-222222222222', 'Test Agency')
        ");

        self::$pdo->exec("
            INSERT INTO users (email, password_hash, role, first_name, last_name, agency_id)
            VALUES ('agency@test.com', 'hash', 'agency', 'Agency', 'User', '22222222-2222-2222-2222-222222222222')
        ");

        $stmt = self::$pdo->query("SELECT role, agency_id FROM users WHERE email = 'agency@test.com'");
        $user = $stmt->fetch();

        $this->assertEquals('agency', $user['role']);
        $this->assertEquals('22222222-2222-2222-2222-222222222222', $user['agency_id']);
    }

    /**
     * Test updated_at trigger on agencies
     */
    public function testAgenciesUpdatedAtTrigger(): void
    {
        self::$pdo->exec("
            INSERT INTO agencies (id, name)
            VALUES ('33333333-3333-3333-3333-333333333333', 'Test Agency')
        ");

        $stmt = self::$pdo->query("
            SELECT created_at, updated_at
            FROM agencies WHERE id = '33333333-3333-3333-3333-333333333333'
        ");
        $before = $stmt->fetch();

        sleep(1);

        self::$pdo->exec("
            UPDATE agencies
            SET name = 'Updated Agency'
            WHERE id = '33333333-3333-3333-3333-333333333333'
        ");

        $stmt = self::$pdo->query("
            SELECT created_at, updated_at
            FROM agencies WHERE id = '33333333-3333-3333-3333-333333333333'
        ");
        $after = $stmt->fetch();

        $this->assertEquals($before['created_at'], $after['created_at']);
        $this->assertGreaterThan($before['updated_at'], $after['updated_at']);
    }

    /**
     * Test Row-Level Security is enabled
     */
    public function testRLSEnabled(): void
    {
        $stmt = self::$pdo->query("
            SELECT tablename, relrowsecurity
            FROM pg_class
            JOIN pg_tables ON pg_class.relname = pg_tables.tablename
            WHERE tablename IN ('users', 'agencies', 'webhook_events')
            AND schemaname = 'public'
        ");

        $tables = $stmt->fetchAll();

        foreach ($tables as $table) {
            $this->assertTrue((bool)$table['relrowsecurity'], "RLS should be enabled on {$table['tablename']}");
        }
    }

    /**
     * Test RLS policies exist
     */
    public function testRLSPoliciesExist(): void
    {
        $stmt = self::$pdo->query("
            SELECT policyname, tablename
            FROM pg_policies
            WHERE tablename IN ('users', 'agencies', 'webhook_events')
        ");

        $policies = $stmt->fetchAll();

        $this->assertGreaterThan(0, count($policies), 'At least one RLS policy should exist');

        $policyNames = array_column($policies, 'policyname');
        $this->assertContains('agency_isolation_users', $policyNames);
    }

    /**
     * Test indexes exist
     */
    public function testIndexesExist(): void
    {
        $stmt = self::$pdo->query("
            SELECT indexname, tablename
            FROM pg_indexes
            WHERE tablename IN ('users', 'agencies', 'webhook_events')
            AND schemaname = 'public'
        ");

        $indexes = $stmt->fetchAll();

        $this->assertGreaterThan(10, count($indexes), 'Multiple indexes should exist');

        $indexNames = array_column($indexes, 'indexname');
        $this->assertContains('idx_users_agency_role', $indexNames);
        $this->assertContains('idx_agencies_name', $indexNames);
        $this->assertContains('idx_webhook_events_payload', $indexNames);
    }

    /**
     * Test UUID generation
     */
    public function testUuidGeneration(): void
    {
        self::$pdo->exec("
            INSERT INTO agencies (name)
            VALUES ('UUID Test Agency')
        ");

        $stmt = self::$pdo->query("SELECT id FROM agencies WHERE name = 'UUID Test Agency'");
        $agency = $stmt->fetch();

        $this->assertNotNull($agency);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $agency['id']
        );
    }

    /**
     * Test JSONB payload storage
     */
    public function testWebhookJsonbPayload(): void
    {
        $payload = ['event' => 'payment.succeeded', 'amount' => 5000];

        $stmt = self::$pdo->prepare("
            INSERT INTO webhook_events (event_id, gateway, event_type, payload)
            VALUES ('evt_test_123', 'stripe', 'payment.succeeded', :payload)
        ");
        $stmt->execute(['payload' => json_encode($payload)]);

        $stmt = self::$pdo->query("
            SELECT payload FROM webhook_events WHERE event_id = 'evt_test_123'
        ");
        $row = $stmt->fetch();

        $storedPayload = json_decode($row['payload'], true);
        $this->assertEquals($payload, $storedPayload);
    }

    /**
     * Test email unique constraint
     */
    public function testUsersEmailUniqueConstraint(): void
    {
        self::$pdo->exec("
            INSERT INTO users (email, password_hash, role, first_name, last_name, agency_id)
            VALUES ('unique@test.com', 'hash', 'owner', 'First', 'User', NULL)
        ");

        $this->expectException(\PDOException::class);
        $this->expectExceptionMessage('users_email_key');

        self::$pdo->exec("
            INSERT INTO users (email, password_hash, role, first_name, last_name, agency_id)
            VALUES ('unique@test.com', 'hash', 'owner', 'Second', 'User', NULL)
        ");
    }
}
