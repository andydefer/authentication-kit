<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Tests\Mail\Actions;

use AndyDefer\AuthenticationKit\Mail\Actions\SendEmailVerificationAction;
use AndyDefer\AuthenticationKit\Mail\Requests\SendEmailVerificationRequest;
use AndyDefer\AuthenticationKit\Tests\IntegrationTestCase;
use AndyDefer\AuthenticationKit\Tests\Mail\Fixtures\Models\TestUserMail;
use AndyDefer\LaravelOtp\Services\OtpService;
use AndyDefer\LaravelOtp\ValueObjects\PurposeVO;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Config;

final class SendEmailVerificationActionTest extends IntegrationTestCase
{
    use DatabaseMigrations;

    private OtpService $otpService;

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
            'default_from' => 'test@example.com',
            'default_from_name' => 'Test App',
        ]);

        $this->app['router']->post('/api/email/verification', action_route(
            SendEmailVerificationRequest::class,
            SendEmailVerificationAction::class
        ));

        $this->otpService = $this->app->make(OtpService::class);
    }

    private function createUser(array $overrides = []): TestUserMail
    {
        $defaults = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => bcrypt('Password123!'),
            'email_verified_at' => null,
        ];

        $data = array_merge($defaults, $overrides);

        return TestUserMail::create($data);
    }

    public function test_send_email_verification_successfully_sends_otp(): void
    {

        // ✅ Utiliser le vrai service
        $user = $this->createUser();

        $payload = [
            'model_type' => TestUserMail::class,
            'auth_id' => $user->id,
        ];

        $response = $this->postJson('/api/email/verification', $payload);

        $response->assertStatus(200);
        $response->assertJson([
            'message' => 'Verification OTP sent successfully',
            'email' => $user->email,
            'alreadyVerified' => false,
        ]);

        // ✅ Vérifier qu'un OTP a été créé
        $purpose = new PurposeVO(
            value: 'email_verification',
            label: 'Email Verification',
            ttl: 300,
            maxAttempts: 3
        );

        $otps = $this->otpService->getAllFor($user, $purpose);

        $this->assertCount(1, $otps);
    }

    public function test_send_email_verification_returns_200_when_already_verified(): void
    {

        $user = $this->createUser([
            'email_verified_at' => now(),
        ]);

        $payload = [
            'model_type' => TestUserMail::class,
            'auth_id' => $user->id,
        ];

        $response = $this->postJson('/api/email/verification', $payload);

        $response->assertStatus(200);
        $response->assertJson([
            'message' => 'Email already verified',
            'email' => $user->email,
            'alreadyVerified' => true,
        ]);

    }

    // ============================================================================
    // Tests - Erreurs de validation (422)
    // ============================================================================

    public function test_send_email_verification_returns_422_when_model_type_is_missing(): void
    {
        $payload = [
            'auth_id' => 1,
        ];

        $response = $this->postJson('/api/email/verification', $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['model_type']);
    }

    public function test_send_email_verification_returns_422_when_auth_id_is_missing(): void
    {
        $payload = [
            'model_type' => TestUserMail::class,
        ];

        $response = $this->postJson('/api/email/verification', $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['auth_id']);
    }

    public function test_send_email_verification_returns_422_when_auth_id_is_not_integer(): void
    {
        $payload = [
            'model_type' => TestUserMail::class,
            'auth_id' => 'not-an-integer',
        ];

        $response = $this->postJson('/api/email/verification', $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['auth_id']);
    }

    // ============================================================================
    // Tests - Erreurs métier
    // ============================================================================

    public function test_send_email_verification_returns_500_when_model_class_does_not_exist(): void
    {
        $payload = [
            'model_type' => 'NonExistentClass',
            'auth_id' => 1,
        ];

        $response = $this->postJson('/api/email/verification', $payload);

        $response->assertStatus(500);
        $response->assertJson([
            'errorCode' => 'MODEL_NOT_FOUND',
            'message' => 'Model NonExistentClass does not exist',
        ]);
    }

    public function test_send_email_verification_returns_404_when_authenticatable_not_found(): void
    {
        $payload = [
            'model_type' => TestUserMail::class,
            'auth_id' => 99999,
        ];

        $response = $this->postJson('/api/email/verification', $payload);

        $response->assertStatus(404);
        $response->assertJson([
            'errorCode' => 'AUTHENTICATABLE_NOT_FOUND',
            'message' => 'Authenticatable not found',
        ]);
    }

    public function test_send_email_verification_with_soft_deleted_user(): void
    {
        $user = $this->createUser();
        $user->delete();

        $payload = [
            'model_type' => TestUserMail::class,
            'auth_id' => $user->id,
        ];

        $response = $this->postJson('/api/email/verification', $payload);

        $response->assertStatus(404);
        $response->assertJson([
            'errorCode' => 'AUTHENTICATABLE_NOT_FOUND',
            'message' => 'Authenticatable not found',
        ]);
    }

    // ============================================================================
    // Tests - Logs
    // ============================================================================

    public function test_send_email_verification_logs_successful_send(): void
    {

        $user = $this->createUser();

        $payload = [
            'model_type' => TestUserMail::class,
            'auth_id' => $user->id,
        ];

        $response = $this->postJson('/api/email/verification', $payload);

        $response->assertStatus(200);
    }
}
