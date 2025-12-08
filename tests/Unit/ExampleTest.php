<?php

namespace Tests\Unit;

use Tests\Support\TestCase;

class ExampleTest extends TestCase
{
    public function test_example_passes(): void
    {
        $this->assertTrue(true);
    }

    public function test_environment_is_testing(): void
    {
        $this->assertEquals('testing', getenv('CI_ENVIRONMENT'));
    }
}
