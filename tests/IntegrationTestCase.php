<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Tests;

use AndyDefer\Actions\ActionServiceProvider;
use AndyDefer\AuthenticationKit\AuthenticationKitServiceProvider;
use AndyDefer\Nemesis\NemesisServiceProvider;
use Carbon\Carbon;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class IntegrationTestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::create(2024, 1, 1, 12, 0, 0));

        $this->runMigrations();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
        \Mockery::close();
    }

    protected function getPackageProviders($app): array
    {
        return [
            NemesisServiceProvider::class,
            ActionServiceProvider::class,
            AuthenticationKitServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);

        $app['config']->set('nemesis.token_length', 64);
        $app['config']->set('nemesis.hash_algorithm', 'sha256');
        $app['config']->set('nemesis.middleware.parameter_name', 'nemesis_auth');
        $app['config']->set('nemesis.expiration', 60);
    }

    protected function runMigrations(): void
    {
        $testMigrationsPath = __DIR__.'/Mail/Fixtures/database/migrations';
        if (is_dir($testMigrationsPath)) {
            $this->loadMigrationsFrom($testMigrationsPath);
        }

        $this->artisan('migrate', [
            '--database' => 'testbench',
            '--force' => true,
        ])->run();
    }
}
