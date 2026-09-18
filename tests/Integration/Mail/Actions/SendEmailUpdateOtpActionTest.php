<?php

// tests/Integration/Mail/Actions/SendEmailUpdateOtpActionTest.php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Tests\Mail\Actions;

use AndyDefer\AuthenticationKit\Configs\AuthenticationKitConfig;
use AndyDefer\AuthenticationKit\Contracts\Configs\AuthenticationKitConfigInterface;
use AndyDefer\AuthenticationKit\Mail\Actions\SendEmailUpdateOtpAction;
use AndyDefer\AuthenticationKit\Mail\Requests\SendEmailUpdateOtpRequest;
use AndyDefer\AuthenticationKit\Tests\IntegrationTestCase;
use AndyDefer\AuthenticationKit\Tests\Mail\Fixtures\Models\TestUserMail;
use AndyDefer\DomainStructures\Utils\StrictDataObject;
use AndyDefer\LaravelOtp\Services\OtpService;
use AndyDefer\LaravelOtp\ValueObjects\PurposeVO;
use AndyDefer\Nemesis\Contracts\Services\NemesisInterface;
use AndyDefer\Nemesis\Records\NemesisTokenRecord;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Config;

final class SendEmailUpdateOtpActionTest extends IntegrationTestCase
{
    use DatabaseMigrations;

    private const TEST_EMAIL = 'john@example.com';

    private const TEST_NEW_EMAIL = 'new-email@example.com';

    private OtpService $otpService;

    private NemesisInterface $nemesis;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('mail.default', 'log');
        Config::set('mail.mailers.log', [
            'transport' => 'log',
            'channel' => 'single',
        ]);

        Config::set('notification.channels.mail', [
            'enabled' => true,
            'driver' => 'mail',
            'default_from' => 'test@example.com',
            'default_from_name' => 'Test App',
        ]);

        $this->app['config']->set('authentication-kit', [
            'token_name' => 'authentication-kit',
            'password_reset_rate_limit' => 3,
            'email_verification_rate_limit' => 5,
            'email_update_rate_limit' => 3,
            'store_token_in_cookie' => false,
        ]);

        $this->app['config']->set('nemesis.web', [
            'login_route' => '/login',
            'dashboard_route' => '/dashboard',
            'cookie_name' => 'nemesis_token',
            'cookie_secure' => false,
            'cookie_httponly' => false,
            'cookie_samesite' => 'lax',
        ]);

        $this->app->singleton(
            AuthenticationKitConfigInterface::class,
            function ($app) {
                return new AuthenticationKitConfig(
                    $app['config']
                );
            }
        );

        $this->app['router']->middleware(['validate.mail.authenticatable', 'nemesis.token'])->post('/api/send-email-update-otp', action_route(
            SendEmailUpdateOtpRequest::class,
            SendEmailUpdateOtpAction::class
        ));

        $this->otpService = $this->app->make(OtpService::class);
        $this->nemesis = $this->app->make(NemesisInterface::class);
    }

    private function createUser(array $overrides = []): TestUserMail
    {
        $defaults = [
            'name' => 'John Doe',
            'email' => self::TEST_EMAIL,
            'password' => bcrypt('Password123!'),
            'email_verified_at' => now(),
        ];

        return TestUserMail::create(array_merge($defaults, $overrides));
    }

    private function createUserAndGetToken(array $overrides = []): array
    {
        $user = $this->createUser($overrides);

        $config = $this->app->make(AuthenticationKitConfigInterface::class);

        [$tokenModel, $plainToken] = $this->nemesis->createWithPlainToken(
            new NemesisTokenRecord(
                name: $config->getTokenName(),
                source: 'login',
                metadata: new StrictDataObject([]),
            ),
            $user
        );

        return [$user, $plainToken];
    }

    // ============================================================================
    // Tests - Succès
    // ============================================================================

    public function test_send_email_update_otp_successfully(): void
    {
        [$user, $plainToken] = $this->createUserAndGetToken();

        $payload = [
            'model_type' => TestUserMail::class,
            'email' => self::TEST_NEW_EMAIL,
        ];

        $response = $this->postJson('/api/send-email-update-otp', $payload, [
            'Authorization' => 'Bearer '.$plainToken,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'message' => 'Email update code sent',
            'status' => 200,
        ]);

        $purpose = new PurposeVO(
            value: 'email_update',
            label: 'Email Update',
            ttl: 600,
            maxAttempts: 3,
        );

        $otps = $this->otpService->getAllFor($user, $purpose);
        $this->assertCount(1, $otps);
    }

    public function test_send_email_update_otp_normalizes_email_to_lowercase(): void
    {
        [$user, $plainToken] = $this->createUserAndGetToken();

        $payload = [
            'model_type' => TestUserMail::class,
            'email' => 'NEW-EMAIL@Example.COM',
        ];

        $response = $this->postJson('/api/send-email-update-otp', $payload, [
            'Authorization' => 'Bearer '.$plainToken,
        ]);

        $response->assertStatus(200);

        $purpose = new PurposeVO(
            value: 'email_update',
            label: 'Email Update',
            ttl: 600,
            maxAttempts: 3,
        );

        $otps = $this->otpService->getAllFor($user, $purpose);
        $this->assertCount(1, $otps);
    }

    // ============================================================================
    // Tests - Erreurs d'authentification
    // ============================================================================

    public function test_send_email_update_otp_returns_401_when_unauthenticated(): void
    {
        $payload = [
            'model_type' => TestUserMail::class,
            'email' => self::TEST_NEW_EMAIL,
        ];

        $response = $this->postJson('/api/send-email-update-otp', $payload);

        $response->assertStatus(401);
    }

    public function test_send_email_update_otp_returns_401_when_token_invalid(): void
    {
        $payload = [
            'model_type' => TestUserMail::class,
            'email' => self::TEST_NEW_EMAIL,
        ];

        $response = $this->postJson('/api/send-email-update-otp', $payload, [
            'Authorization' => 'Bearer invalid-token',
        ]);

        $response->assertStatus(401);
    }

    // ============================================================================
    // Tests - Erreurs de validation (le middleware valide en amont)
    // ============================================================================

    public function test_send_email_update_otp_returns_400_when_model_type_is_missing(): void
    {
        [, $plainToken] = $this->createUserAndGetToken();

        $response = $this->postJson('/api/send-email-update-otp', [
            'email' => self::TEST_NEW_EMAIL,
        ], [
            'Authorization' => 'Bearer '.$plainToken,
        ]);

        // Le middleware validate.mail.authenticatable intercepte avant le Form Request
        $response->assertStatus(400);
        $response->assertJson([
            'errorCode' => 'MODEL_TYPE_REQUIRED',
        ]);
    }

    public function test_send_email_update_otp_returns_422_when_email_is_missing(): void
    {
        [, $plainToken] = $this->createUserAndGetToken();

        $response = $this->postJson('/api/send-email-update-otp', [
            'model_type' => TestUserMail::class,
        ], [
            'Authorization' => 'Bearer '.$plainToken,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);
    }

    public function test_send_email_update_otp_returns_422_when_email_is_invalid(): void
    {
        [, $plainToken] = $this->createUserAndGetToken();

        $response = $this->postJson('/api/send-email-update-otp', [
            'model_type' => TestUserMail::class,
            'email' => 'invalid-email',
        ], [
            'Authorization' => 'Bearer '.$plainToken,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);
    }

    public function test_send_email_update_otp_returns_422_when_email_too_long(): void
    {
        [, $plainToken] = $this->createUserAndGetToken();

        $longEmail = str_repeat('a', 250).'@example.com';

        $response = $this->postJson('/api/send-email-update-otp', [
            'model_type' => TestUserMail::class,
            'email' => $longEmail,
        ], [
            'Authorization' => 'Bearer '.$plainToken,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);
    }

    // ============================================================================
    // Tests - Erreurs métier
    // ============================================================================

    public function test_send_email_update_otp_returns_429_when_email_already_taken(): void
    {
        $this->createUser(['email' => self::TEST_NEW_EMAIL]);

        [, $plainToken] = $this->createUserAndGetToken();

        $payload = [
            'model_type' => TestUserMail::class,
            'email' => self::TEST_NEW_EMAIL,
        ];

        $response = $this->postJson('/api/send-email-update-otp', $payload, [
            'Authorization' => 'Bearer '.$plainToken,
        ]);

        $response->assertStatus(429);
        $response->assertJson([
            'message' => 'Unable to send email update code',
            'errorCode' => 'EMAIL_UPDATE_SEND_FAILED',
        ]);
    }

    public function test_send_email_update_otp_returns_429_when_rate_limit_exceeded(): void
    {
        [, $plainToken] = $this->createUserAndGetToken();

        $this->postJson('/api/send-email-update-otp', [
            'model_type' => TestUserMail::class,
            'email' => 'new1@example.com',
        ], ['Authorization' => 'Bearer '.$plainToken]);

        $this->postJson('/api/send-email-update-otp', [
            'model_type' => TestUserMail::class,
            'email' => 'new2@example.com',
        ], ['Authorization' => 'Bearer '.$plainToken]);

        $this->postJson('/api/send-email-update-otp', [
            'model_type' => TestUserMail::class,
            'email' => 'new3@example.com',
        ], ['Authorization' => 'Bearer '.$plainToken]);

        $response = $this->postJson('/api/send-email-update-otp', [
            'model_type' => TestUserMail::class,
            'email' => 'new4@example.com',
        ], ['Authorization' => 'Bearer '.$plainToken]);

        $response->assertStatus(429);
        $response->assertJson([
            'message' => 'Unable to send email update code',
            'errorCode' => 'EMAIL_UPDATE_SEND_FAILED',
        ]);
    }

    // ============================================================================
    // Tests - Cas limites
    // ============================================================================

    public function test_send_email_update_otp_multiple_times_different_emails(): void
    {
        [$user, $plainToken] = $this->createUserAndGetToken();

        $this->postJson('/api/send-email-update-otp', [
            'model_type' => TestUserMail::class,
            'email' => 'first@example.com',
        ], ['Authorization' => 'Bearer '.$plainToken])->assertStatus(200);

        $this->postJson('/api/send-email-update-otp', [
            'model_type' => TestUserMail::class,
            'email' => 'second@example.com',
        ], ['Authorization' => 'Bearer '.$plainToken])->assertStatus(200);

        $purpose = new PurposeVO(
            value: 'email_update',
            label: 'Email Update',
            ttl: 600,
            maxAttempts: 3,
        );

        $otps = $this->otpService->getAllFor($user, $purpose);
        $this->assertCount(2, $otps);
    }
}
