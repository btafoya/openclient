<?php

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use PDO;

/**
 * Row-Level Security Integration Tests
 *
 * Tests that PostgreSQL RLS policies correctly enforce multi-agency data isolation.
 * Verifies that:
 * - Agency users can only see/modify their own agency's data
 * - Owner users can see/modify all agencies' data
 * - SQL injection cannot bypass RLS policies
 */
class RowLevelSecurityTest extends TestCase
{
    private static ?PDO $pdo = null;
    private static $agencyA;
    private static $agencyB;
    private static $clientAlpha;
    private static $clientBeta;

    public static function setUpBeforeClass(): void
    {
        $host = getenv('database.default.hostname') ?: 'localhost';
        $dbname = getenv('database.default.database') ?: 'openclient_db';
        $username = getenv('database.default.username') ?: 'openclient_user';
        $password = getenv('database.default.password') ?: 'dev_password_change_in_production';

        $dsn = "pgsql:host={$host};dbname={$dbname}";
        self::$pdo = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);

        // Set owner role to bypass RLS during test setup
        self::$pdo->exec("SET app.current_user_role = 'owner'");

        // Create test agencies
        self::$pdo->exec("DELETE FROM clients WHERE name LIKE 'Test Client%'");
        self::$pdo->exec("DELETE FROM agencies WHERE name LIKE 'Test Agency%'");

        self::$pdo->exec("
            INSERT INTO agencies (id, name)
            VALUES ('11111111-1111-1111-1111-111111111111', 'Test Agency A')
        ");
        self::$agencyA = '11111111-1111-1111-1111-111111111111';

        self::$pdo->exec("
            INSERT INTO agencies (id, name)
            VALUES ('22222222-2222-2222-2222-222222222222', 'Test Agency B')
        ");
        self::$agencyB = '22222222-2222-2222-2222-222222222222';

        // Create test clients
        self::$pdo->exec("
            INSERT INTO clients (id, agency_id, name, email, company)
            VALUES (
                '33333333-3333-3333-3333-333333333333',
                '11111111-1111-1111-1111-111111111111',
                'Test Client Alpha',
                'alpha@agencyA.com',
                'Alpha Company'
            )
        ");
        self::$clientAlpha = '33333333-3333-3333-3333-333333333333';

        self::$pdo->exec("
            INSERT INTO clients (id, agency_id, name, email, company)
            VALUES (
                '44444444-4444-4444-4444-444444444444',
                '22222222-2222-2222-2222-222222222222',
                'Test Client Beta',
                'beta@agencyB.com',
                'Beta Company'
            )
        ");
        self::$clientBeta = '44444444-4444-4444-4444-444444444444';
    }

    public static function tearDownAfterClass(): void
    {
        // Set owner role to bypass RLS during cleanup
        self::$pdo->exec("SET app.current_user_role = 'owner'");

        // Clean up test data
        self::$pdo->exec("DELETE FROM clients WHERE id IN ('33333333-3333-3333-3333-333333333333', '44444444-4444-4444-4444-444444444444')");
        self::$pdo->exec("DELETE FROM agencies WHERE id IN ('11111111-1111-1111-1111-111111111111', '22222222-2222-2222-2222-222222222222')");
    }

    protected function tearDown(): void
    {
        // Reset session variables after each test
        // Set to owner role to allow cleanup operations
        self::$pdo->exec("SET app.current_user_role = 'owner'");
    }

    public function testAgencyACanOnlySeeTheirOwnClients(): void
    {
        // Simulate Agency A user logged in
        self::$pdo->exec("SET app.current_user_role = 'agency'");
        self::$pdo->exec("SET app.current_agency_id = '" . self::$agencyA . "'");

        // Query all clients
        $stmt = self::$pdo->query("SELECT * FROM clients WHERE name LIKE 'Test Client%' ORDER BY name");
        $clients = $stmt->fetchAll();

        // Assert only Agency A's client is returned
        $this->assertCount(1, $clients, "Agency A should only see 1 client");
        $this->assertEquals('Test Client Alpha', $clients[0]['name']);
        $this->assertEquals(self::$agencyA, $clients[0]['agency_id']);
    }

    public function testAgencyBCanOnlySeeTheirOwnClients(): void
    {
        // Simulate Agency B user logged in
        self::$pdo->exec("SET app.current_user_role = 'agency'");
        self::$pdo->exec("SET app.current_agency_id = '" . self::$agencyB . "'");

        // Query all clients
        $stmt = self::$pdo->query("SELECT * FROM clients WHERE name LIKE 'Test Client%' ORDER BY name");
        $clients = $stmt->fetchAll();

        // Assert only Agency B's client is returned
        $this->assertCount(1, $clients, "Agency B should only see 1 client");
        $this->assertEquals('Test Client Beta', $clients[0]['name']);
        $this->assertEquals(self::$agencyB, $clients[0]['agency_id']);
    }

    public function testOwnerCanSeeAllAgenciesClients(): void
    {
        // Simulate Owner user logged in
        self::$pdo->exec("SET app.current_user_role = 'owner'");

        // Query all clients
        $stmt = self::$pdo->query("SELECT * FROM clients WHERE name LIKE 'Test Client%' ORDER BY name");
        $clients = $stmt->fetchAll();

        // Assert both agencies' clients are returned
        $this->assertCount(2, $clients, "Owner should see all 2 clients");
        $this->assertEquals('Test Client Alpha', $clients[0]['name']);
        $this->assertEquals('Test Client Beta', $clients[1]['name']);
    }

    public function testSQLInjectionCannotBypassRLS(): void
    {
        // Simulate Agency A user trying to access Agency B's data
        self::$pdo->exec("SET app.current_user_role = 'agency'");
        self::$pdo->exec("SET app.current_agency_id = '" . self::$agencyA . "'");

        // Attempt to bypass RLS with WHERE clause targeting Agency B
        $maliciousQuery = "SELECT * FROM clients WHERE agency_id = '" . self::$agencyB . "'";
        $stmt = self::$pdo->query($maliciousQuery);
        $clients = $stmt->fetchAll();

        // Assert: RLS policy STILL filters results, attacker gets 0 rows
        $this->assertCount(0, $clients, "SQL injection should not bypass RLS");
    }

    public function testAgencyCannotInsertClientForDifferentAgency(): void
    {
        // Simulate Agency A user logged in
        self::$pdo->exec("SET app.current_user_role = 'agency'");
        self::$pdo->exec("SET app.current_agency_id = '" . self::$agencyA . "'");

        // Attempt to insert a client for Agency B
        try {
            self::$pdo->exec("
                INSERT INTO clients (agency_id, name, email)
                VALUES ('" . self::$agencyB . "', 'Malicious Client', 'malicious@test.com')
            ");
            $this->fail("Should not be able to insert client for different agency");
        } catch (\PDOException $e) {
            // Expected: RLS policy blocks the insert
            $this->assertStringContainsString('new row violates row-level security policy', $e->getMessage());
        }
    }

    public function testAgencyCanInsertClientForTheirOwnAgency(): void
    {
        // Simulate Agency A user logged in
        self::$pdo->exec("SET app.current_user_role = 'agency'");
        self::$pdo->exec("SET app.current_agency_id = '" . self::$agencyA . "'");

        // Insert a client for Agency A
        self::$pdo->exec("
            INSERT INTO clients (id, agency_id, name, email)
            VALUES (
                '55555555-5555-5555-5555-555555555555',
                '" . self::$agencyA . "',
                'Test Client Gamma',
                'gamma@agencyA.com'
            )
        ");

        // Verify the client was inserted and is visible
        $stmt = self::$pdo->query("SELECT * FROM clients WHERE id = '55555555-5555-5555-5555-555555555555'");
        $client = $stmt->fetch();

        $this->assertNotNull($client);
        $this->assertEquals('Test Client Gamma', $client['name']);
        $this->assertEquals(self::$agencyA, $client['agency_id']);

        // Clean up
        self::$pdo->exec("DELETE FROM clients WHERE id = '55555555-5555-5555-5555-555555555555'");
    }

    public function testAgencyCannotUpdateClientFromDifferentAgency(): void
    {
        // Simulate Agency A user logged in
        self::$pdo->exec("SET app.current_user_role = 'agency'");
        self::$pdo->exec("SET app.current_agency_id = '" . self::$agencyA . "'");

        // Attempt to update Agency B's client
        self::$pdo->exec("
            UPDATE clients
            SET name = 'Hacked Client'
            WHERE id = '" . self::$clientBeta . "'
        ");

        // Verify the update was blocked by RLS (0 rows affected)
        // Agency B's client should still have original name
        self::$pdo->exec("SET app.current_user_role = 'owner'");
        $stmt = self::$pdo->query("SELECT name FROM clients WHERE id = '" . self::$clientBeta . "'");
        $client = $stmt->fetch();

        $this->assertEquals('Test Client Beta', $client['name'], "RLS should prevent update");
    }

    public function testAgencyCanDeleteTheirOwnClient(): void
    {
        // Create a test client for Agency A
        self::$pdo->exec("
            INSERT INTO clients (id, agency_id, name, email)
            VALUES (
                '66666666-6666-6666-6666-666666666666',
                '" . self::$agencyA . "',
                'Test Client Delta',
                'delta@agencyA.com'
            )
        ");

        // Simulate Agency A user logged in
        self::$pdo->exec("SET app.current_user_role = 'agency'");
        self::$pdo->exec("SET app.current_agency_id = '" . self::$agencyA . "'");

        // Delete the client
        self::$pdo->exec("DELETE FROM clients WHERE id = '66666666-6666-6666-6666-666666666666'");

        // Verify deletion (as owner to bypass RLS)
        self::$pdo->exec("SET app.current_user_role = 'owner'");
        $stmt = self::$pdo->query("SELECT * FROM clients WHERE id = '66666666-6666-6666-6666-666666666666'");
        $client = $stmt->fetch();

        $this->assertFalse($client, "Client should be deleted");
    }

    public function testAgencyCannotDeleteClientFromDifferentAgency(): void
    {
        // Simulate Agency A user logged in
        self::$pdo->exec("SET app.current_user_role = 'agency'");
        self::$pdo->exec("SET app.current_agency_id = '" . self::$agencyA . "'");

        // Attempt to delete Agency B's client
        self::$pdo->exec("DELETE FROM clients WHERE id = '" . self::$clientBeta . "'");

        // Verify the client still exists (as owner to bypass RLS)
        self::$pdo->exec("SET app.current_user_role = 'owner'");
        $stmt = self::$pdo->query("SELECT * FROM clients WHERE id = '" . self::$clientBeta . "'");
        $client = $stmt->fetch();

        $this->assertNotFalse($client, "RLS should prevent deletion of other agency's client");
    }
}
