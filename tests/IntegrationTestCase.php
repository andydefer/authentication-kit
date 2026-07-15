<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Tests;

use AndyDefer\Actions\ActionServiceProvider;
use AndyDefer\AuthenticationKit\AuthenticationKitServiceProvider;
use AndyDefer\AuthenticationKit\Tests\Mail\Providers\TestMailServiceProvider;
use AndyDefer\LaravelNotification\NotificationServiceProvider;
use AndyDefer\LaravelOtp\OtpServiceProvider;
use AndyDefer\Logger\LoggerServiceProvider;
use AndyDefer\Nemesis\NemesisServiceProvider;
use AndyDefer\Task\TaskServiceProvider;
use Carbon\Carbon;
use Illuminate\Routing\Router;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class IntegrationTestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        $router = $this->app->make(Router::class);
        $middleware = $router->getMiddleware();

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
            LoggerServiceProvider::class,
            NemesisServiceProvider::class,
            NotificationServiceProvider::class,   // ✅ Ajout du provider de notification
            TaskServiceProvider::class,           // ✅ Virgule ajoutée
            ActionServiceProvider::class,
            TestMailServiceProvider::class,
            OtpServiceProvider::class,
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

        $app['config']->set('authentication-kit.token_name', 'authentication-kit');

        // ✅ Configuration des notifications pour les tests
        $app['config']->set('notification.channels.mail', [
            'enabled' => true,
            'default_from' => 'test@example.com',
            'default_from_name' => 'Test App',
        ]);

        $app['config']->set('notification.channels.database', [
            'driver' => 'database',
            'table' => 'notifications',
        ]);
    }

    protected function runMigrations(): void
    {
        // Charger les migrations de Nemesis
        $nemesisMigrationsPath = __DIR__.'/../vendor/andydefer/laravel-nemesis/database/migrations';
        if (is_dir($nemesisMigrationsPath)) {
            $this->loadMigrationsFrom($nemesisMigrationsPath);
        }

        // Charger les migrations de Laravel Notification
        $notificationMigrationsPath = __DIR__.'/../vendor/andydefer/laravel-notification/database/migrations';
        if (is_dir($notificationMigrationsPath)) {
            $this->loadMigrationsFrom($notificationMigrationsPath);
        }

        // Charger les migrations de test
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
