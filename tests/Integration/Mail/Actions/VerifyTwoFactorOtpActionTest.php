<?php

// tests/Integration/Mail/Actions/VerifyTwoFactorOtpActionTest.php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Tests\Mail\Actions;

use AndyDefer\AuthenticationKit\Configs\AuthenticationKitConfig;
use AndyDefer\AuthenticationKit\Contracts\Configs\AuthenticationKitConfigInterface;
use AndyDefer\AuthenticationKit\Mail\Actions\SendTwoFactorOtpAction;
use AndyDefer\AuthenticationKit\Mail\Actions\VerifyTwoFactorOtpAction;
use AndyDefer\AuthenticationKit\Mail\Requests\SendTwoFactorOtpRequest;
use AndyDefer\AuthenticationKit\Mail\Requests\VerifyTwoFactorOtpRequest;
use AndyDefer\AuthenticationKit\Tests\IntegrationTestCase;
use AndyDefer\AuthenticationKit\Tests\Mail\Fixtures\Models\TestUserMail;
use AndyDefer\DomainStructures\Utils\StrictDataObject;
use AndyDefer\LaravelOtp\Services\OtpService;
use AndyDefer\LaravelOtp\ValueObjects\PurposeVO;
use AndyDefer\Nemesis\Contracts\Services\NemesisInterface;
use AndyDefer\Nemesis\Records\NemesisTokenRecord;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Config;

final class VerifyTwoFactorOtpActionTest extends IntegrationTestCase
{
    use DatabaseMigrations;

    private const TEST_EMAIL = 'john@example.com';

    private const TEST_PURPOSE = 'change_email';

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
            'two_factor_rate_limit' => 3,
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
                return new AuthenticationKitConfig($app['config']);
            }
        );

        $this->app['router']->middleware(['validate.mail.authenticatable', 'nemesis.token'])->post('/api/send-two-factor-otp', action_route(
            SendTwoFactorOtpRequest::class,
            SendTwoFactorOtpAction::class
        ));

        $this->app['router']->middleware(['validate.mail.authenticatable', 'nemesis.token'])->post('/api/verify-two-factor-otp', action_route(
            VerifyTwoFactorOtpRequest::class,
            VerifyTwoFactorOtpAction::class
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

    private function createTwoFactorOtp(TestUserMail $user, string $purpose = self::TEST_PURPOSE): string
    {
        $purposeVO = new PurposeVO(
            value: 'two_factor_'.$purpose,
            label: 'Two-Factor Authentication',
            ttl: 300,
            maxAttempts: 3,
        );

        $otp = $this->otpService->create($user, $purposeVO);

        return $otp->code;
    }

    // ============================================================================
    // Tests - Succès
    // ============================================================================

    public function test_verify_two_factor_otp_successfully(): void
    {
        [$user, $plainToken] = $this->createUserAndGetToken();
        $otpCode = $this->createTwoFactorOtp($user);

        $payload = [
            'model_type' => TestUserMail::class,
            'two_factor_purpose' => self::TEST_PURPOSE,
            'code' => $otpCode,
        ];

        $response = $this->postJson('/api/verify-two-factor-otp', $payload, [
            'Authorization' => 'Bearer '.$plainToken,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'message' => 'Two-factor verification successful',
            'status' => 200,
        ]);
    }

    public function test_verify_two_factor_otp_for_different_purpose_fails(): void
    {
        [$user, $plainToken] = $this->createUserAndGetToken();
        $otpCode = $this->createTwoFactorOtp($user, 'change_email');

        $payload = [
            'model_type' => TestUserMail::class,
            'two_factor_purpose' => 'change_password',
            'code' => $otpCode,
        ];

        $response = $this->postJson('/api/verify-two-factor-otp', $payload, [
            'Authorization' => 'Bearer '.$plainToken,
        ]);

        $response->assertStatus(400);
        $response->assertJson([
            'errorCode' => 'TWO_FACTOR_VERIFY_FAILED',
        ]);
    }

    // ============================================================================
    // Tests - Erreurs d'authentification
    // ============================================================================

    public function test_verify_two_factor_otp_returns_401_when_unauthenticated(): void
    {
        $payload = [
            'model_type' => TestUserMail::class,
            'two_factor_purpose' => self::TEST_PURPOSE,
            'code' => '123456',
        ];

        $response = $this->postJson('/api/verify-two-factor-otp', $payload);

        $response->assertStatus(401);
    }

    public function test_verify_two_factor_otp_returns_401_when_token_invalid(): void
    {
        $payload = [
            'model_type' => TestUserMail::class,
            'two_factor_purpose' => self::TEST_PURPOSE,
            'code' => '123456',
        ];

        $response = $this->postJson('/api/verify-two-factor-otp', $payload, [
            'Authorization' => 'Bearer invalid-token',
        ]);

        $response->assertStatus(401);
    }

    // ============================================================================
    // Tests - Erreurs de validation
    // ============================================================================

    public function test_verify_two_factor_otp_returns_400_when_model_type_is_missing(): void
    {
        [, $plainToken] = $this->createUserAndGetToken();

        $response = $this->postJson('/api/verify-two-factor-otp', [
            'two_factor_purpose' => self::TEST_PURPOSE,
            'code' => '123456',
        ], [
            'Authorization' => 'Bearer '.$plainToken,
        ]);

        $response->assertStatus(400);
        $response->assertJson([
            'errorCode' => 'MODEL_TYPE_REQUIRED',
        ]);
    }

    public function test_verify_two_factor_otp_returns_422_when_purpose_is_missing(): void
    {
        [, $plainToken] = $this->createUserAndGetToken();

        $response = $this->postJson('/api/verify-two-factor-otp', [
            'model_type' => TestUserMail::class,
            'code' => '123456',
        ], [
            'Authorization' => 'Bearer '.$plainToken,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['two_factor_purpose']);
    }

    public function test_verify_two_factor_otp_returns_422_when_code_is_missing(): void
    {
        [, $plainToken] = $this->createUserAndGetToken();

        $response = $this->postJson('/api/verify-two-factor-otp', [
            'model_type' => TestUserMail::class,
            'two_factor_purpose' => self::TEST_PURPOSE,
        ], [
            'Authorization' => 'Bearer '.$plainToken,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['code']);
    }

    public function test_verify_two_factor_otp_returns_422_when_code_too_short(): void
    {
        [, $plainToken] = $this->createUserAndGetToken();

        $response = $this->postJson('/api/verify-two-factor-otp', [
            'model_type' => TestUserMail::class,
            'two_factor_purpose' => self::TEST_PURPOSE,
            'code' => '12',
        ], [
            'Authorization' => 'Bearer '.$plainToken,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['code']);
    }

    public function test_verify_two_factor_otp_returns_422_when_code_too_long(): void
    {
        [, $plainToken] = $this->createUserAndGetToken();

        $response = $this->postJson('/api/verify-two-factor-otp', [
            'model_type' => TestUserMail::class,
            'two_factor_purpose' => self::TEST_PURPOSE,
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

    public function test_verify_two_factor_otp_returns_400_when_code_invalid(): void
    {
        [, $plainToken] = $this->createUserAndGetToken();

        $payload = [
            'model_type' => TestUserMail::class,
            'two_factor_purpose' => self::TEST_PURPOSE,
            'code' => '000000',
        ];

        $response = $this->postJson('/api/verify-two-factor-otp', $payload, [
            'Authorization' => 'Bearer '.$plainToken,
        ]);

        $response->assertStatus(400);
        $response->assertJson([
            'message' => 'Invalid or expired two-factor code',
            'errorCode' => 'TWO_FACTOR_VERIFY_FAILED',
        ]);
    }

    public function test_verify_two_factor_otp_returns_400_when_code_expired(): void
    {
        [$user, $plainToken] = $this->createUserAndGetToken();
        $otpCode = $this->createTwoFactorOtp($user);

        $purpose = new PurposeVO(
            value: 'two_factor_'.self::TEST_PURPOSE,
            label: 'Two-Factor Authentication',
            ttl: 300,
            maxAttempts: 3,
        );

        $otps = $this->otpService->getAllFor($user, $purpose);
        $otp = $otps->first();
        $otp->expires_at = now()->subSecond();
        $otp->save();

        $payload = [
            'model_type' => TestUserMail::class,
            'two_factor_purpose' => self::TEST_PURPOSE,
            'code' => $otpCode,
        ];

        $response = $this->postJson('/api/verify-two-factor-otp', $payload, [
            'Authorization' => 'Bearer '.$plainToken,
        ]);

        $response->assertStatus(400);
        $response->assertJson([
            'errorCode' => 'TWO_FACTOR_VERIFY_FAILED',
        ]);
    }

    public function test_verify_two_factor_otp_returns_400_when_code_already_used(): void
    {
        [$user, $plainToken] = $this->createUserAndGetToken();
        $otpCode = $this->createTwoFactorOtp($user);

        $payload = [
            'model_type' => TestUserMail::class,
            'two_factor_purpose' => self::TEST_PURPOSE,
            'code' => $otpCode,
        ];

        $this->postJson('/api/verify-two-factor-otp', $payload, [
            'Authorization' => 'Bearer '.$plainToken,
        ])->assertStatus(200);

        $response = $this->postJson('/api/verify-two-factor-otp', $payload, [
            'Authorization' => 'Bearer '.$plainToken,
        ]);

        $response->assertStatus(400);
        $response->assertJson([
            'errorCode' => 'TWO_FACTOR_VERIFY_FAILED',
        ]);
    }

    public function test_verify_two_factor_otp_returns_400_when_attempts_exceeded(): void
    {
        [$user, $plainToken] = $this->createUserAndGetToken();
        $otpCode = $this->createTwoFactorOtp($user);

        $purpose = new PurposeVO(
            value: 'two_factor_'.self::TEST_PURPOSE,
            label: 'Two-Factor Authentication',
            ttl: 300,
            maxAttempts: 3,
        );

        $otps = $this->otpService->getAllFor($user, $purpose);
        $otp = $otps->first();
        $otp->attempts = 3;
        $otp->save();

        $payload = [
            'model_type' => TestUserMail::class,
            'two_factor_purpose' => self::TEST_PURPOSE,
            'code' => $otpCode,
        ];

        $response = $this->postJson('/api/verify-two-factor-otp', $payload, [
            'Authorization' => 'Bearer '.$plainToken,
        ]);

        $response->assertStatus(400);
        $response->assertJson([
            'errorCode' => 'TWO_FACTOR_VERIFY_FAILED',
        ]);
    }

    // ============================================================================
    // Test d'intégration complet
    // ============================================================================

    public function test_complete_two_factor_flow(): void
    {
        [$user, $plainToken] = $this->createUserAndGetToken();

        $sendResponse = $this->postJson('/api/send-two-factor-otp', [
            'model_type' => TestUserMail::class,
            'two_factor_purpose' => self::TEST_PURPOSE,
        ], [
            'Authorization' => 'Bearer '.$plainToken,
        ]);
        $sendResponse->assertStatus(200);

        $purpose = new PurposeVO(
            value: 'two_factor_'.self::TEST_PURPOSE,
            label: 'Two-Factor Authentication',
            ttl: 300,
            maxAttempts: 3,
        );

        $otps = $this->otpService->getAllFor($user, $purpose);
        $otpCode = $otps->first()->code;

        $verifyResponse = $this->postJson('/api/verify-two-factor-otp', [
            'model_type' => TestUserMail::class,
            'two_factor_purpose' => self::TEST_PURPOSE,
            'code' => $otpCode,
        ], [
            'Authorization' => 'Bearer '.$plainToken,
        ]);

        $verifyResponse->assertStatus(200);
        $verifyResponse->assertJson([
            'message' => 'Two-factor verification successful',
        ]);
    }
}
