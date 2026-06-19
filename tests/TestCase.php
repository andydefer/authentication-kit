<?php

declare(strict_types=1);

namespace AndyDefer\Nemesis\Tests;

use AndyDefer\Nemesis\NemesisServiceProvider;
use Carbon\Carbon;
use Illuminate\Foundation\Application;
use Orchestra\Testbench\TestCase as Orchestra;

/**
 * Base test case for the Nemesis package.
 *
 * Provides a consistent testing environment with:
 * - SQLite in-memory database for fast, isolated tests
 * - Frozen time (2024-01-01 12:00:00) for deterministic tests
 * - Package service provider registration
 * - Package-specific configuration defaults
 * - Migration loading from both package and test directories
 */
abstract class TestCase extends Orchestra
{
    /**
     * Setup the test environment.
     *
     * Freezes time to a fixed moment to ensure test consistency
     * across all test cases.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Freeze time to a fixed point for deterministic test results
        Carbon::setTestNow(Carbon::create(2024, 1, 1, 12, 0, 0));
    }

    /**
     * Clean up the test environment.
     *
     * Restores the normal time behavior after tests complete.
     */
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * Get the package service providers to register.
     *
     * @param  Application  $app  The Laravel application instance
     * @return array<int, class-string> The service providers to register
     */
    protected function getPackageProviders($app): array
    {
        return [
            NemesisServiceProvider::class,
        ];
    }

    /**
     * Configure the test environment.
     *
     * Sets up SQLite in-memory database and package-specific
     * configuration defaults for testing.
     *
     * @param  Application  $app  The Laravel application instance
     */
    protected function getEnvironmentSetUp($app): void
    {
        // Configure SQLite in-memory database for fast, isolated tests
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);

        // Configure Nemesis package defaults for testing
        $app['config']->set('nemesis.token_length', 64);
        $app['config']->set('nemesis.hash_algorithm', 'sha256');
        $app['config']->set('nemesis.middleware.parameter_name', 'nemesis_auth');
        $app['config']->set('nemesis.expiration', 60);
    }

    /**
     * Define and run database migrations for tests.
     *
     * Loads migrations from both the package's database/migrations
     * directory and the test-specific migrations directory.
     */
    protected function defineDatabaseMigrations(): void
    {
        // Load package migrations if they exist
        $packageMigrationsPath = __DIR__.'/../database/migrations';
        if (is_dir($packageMigrationsPath)) {
            $this->loadMigrationsFrom($packageMigrationsPath);
        }

        // Load test-specific migrations if they exist
        $testMigrationsPath = __DIR__.'/database/migrations';
        if (is_dir($testMigrationsPath)) {
            $this->loadMigrationsFrom($testMigrationsPath);
        }

        // Run migrations on the testbench database
        $this->artisan('migrate', [
            '--database' => 'testbench',
            '--force' => true,
        ])->run();

        parent::defineDatabaseMigrations();
    }
}
