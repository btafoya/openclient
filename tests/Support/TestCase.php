<?php

namespace Tests\Support;

use PHPUnit\Framework\TestCase as BaseTestCase;

/**
 * Base Test Case for OpenClient
 *
 * Provides common functionality for all tests including:
 * - Database setup and teardown
 * - Faker instance
 * - Helper methods for testing
 */
abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Additional setup can be added here
    }

    protected function tearDown(): void
    {
        // Cleanup after each test
        parent::tearDown();
    }

    /**
     * Assert that a value is a valid UUID v4
     */
    protected function assertIsUuid(string $value, string $message = ''): void
    {
        $pattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';
        $this->assertMatchesRegularExpression($pattern, $value, $message);
    }

    /**
     * Assert that an array has the specified keys
     */
    protected function assertArrayHasKeys(array $keys, array $array, string $message = ''): void
    {
        foreach ($keys as $key) {
            $this->assertArrayHasKey($key, $array, $message ?: "Array does not have key: {$key}");
        }
    }
}
