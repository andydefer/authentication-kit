<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Tests\Mail\Actions;

use AndyDefer\AuthenticationKit\Mail\Actions\VerifyEmailAction;
use AndyDefer\AuthenticationKit\Mail\Contracts\Repositories\LogRepositoryInterface;
use AndyDefer\AuthenticationKit\Mail\Requests\VerifyEmailRequest;
use AndyDefer\AuthenticationKit\Tests\IntegrationTestCase;
use AndyDefer\AuthenticationKit\Tests\Mail\Fixtures\Models\TestUserMail;
use AndyDefer\LaravelOtp\Services\OtpService;
use AndyDefer\LaravelOtp\ValueObjects\PurposeVO;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Mockery;

final class VerifyEmailActionTest extends IntegrationTestCase
{
    use DatabaseMigrations;

    private const EMAIL_VERIFICATION_PURPOSE = 'email_verification';

    private OtpService $otpService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app['router']->post('/api/verify-email', action_route(
            VerifyEmailRequest::class,
            VerifyEmailAction::class
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

    private function createUserWithVerificationOtp(): array
    {
        $user = $this->createUser();

        $purpose = new PurposeVO(
            value: self::EMAIL_VERIFICATION_PURPOSE,
            label: 'Email Verification',
            ttl: 300,
            maxAttempts: 3
        );

        $otpModel = $this->otpService->create($user, $purpose);

        return [$user, $otpModel->code];
    }

    // ============================================================================
    // Tests - Succès
    // ============================================================================

    public function test_verify_email_successfully_verifies_email(): void
    {
        [$user, $code] = $this->createUserWithVerificationOtp();

        $payload = [
            'model_type' => TestUserMail::class,
            'email' => $user->email,
            'token' => $code,
        ];

        $response = $this->postJson('/api/verify-email', $payload);

        $response->assertStatus(200);
        $response->assertJson([
            'message' => 'Email verified successfully',
            'email' => $user->email,
            'alreadyVerified' => false,
        ]);

        $user->refresh();
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_verify_email_returns_200_when_already_verified(): void
    {
        $user = $this->createUser([
            'email_verified_at' => now(),
        ]);

        $purpose = new PurposeVO(
            value: self::EMAIL_VERIFICATION_PURPOSE,
            label: 'Email Verification',
            ttl: 300,
            maxAttempts: 3
        );

        $otpModel = $this->otpService->create($user, $purpose);

        $payload = [
            'model_type' => TestUserMail::class,
            'email' => $user->email,
            'token' => $otpModel->code,
        ];

        $response = $this->postJson('/api/verify-email', $payload);

        $response->assertStatus(200);
        $response->assertJson([
            'message' => 'Email already verified',
            'email' => $user->email,
            'alreadyVerified' => true,
        ]);

        $user->refresh();
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_verify_email_logs_successful_verification(): void
    {
        $logRepository = Mockery::mock(LogRepositoryInterface::class);
        $this->app->instance(LogRepositoryInterface::class, $logRepository);

        $logRepository->shouldReceive('logVerificationSuccess')
            ->once()
            ->with(
                'john@example.com',
                TestUserMail::class,
                false
            );

        [$user, $code] = $this->createUserWithVerificationOtp();

        $payload = [
            'model_type' => TestUserMail::class,
            'email' => $user->email,
            'token' => $code,
        ];

        $response = $this->postJson('/api/verify-email', $payload);

        $response->assertStatus(200);
    }

    public function test_verify_email_logs_already_verified(): void
    {
        $logRepository = Mockery::mock(LogRepositoryInterface::class);
        $this->app->instance(LogRepositoryInterface::class, $logRepository);

        $logRepository->shouldReceive('logVerificationSuccess')
            ->once()
            ->with(
                'john@example.com',
                TestUserMail::class,
                true
            );

        $user = $this->createUser([
            'email_verified_at' => now(),
        ]);

        $purpose = new PurposeVO(
            value: self::EMAIL_VERIFICATION_PURPOSE,
            label: 'Email Verification',
            ttl: 300,
            maxAttempts: 3
        );

        $otpModel = $this->otpService->create($user, $purpose);

        $payload = [
            'model_type' => TestUserMail::class,
            'email' => $user->email,
            'token' => $otpModel->code,
        ];

        $response = $this->postJson('/api/verify-email', $payload);

        $response->assertStatus(200);
    }

    // ============================================================================
    // Tests - Erreurs de validation (422)
    // ============================================================================

    public function test_verify_email_returns_422_when_email_is_missing(): void
    {
        $payload = [
            'model_type' => TestUserMail::class,
            'token' => 'some-token',
        ];

        $response = $this->postJson('/api/verify-email', $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);
    }

    public function test_verify_email_returns_422_when_token_is_missing(): void
    {
        $payload = [
            'model_type' => TestUserMail::class,
            'email' => 'john@example.com',
        ];

        $response = $this->postJson('/api/verify-email', $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['token']);
    }

    public function test_verify_email_returns_422_when_model_type_is_missing(): void
    {
        $payload = [
            'email' => 'john@example.com',
            'token' => 'some-token',
        ];

        $response = $this->postJson('/api/verify-email', $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['model_type']);
    }

    public function test_verify_email_returns_422_when_email_invalid_format(): void
    {
        $payload = [
            'model_type' => TestUserMail::class,
            'email' => 'invalid-email',
            'token' => 'some-token',
        ];

        $response = $this->postJson('/api/verify-email', $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);
    }

    // ============================================================================
    // Tests - Erreurs de validation OTP (Toujours 422)
    // ============================================================================

    public function test_verify_email_returns_422_when_token_invalid(): void
    {
        $user = $this->createUser();

        $payload = [
            'model_type' => TestUserMail::class,
            'email' => $user->email,
            'token' => 'invalid-token-1234567890',
        ];

        $response = $this->postJson('/api/verify-email', $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['token']);
        $response->assertJson([
            'message' => 'Invalid or expired verification code.',
            'errors' => [
                'token' => ['Invalid or expired verification code.'],
            ],
        ]);
    }

    public function test_verify_email_returns_422_when_token_expired(): void
    {
        $user = $this->createUser();

        $purpose = new PurposeVO(
            value: self::EMAIL_VERIFICATION_PURPOSE,
            label: 'Email Verification',
            ttl: 300,
            maxAttempts: 3
        );

        $otpModel = $this->otpService->create($user, $purpose);

        // ✅ Expirer le token
        $otpModel->expires_at = now()->subDay();
        $otpModel->save();

        $payload = [
            'model_type' => TestUserMail::class,
            'email' => $user->email,
            'token' => $otpModel->code,
        ];

        $response = $this->postJson('/api/verify-email', $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['token']);
        $response->assertJson([
            'message' => 'Invalid or expired verification code.',
            'errors' => [
                'token' => ['Invalid or expired verification code.'],
            ],
        ]);
    }

    public function test_verify_email_returns_422_when_token_revoked(): void
    {
        $user = $this->createUser();

        $purpose = new PurposeVO(
            value: self::EMAIL_VERIFICATION_PURPOSE,
            label: 'Email Verification',
            ttl: 300,
            maxAttempts: 3
        );

        $otpModel = $this->otpService->create($user, $purpose);

        // ✅ Révoquer le token (marquer comme utilisé)
        $this->otpService->verify(
            identifier: $user,
            code: $otpModel->code,
            purpose: $purpose,
            markAsUsed: true
        );

        $payload = [
            'model_type' => TestUserMail::class,
            'email' => $user->email,
            'token' => $otpModel->code,
        ];

        $response = $this->postJson('/api/verify-email', $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['token']);
        $response->assertJson([
            'message' => 'Invalid or expired verification code.',
            'errors' => [
                'token' => ['Invalid or expired verification code.'],
            ],
        ]);
    }

    // ============================================================================
    // Tests - Erreurs utilisateur (Retourne 422 car la règle bloque)
    // ============================================================================

    public function test_verify_email_returns_422_when_user_not_found(): void
    {
        [$user, $code] = $this->createUserWithVerificationOtp();

        // ✅ Supprimer l'utilisateur
        $user->delete();

        $payload = [
            'model_type' => TestUserMail::class,
            'email' => $user->email,
            'token' => $code,
        ];

        $response = $this->postJson('/api/verify-email', $payload);

        // ✅ La règle de validation ne trouve pas l'utilisateur → 422
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['token']);
    }

    public function test_verify_email_returns_422_when_model_type_does_not_exist(): void
    {
        [$user, $code] = $this->createUserWithVerificationOtp();

        $payload = [
            'model_type' => 'NonExistentClass',
            'email' => $user->email,
            'token' => $code,
        ];

        $response = $this->postJson('/api/verify-email', $payload);

        // ✅ La règle de validation ne trouve pas le modèle → 422
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['token']);
    }

    public function test_verify_email_returns_422_when_model_type_does_not_implement_mail_authenticatable(): void
    {
        [$user, $code] = $this->createUserWithVerificationOtp();

        $payload = [
            'model_type' => Application::class,
            'email' => $user->email,
            'token' => $code,
        ];

        $response = $this->postJson('/api/verify-email', $payload);

        // ✅ La règle de validation vérifie l'interface → 422
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['token']);
    }

    // ============================================================================
    // Tests - Logs d'erreur
    // ============================================================================

    // ============================================================================
    // Tests - Cas limites
    // ============================================================================

    public function test_verify_email_with_uppercase_email(): void
    {
        [$user, $code] = $this->createUserWithVerificationOtp();

        $payload = [
            'model_type' => TestUserMail::class,
            'email' => strtoupper($user->email),  // ✅ Cas réel : "JOHN@EXAMPLE.COM"
            'token' => $code,
        ];

        $response = $this->postJson('/api/verify-email', $payload);

        $response->assertStatus(200);
        $response->assertJson([
            'message' => 'Email verified successfully',
            'email' => strtoupper($user->email),  // ✅ Retourne "JOHN@EXAMPLE.COM"
            'alreadyVerified' => false,
        ]);

        $user->refresh();
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_verify_email_when_user_deleted_soft_delete(): void
    {
        [$user, $code] = $this->createUserWithVerificationOtp();

        // ✅ Soft delete l'utilisateur
        $user->delete();

        $payload = [
            'model_type' => TestUserMail::class,
            'email' => $user->email,
            'token' => $code,
        ];

        $response = $this->postJson('/api/verify-email', $payload);

        // ✅ La règle de validation ne trouve pas l'utilisateur → 422
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['token']);
    }

    // ============================================================================
    // Tests - Intégration
    // ============================================================================

    public function test_verify_email_cannot_reuse_otp(): void
    {
        [$user, $code] = $this->createUserWithVerificationOtp();

        $payload = [
            'model_type' => TestUserMail::class,
            'email' => $user->email,
            'token' => $code,
        ];

        // ✅ Première tentative - succès
        $response1 = $this->postJson('/api/verify-email', $payload);
        $response1->assertStatus(200);

        // ✅ Deuxième tentative - échec (OTP déjà utilisé)
        // ✅ La règle de validation retourne 422
        $response2 = $this->postJson('/api/verify-email', $payload);
        $response2->assertStatus(422);
        $response2->assertJsonValidationErrors(['token']);
    }
}
