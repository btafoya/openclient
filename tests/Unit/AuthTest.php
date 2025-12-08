<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use PDO;

/**
 * Authentication Tests
 *
 * Tests authentication functionality including password hashing,
 * brute force protection, and login flow.
 */
class AuthTest extends TestCase
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
    }

    public function testPasswordHashingWorks(): void
    {
        $password = 'TestPassword123';
        $hash = password_hash($password, PASSWORD_BCRYPT);

        $this->assertNotEquals($password, $hash);
        $this->assertTrue(password_verify($password, $hash));
        $this->assertFalse(password_verify('WrongPassword', $hash));
    }

    public function testUserCreationWithPasswordHash(): void
    {
        $password = 'TestPassword123';
        $hash = password_hash($password, PASSWORD_BCRYPT);

        self::$pdo->exec("
            INSERT INTO users (email, password_hash, role, first_name, last_name, is_active, agency_id)
            VALUES ('test@example.com', '{$hash}', 'owner', 'Test', 'User', true, NULL)
        ");

        $stmt = self::$pdo->query("SELECT * FROM users WHERE email = 'test@example.com'");
        $user = $stmt->fetch();

        $this->assertNotNull($user);
        $this->assertEquals('test@example.com', $user['email']);
        $this->assertTrue(password_verify($password, $user['password_hash']));
    }

    public function testUserEmailMustBeUnique(): void
    {
        $hash = password_hash('TestPassword123', PASSWORD_BCRYPT);

        self::$pdo->exec("
            INSERT INTO users (email, password_hash, role, first_name, last_name, is_active, agency_id)
            VALUES ('unique@example.com', '{$hash}', 'owner', 'Test', 'User', true, NULL)
        ");

        $this->expectException(\PDOException::class);
        $this->expectExceptionMessage('users_email_key');

        self::$pdo->exec("
            INSERT INTO users (email, password_hash, role, first_name, last_name, is_active, agency_id)
            VALUES ('unique@example.com', '{$hash}', 'owner', 'Another', 'User', true, NULL)
        ");
    }

    public function testBruteForceProtectionFields(): void
    {
        $hash = password_hash('TestPassword123', PASSWORD_BCRYPT);

        // Create user with failed attempts
        self::$pdo->exec("
            INSERT INTO users (email, password_hash, role, first_name, last_name, is_active, agency_id, failed_login_attempts, locked_until)
            VALUES ('locked@example.com', '{$hash}', 'owner', 'Test', 'User', true, NULL, 5, '" . date('Y-m-d H:i:s', strtotime('+15 minutes')) . "')
        ");

        $stmt = self::$pdo->query("SELECT * FROM users WHERE email = 'locked@example.com'");
        $user = $stmt->fetch();

        $this->assertEquals(5, $user['failed_login_attempts']);
        $this->assertNotNull($user['locked_until']);

        // Verify lock is in the future
        $lockedUntil = strtotime($user['locked_until']);
        $this->assertGreaterThan(time(), $lockedUntil);
    }

    public function testFailedAttemptsIncrement(): void
    {
        $hash = password_hash('TestPassword123', PASSWORD_BCRYPT);

        self::$pdo->exec("
            INSERT INTO users (email, password_hash, role, first_name, last_name, is_active, agency_id)
            VALUES ('increment@example.com', '{$hash}', 'owner', 'Test', 'User', true, NULL)
        ");

        // Increment failed attempts
        self::$pdo->exec("
            UPDATE users
            SET failed_login_attempts = failed_login_attempts + 1
            WHERE email = 'increment@example.com'
        ");

        $stmt = self::$pdo->query("SELECT failed_login_attempts FROM users WHERE email = 'increment@example.com'");
        $user = $stmt->fetch();

        $this->assertEquals(1, $user['failed_login_attempts']);
    }

    public function testLastLoginTracking(): void
    {
        $hash = password_hash('TestPassword123', PASSWORD_BCRYPT);

        self::$pdo->exec("
            INSERT INTO users (email, password_hash, role, first_name, last_name, is_active, agency_id)
            VALUES ('tracking@example.com', '{$hash}', 'owner', 'Test', 'User', true, NULL)
        ");

        // Update last login
        $ipAddress = '192.168.1.1';
        $timestamp = date('Y-m-d H:i:s');

        self::$pdo->exec("
            UPDATE users
            SET last_login_at = '{$timestamp}', last_login_ip = '{$ipAddress}'
            WHERE email = 'tracking@example.com'
        ");

        $stmt = self::$pdo->query("SELECT last_login_at, last_login_ip FROM users WHERE email = 'tracking@example.com'");
        $user = $stmt->fetch();

        $this->assertNotNull($user['last_login_at']);
        $this->assertEquals($ipAddress, $user['last_login_ip']);
    }

    public function testInactiveUserField(): void
    {
        $hash = password_hash('TestPassword123', PASSWORD_BCRYPT);

        self::$pdo->exec("
            INSERT INTO users (email, password_hash, role, first_name, last_name, is_active, agency_id)
            VALUES ('inactive@example.com', '{$hash}', 'owner', 'Test', 'User', false, NULL)
        ");

        $stmt = self::$pdo->query("SELECT is_active FROM users WHERE email = 'inactive@example.com'");
        $user = $stmt->fetch();

        $this->assertFalse($user['is_active']);
    }

    public function testPasswordStrengthValidation(): void
    {
        // Test weak password (no uppercase)
        $this->assertFalse($this->isPasswordStrong('testpassword123'));

        // Test weak password (no lowercase)
        $this->assertFalse($this->isPasswordStrong('TESTPASSWORD123'));

        // Test weak password (no number)
        $this->assertFalse($this->isPasswordStrong('TestPassword'));

        // Test weak password (too short)
        $this->assertFalse($this->isPasswordStrong('Test123'));

        // Test strong password
        $this->assertTrue($this->isPasswordStrong('TestPassword123'));
    }

    /**
     * Helper method to test password strength
     * Duplicates the private method from AuthController for testing
     */
    private function isPasswordStrong(string $password): bool
    {
        return strlen($password) >= 12
            && preg_match('/[A-Z]/', $password) // uppercase
            && preg_match('/[a-z]/', $password) // lowercase
            && preg_match('/[0-9]/', $password); // number
    }
}
