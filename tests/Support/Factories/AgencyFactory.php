<?php

namespace Tests\Support\Factories;

use Faker\Generator;

/**
 * Agency Factory for Testing
 *
 * Generates fake agency data for unit and integration tests.
 */
class AgencyFactory
{
    protected Generator $faker;

    public function __construct()
    {
        $this->faker = \Faker\Factory::create();
    }

    /**
     * Generate a single agency with fake data
     */
    public function make(array $overrides = []): array
    {
        return array_merge([
            'id' => $this->faker->uuid,
            'name' => $this->faker->company,
            'email' => $this->faker->companyEmail,
            'phone' => $this->faker->phoneNumber,
            'address' => $this->faker->streetAddress,
            'city' => $this->faker->city,
            'state' => $this->faker->state,
            'postal_code' => $this->faker->postcode,
            'country' => 'USA',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
            'deleted_at' => null,
        ], $overrides);
    }

    /**
     * Generate multiple agencies
     */
    public function makeMany(int $count, array $overrides = []): array
    {
        $agencies = [];
        for ($i = 0; $i < $count; $i++) {
            $agencies[] = $this->make($overrides);
        }
        return $agencies;
    }
}
