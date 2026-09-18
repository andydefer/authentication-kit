<?php

// tests/Integration/Mail/Actions/UpdateEmailActionTest.php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Tests\Mail\Actions;

use AndyDefer\AuthenticationKit\Configs\AuthenticationKitConfig;
use AndyDefer\AuthenticationKit\Contracts\Configs\AuthenticationKitConfigInterface;
use AndyDefer\AuthenticationKit\Mail\Actions\SendEmailUpdateOtpAction;
use AndyDefer\AuthenticationKit\Mail\Actions\UpdateEmailAction;
use AndyDefer\AuthenticationKit\Mail\Requests\SendEmailUpdateOtpRequest;
use AndyDefer\AuthenticationKit\Mail\Requests\UpdateEmailRequest;
use AndyDefer\AuthenticationKit\Tests\IntegrationTestCase;
use AndyDefer\AuthenticationKit\Tests\Mail\Fixtures\Models\TestUserMail;
use AndyDefer\DomainStructures\Utils\StrictDataObject;
use AndyDefer\LaravelOtp\Services\OtpService;
use AndyDefer\LaravelOtp\ValueObjects\PurposeVO;
use AndyDefer\Nemesis\Contracts\Services\NemesisInterface;
use AndyDefer\Nemesis\Records\NemesisTokenRecord;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Config;

final class UpdateEmailActionTest extends IntegrationTestCase
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

        $this->app['router']->middleware(['validate.mail.authenticatable', 'nemesis.token'])->post('/api/update-email', action_route(
            UpdateEmailRequest::class,
            UpdateEmailAction::class
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

    private function createEmailUpdateOtp(TestUserMail $user): string
    {
        $purpose = new PurposeVO(
            value: 'email_update',
            label: 'Email Update',
            ttl: 600,
            maxAttempts: 3,
        );

        $otp = $this->otpService->create($user, $purpose);

        return $otp->code;
    }

    // ============================================================================
    // Tests - Succès
    // ============================================================================

    public function test_update_email_successfully_with_valid_otp(): void
    {
        [$user, $plainToken] = $this->createUserAndGetToken();
        $otpCode = $this->createEmailUpdateOtp($user);

        $payload = [
            'model_type' => TestUserMail::class,
            'email' => self::TEST_NEW_EMAIL,
            'code' => $otpCode,
        ];

        $response = $this->postJson('/api/update-email', $payload, [
            'Authorization' => 'Bearer '.$plainToken,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'message' => 'Email updated successfully. Please verify your new email.',
            'status' => 200,
        ]);

        $user->refresh();
        $this->assertEquals(self::TEST_NEW_EMAIL, $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_update_email_resets_email_verified_at(): void
    {
        [$user, $plainToken] = $this->createUserAndGetToken(['email_verified_at' => now()]);
        $otpCode = $this->createEmailUpdateOtp($user);

        $payload = [
            'model_type' => TestUserMail::class,
            'email' => self::TEST_NEW_EMAIL,
            'code' => $otpCode,
        ];

        $this->postJson('/api/update-email', $payload, [
            'Authorization' => 'Bearer '.$plainToken,
        ])->assertStatus(200);

        $user->refresh();
        $this->assertNull($user->email_verified_at);
    }

    public function test_update_email_normalizes_to_lowercase(): void
    {
        [$user, $plainToken] = $this->createUserAndGetToken();
        $otpCode = $this->createEmailUpdateOtp($user);

        $payload = [
            'model_type' => TestUserMail::class,
            'email' => 'NEW-EMAIL@Example.COM',
            'code' => $otpCode,
        ];

        $this->postJson('/api/update-email', $payload, [
            'Authorization' => 'Bearer '.$plainToken,
        ])->assertStatus(200);

        $user->refresh();
        $this->assertEquals('new-email@example.com', $user->email);
    }

    // ============================================================================
    // Tests - Erreurs d'authentification
    // ============================================================================

    public function test_update_email_returns_401_when_unauthenticated(): void
    {
        $payload = [
            'model_type' => TestUserMail::class,
            'email' => self::TEST_NEW_EMAIL,
            'code' => '123456',
        ];

        $response = $this->postJson('/api/update-email', $payload);

        $response->assertStatus(401);
    }

    public function test_update_email_returns_401_when_token_invalid(): void
    {
        $payload = [
            'model_type' => TestUserMail::class,
            'email' => self::TEST_NEW_EMAIL,
            'code' => '123456',
        ];

        $response = $this->postJson('/api/update-email', $payload, [
            'Authorization' => 'Bearer invalid-token',
        ]);

        $response->assertStatus(401);
    }

    // ============================================================================
    // Tests - Erreurs de validation (middleware en amont pour model_type)
    // ============================================================================

    public function test_update_email_returns_400_when_model_type_is_missing(): void
    {
        [, $plainToken] = $this->createUserAndGetToken();

        $response = $this->postJson('/api/update-email', [
            'email' => self::TEST_NEW_EMAIL,
            'code' => '123456',
        ], [
            'Authorization' => 'Bearer '.$plainToken,
        ]);

        $response->assertStatus(400);
        $response->assertJson([
            'errorCode' => 'MODEL_TYPE_REQUIRED',
        ]);
    }

    public function test_update_email_returns_422_when_email_is_missing(): void
    {
        [, $plainToken] = $this->createUserAndGetToken();

        $response = $this->postJson('/api/update-email', [
            'model_type' => TestUserMail::class,
            'code' => '123456',
        ], [
            'Authorization' => 'Bearer '.$plainToken,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);
    }

    public function test_update_email_returns_422_when_code_is_missing(): void
    {
        [, $plainToken] = $this->createUserAndGetToken();

        $response = $this->postJson('/api/update-email', [
            'model_type' => TestUserMail::class,
            'email' => self::TEST_NEW_EMAIL,
        ], [
            'Authorization' => 'Bearer '.$plainToken,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['code']);
    }

    public function test_update_email_returns_422_when_email_is_invalid(): void
    {
        [, $plainToken] = $this->createUserAndGetToken();

        $response = $this->postJson('/api/update-email', [
            'model_type' => TestUserMail::class,
            'email' => 'invalid-email',
            'code' => '123456',
        ], [
            'Authorization' => 'Bearer '.$plainToken,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);
    }

    public function test_update_email_returns_422_when_code_too_short(): void
    {
        [, $plainToken] = $this->createUserAndGetToken();

        $response = $this->postJson('/api/update-email', [
            'model_type' => TestUserMail::class,
            'email' => self::TEST_NEW_EMAIL,
            'code' => '12',
        ], [
            'Authorization' => 'Bearer '.$plainToken,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['code']);
    }

    public function test_update_email_returns_422_when_code_too_long(): void
    {
        [, $plainToken] = $this->createUserAndGetToken();

        $response = $this->postJson('/api/update-email', [
            'model_type' => TestUserMail::class,
            'email' => self::TEST_NEW_EMAIL,
            'code' => '12345678901',
        ], [
            'Authorization' => 'Bearer '.$plainToken,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['code']);
    }

    // ============================================================================
    // Tests - Erreurs métier
    // ============================================================================

    public function test_update_email_returns_400_when_otp_invalid(): void
    {
        [$user, $plainToken] = $this->createUserAndGetToken();

        $payload = [
            'model_type' => TestUserMail::class,
            'email' => self::TEST_NEW_EMAIL,
            'code' => '000000',
        ];

        $response = $this->postJson('/api/update-email', $payload, [
            'Authorization' => 'Bearer '.$plainToken,
        ]);

        $response->assertStatus(400);
        $response->assertJson([
            'message' => 'Invalid or expired code, or email already taken',
            'errorCode' => 'EMAIL_UPDATE_FAILED',
        ]);

        $user->refresh();
        $this->assertEquals(self::TEST_EMAIL, $user->email);
    }

    public function test_update_email_returns_400_when_otp_expired(): void
    {
        [$user, $plainToken] = $this->createUserAndGetToken();
        $otpCode = $this->createEmailUpdateOtp($user);

        $purpose = new PurposeVO(
            value: 'email_update',
            label: 'Email Update',
            ttl: 600,
            maxAttempts: 3,
        );

        $otps = $this->otpService->getAllFor($user, $purpose);
        $otp = $otps->first();
        $otp->expires_at = now()->subSecond();
        $otp->save();

        $payload = [
            'model_type' => TestUserMail::class,
            'email' => self::TEST_NEW_EMAIL,
            'code' => $otpCode,
        ];

        $response = $this->postJson('/api/update-email', $payload, [
            'Authorization' => 'Bearer '.$plainToken,
        ]);

        $response->assertStatus(400);
        $response->assertJson([
            'errorCode' => 'EMAIL_UPDATE_FAILED',
        ]);

        $user->refresh();
        $this->assertEquals(self::TEST_EMAIL, $user->email);
    }

    public function test_update_email_returns_400_when_otp_already_used(): void
    {
        [$user, $plainToken] = $this->createUserAndGetToken();
        $otpCode = $this->createEmailUpdateOtp($user);

        $payload = [
            'model_type' => TestUserMail::class,
            'email' => self::TEST_NEW_EMAIL,
            'code' => $otpCode,
        ];

        $this->postJson('/api/update-email', $payload, [
            'Authorization' => 'Bearer '.$plainToken,
        ])->assertStatus(200);

        $response = $this->postJson('/api/update-email', $payload, [
            'Authorization' => 'Bearer '.$plainToken,
        ]);

        $response->assertStatus(400);
        $response->assertJson([
            'errorCode' => 'EMAIL_UPDATE_FAILED',
        ]);
    }

    public function test_update_email_returns_400_when_otp_wrong_purpose(): void
    {
        [$user, $plainToken] = $this->createUserAndGetToken();

        $purpose = new PurposeVO(
            value: 'email_verification',
            label: 'Email Verification',
            ttl: 300,
            maxAttempts: 3,
        );

        $otp = $this->otpService->create($user, $purpose);

        $payload = [
            'model_type' => TestUserMail::class,
            'email' => self::TEST_NEW_EMAIL,
            'code' => $otp->code,
        ];

        $response = $this->postJson('/api/update-email', $payload, [
            'Authorization' => 'Bearer '.$plainToken,
        ]);

        $response->assertStatus(400);
        $response->assertJson([
            'errorCode' => 'EMAIL_UPDATE_FAILED',
        ]);

        $user->refresh();
        $this->assertEquals(self::TEST_EMAIL, $user->email);
    }

    public function test_update_email_returns_400_when_attempts_exceeded(): void
    {
        [$user, $plainToken] = $this->createUserAndGetToken();
        $otpCode = $this->createEmailUpdateOtp($user);

        $purpose = new PurposeVO(
            value: 'email_update',
            label: 'Email Update',
            ttl: 600,
            maxAttempts: 3,
        );

        $otps = $this->otpService->getAllFor($user, $purpose);
        $otp = $otps->first();
        $otp->attempts = 3;
        $otp->save();

        $payload = [
            'model_type' => TestUserMail::class,
            'email' => self::TEST_NEW_EMAIL,
            'code' => $otpCode,
        ];

        $response = $this->postJson('/api/update-email', $payload, [
            'Authorization' => 'Bearer '.$plainToken,
        ]);

        $response->assertStatus(400);
        $response->assertJson([
            'errorCode' => 'EMAIL_UPDATE_FAILED',
        ]);

        $user->refresh();
        $this->assertEquals(self::TEST_EMAIL, $user->email);
    }

    // ============================================================================
    // Tests - Cas limites
    // ============================================================================

    public function test_update_email_uses_otp_once(): void
    {
        [$user, $plainToken] = $this->createUserAndGetToken();
        $otpCode = $this->createEmailUpdateOtp($user);

        $payload = [
            'model_type' => TestUserMail::class,
            'email' => self::TEST_NEW_EMAIL,
            'code' => $otpCode,
        ];

        $this->postJson('/api/update-email', $payload, [
            'Authorization' => 'Bearer '.$plainToken,
        ])->assertStatus(200);

        $response = $this->postJson('/api/update-email', $payload, [
            'Authorization' => 'Bearer '.$plainToken,
        ]);

        $response->assertStatus(400);
    }

    public function test_update_email_with_whitespace_in_email(): void
    {
        [$user, $plainToken] = $this->createUserAndGetToken();
        $otpCode = $this->createEmailUpdateOtp($user);

        $payload = [
            'model_type' => TestUserMail::class,
            'email' => '  '.self::TEST_NEW_EMAIL.'  ',
            'code' => $otpCode,
        ];

        $this->postJson('/api/update-email', $payload, [
            'Authorization' => 'Bearer '.$plainToken,
        ])->assertStatus(200);

        $user->refresh();
        $this->assertEquals(self::TEST_NEW_EMAIL, $user->email);
    }

    // ============================================================================
    // Test d'intégration complet
    // ============================================================================

    public function test_complete_email_update_flow(): void
    {
        [$user, $plainToken] = $this->createUserAndGetToken();

        $sendResponse = $this->postJson('/api/send-email-update-otp', [
            'model_type' => TestUserMail::class,
            'email' => self::TEST_NEW_EMAIL,
        ], [
            'Authorization' => 'Bearer '.$plainToken,
        ]);
        $sendResponse->assertStatus(200);

        $purpose = new PurposeVO(
            value: 'email_update',
            label: 'Email Update',
            ttl: 600,
            maxAttempts: 3,
        );

        $otps = $this->otpService->getAllFor($user, $purpose);
        $otpCode = $otps->first()->code;

        $updateResponse = $this->postJson('/api/update-email', [
            'model_type' => TestUserMail::class,
            'email' => self::TEST_NEW_EMAIL,
            'code' => $otpCode,
        ], [
            'Authorization' => 'Bearer '.$plainToken,
        ]);

        $updateResponse->assertStatus(200);
        $updateResponse->assertJson([
            'message' => 'Email updated successfully. Please verify your new email.',
        ]);

        $user->refresh();
        $this->assertEquals(self::TEST_NEW_EMAIL, $user->email);
        $this->assertNull($user->email_verified_at);
    }
}
