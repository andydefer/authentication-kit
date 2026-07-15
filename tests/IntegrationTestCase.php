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
    private array $testEnv = [];

    protected function setUp(): void
    {
        parent::setUp();

        $router = $this->app->make(Router::class);
        $middleware = $router->getMiddleware();

        Carbon::setTestNow(Carbon::create(2024, 1, 1, 12, 0, 0));

        $this->loadTestEnvironment();
        $this->setUpEnvironmentVariables();
        $this->runMigrations();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
        \Mockery::close();
    }

    /**
     * Load test environment configuration.
     */
    protected function loadTestEnvironment(): void
    {
        $envFile = __DIR__.'/test_env.php';

        if (file_exists($envFile)) {
            $this->testEnv = require $envFile;
        } else {
            $this->testEnv = $this->getDefaultTestEnvironment();
        }
    }

    /**
     * Get default test environment variables.
     */
    protected function getDefaultTestEnvironment(): array
    {
        return [
            // Mail
            'MAIL_FROM_ADDRESS' => 'noreply@test.com',
            'MAIL_FROM_NAME' => 'Test App',
            'MAIL_DEFAULT_TO' => 'test@example.com',

            // SMS (Twilio)
            'TWILIO_SID' => 'ACtest123456789',
            'TWILIO_TOKEN' => 'testtoken123456789',
            'TWILIO_FROM' => '+1234567890',

            // WhatsApp (Meta)
            'WHATSAPP_ACCESS_TOKEN' => 'test_access_token_123456789',
            'WHATSAPP_PHONE_NUMBER_ID' => '123456789012345',

            // Slack
            'SLACK_WEBHOOK_URL' => 'https://hooks.slack.com/services/fake/fake/fake',

            // Telegram
            'TELEGRAM_BOT_TOKEN' => '1234567890:ABCDEFGHIJKLMNOPQRSTUVWXYZ',
            'TELEGRAM_CHAT_ID' => '-123456789',

            // Push (FCM/APNS)
            'FCM_API_KEY' => 'AAAAtest123456789',
            'FCM_PROJECT_ID' => 'test-project-123456',
            'APNS_KEY_PATH' => '/path/to/apns/key.p8',
            'APNS_KEY_ID' => 'ABCDEF1234',
            'APNS_TEAM_ID' => 'ABCDEF1234',
            'APNS_BUNDLE_ID' => 'com.test.app',

            // Logs
            'NOTIFICATION_LOG_CHANNEL' => 'daily',
            'NOTIFICATION_LOG_LEVEL' => 'debug',
        ];
    }

    /**
     * Set up environment variables using putenv().
     */
    protected function setUpEnvironmentVariables(): void
    {
        // ✅ Mail
        putenv('MAIL_FROM_ADDRESS='.$this->testEnv['MAIL_FROM_ADDRESS']);
        putenv('MAIL_FROM_NAME='.$this->testEnv['MAIL_FROM_NAME']);
        putenv('MAIL_DEFAULT_TO='.$this->testEnv['MAIL_DEFAULT_TO']);

        // ✅ SMS (Twilio)
        putenv('TWILIO_SID='.$this->testEnv['TWILIO_SID']);
        putenv('TWILIO_TOKEN='.$this->testEnv['TWILIO_TOKEN']);
        putenv('TWILIO_FROM='.$this->testEnv['TWILIO_FROM']);

        // ✅ WhatsApp (Meta)
        putenv('WHATSAPP_ACCESS_TOKEN='.$this->testEnv['WHATSAPP_ACCESS_TOKEN']);
        putenv('WHATSAPP_PHONE_NUMBER_ID='.$this->testEnv['WHATSAPP_PHONE_NUMBER_ID']);

        // ✅ Slack
        putenv('SLACK_WEBHOOK_URL='.$this->testEnv['SLACK_WEBHOOK_URL']);

        // ✅ Telegram
        putenv('TELEGRAM_BOT_TOKEN='.$this->testEnv['TELEGRAM_BOT_TOKEN']);
        putenv('TELEGRAM_CHAT_ID='.$this->testEnv['TELEGRAM_CHAT_ID']);

        // ✅ Push (FCM/APNS)
        putenv('FCM_API_KEY='.$this->testEnv['FCM_API_KEY']);
        putenv('FCM_PROJECT_ID='.$this->testEnv['FCM_PROJECT_ID']);
        putenv('APNS_KEY_PATH='.$this->testEnv['APNS_KEY_PATH']);
        putenv('APNS_KEY_ID='.$this->testEnv['APNS_KEY_ID']);
        putenv('APNS_TEAM_ID='.$this->testEnv['APNS_TEAM_ID']);
        putenv('APNS_BUNDLE_ID='.$this->testEnv['APNS_BUNDLE_ID']);

        // ✅ Logs
        putenv('NOTIFICATION_LOG_CHANNEL='.$this->testEnv['NOTIFICATION_LOG_CHANNEL']);
        putenv('NOTIFICATION_LOG_LEVEL='.$this->testEnv['NOTIFICATION_LOG_LEVEL']);

        // ✅ Rendre les variables disponibles dans $_ENV
        foreach ($this->testEnv as $key => $value) {
            $_ENV[$key] = $value;
        }
    }

    protected function getPackageProviders($app): array
    {
        return [
            LoggerServiceProvider::class,
            NemesisServiceProvider::class,
            NotificationServiceProvider::class,
            TaskServiceProvider::class,
            ActionServiceProvider::class,
            TestMailServiceProvider::class,
            OtpServiceProvider::class,
            AuthenticationKitServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        // ✅ Base de données
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);

        // ✅ Nemesis
        $app['config']->set('nemesis.token_length', 64);
        $app['config']->set('nemesis.hash_algorithm', 'sha256');
        $app['config']->set('nemesis.middleware.parameter_name', 'nemesis_auth');
        $app['config']->set('nemesis.expiration', 60);

        // ✅ Authentication
        $app['config']->set('authentication-kit.token_name', 'authentication-kit');

        // ✅ Mail - Utilise les variables d'environnement
        $app['config']->set('mail.default', 'array');
        $app['config']->set('mail.mailers.array', [
            'transport' => 'array',
        ]);

        // ✅ Notification - Utilise les variables d'environnement
        $app['config']->set('notification.channels.mail', [
            'enabled' => true,
            'driver' => 'mail',
            'default_from' => env('MAIL_FROM_ADDRESS', 'test@example.com'),
            'default_from_name' => env('MAIL_FROM_NAME', 'Test App'),
        ]);

        $app['config']->set('notification.channels.database', [
            'driver' => 'database',
            'table' => 'notifications',
        ]);

        $app['config']->set('notification.default_channels', ['mail', 'database']);

        // ✅ Logger
        $app['config']->set('logger.base_path', storage_path('logs'));
    }

    protected function runMigrations(): void
    {
        // ✅ Nemesis migrations
        $nemesisMigrationsPath = __DIR__.'/../vendor/andydefer/laravel-nemesis/database/migrations';
        if (is_dir($nemesisMigrationsPath)) {
            $this->loadMigrationsFrom($nemesisMigrationsPath);
        }

        // ✅ Notification migrations
        $notificationMigrationsPath = __DIR__.'/../vendor/andydefer/laravel-notification/database/migrations';
        if (is_dir($notificationMigrationsPath)) {
            $this->loadMigrationsFrom($notificationMigrationsPath);
        }

        // ✅ Test migrations
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
