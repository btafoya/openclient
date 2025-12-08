<?php

namespace Tests\Support\Factories;

use Faker\Generator;

/**
 * User Factory for Testing
 *
 * Generates fake user data for unit and integration tests.
 * Supports all RBAC roles: owner, agency, end_client, direct_client.
 */
class UserFactory
{
    protected Generator $faker;

    public function __construct()
    {
        $this->faker = \Faker\Factory::create();
    }

    /**
     * Generate a single user with fake data
     */
    public function make(array $overrides = []): array
    {
        $role = $overrides['role'] ?? 'end_client';
        $agencyId = $overrides['agency_id'] ?? null;

        // Owner role must have null agency_id
        if ($role === 'owner') {
            $agencyId = null;
        }

        // Non-owner roles must have agency_id
        if ($role !== 'owner' && $agencyId === null) {
            $agencyId = $this->faker->uuid;
        }

        return array_merge([
            'id' => $this->faker->uuid,
            'agency_id' => $agencyId,
            'email' => $this->faker->unique()->safeEmail,
            'password_hash' => password_hash('password', PASSWORD_DEFAULT),
            'role' => $role,
            'first_name' => $this->faker->firstName,
            'last_name' => $this->faker->lastName,
            'phone' => $this->faker->phoneNumber,
            'avatar' => null,
            'is_active' => true,
            'failed_login_attempts' => 0,
            'locked_until' => null,
            'last_login_at' => null,
            'last_login_ip' => null,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
            'deleted_at' => null,
        ], $overrides);
    }

    /**
     * Generate an owner user
     */
    public function makeOwner(array $overrides = []): array
    {
        return $this->make(array_merge(['role' => 'owner', 'agency_id' => null], $overrides));
    }

    /**
     * Generate an agency user
     */
    public function makeAgency(string $agencyId, array $overrides = []): array
    {
        return $this->make(array_merge(['role' => 'agency', 'agency_id' => $agencyId], $overrides));
    }

    /**
     * Generate a direct client user
     */
    public function makeDirectClient(string $agencyId, array $overrides = []): array
    {
        return $this->make(array_merge(['role' => 'direct_client', 'agency_id' => $agencyId], $overrides));
    }

    /**
     * Generate an end client user
     */
    public function makeEndClient(string $agencyId, array $overrides = []): array
    {
        return $this->make(array_merge(['role' => 'end_client', 'agency_id' => $agencyId], $overrides));
    }

    /**
     * Generate multiple users
     */
    public function makeMany(int $count, array $overrides = []): array
    {
        $users = [];
        for ($i = 0; $i < $count; $i++) {
            $users[] = $this->make($overrides);
        }
        return $users;
    }
}
