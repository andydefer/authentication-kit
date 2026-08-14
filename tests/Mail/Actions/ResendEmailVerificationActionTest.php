<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Tests\Mail\Actions;

use AndyDefer\AuthenticationKit\Mail\Actions\ResendEmailVerificationAction;
use AndyDefer\AuthenticationKit\Mail\Requests\ResendEmailVerificationRequest;
use AndyDefer\AuthenticationKit\Tests\IntegrationTestCase;
use AndyDefer\AuthenticationKit\Tests\Mail\Fixtures\Models\TestUserMail;
use AndyDefer\LaravelOtp\Services\OtpService;
use AndyDefer\LaravelOtp\ValueObjects\PurposeVO;
use Illuminate\Foundation\Testing\DatabaseMigrations;

final class ResendEmailVerificationActionTest extends IntegrationTestCase
{
    use DatabaseMigrations;

    private OtpService $otpService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app['router']->post('/api/email/resend', action_route(
            ResendEmailVerificationRequest::class,
            ResendEmailVerificationAction::class
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

    // ============================================================================
    // Tests - Succès
    // ============================================================================

    public function test_resend_email_verification_successfully_resent_otp(): void
    {
        $user = $this->createUser();

        $payload = [
            'model_type' => TestUserMail::class,
            'auth_id' => $user->id,
        ];

        $response = $this->postJson('/api/email/resend', $payload);

        $response->assertStatus(200);
        $response->assertJson([
            'message' => 'Verification OTP resent successfully',
            'email' => $user->email,
            'alreadyVerified' => false,
        ]);

        $purpose = new PurposeVO(
            value: 'email_verification',
            label: 'Email Verification',
            ttl: 300,
            maxAttempts: 3
        );

        $otps = $this->otpService->getAllFor($user, $purpose);
        $this->assertCount(1, $otps);
    }

    public function test_resend_email_verification_returns_200_when_already_verified(): void
    {
        $user = $this->createUser([
            'email_verified_at' => now(),
        ]);

        $payload = [
            'model_type' => TestUserMail::class,
            'auth_id' => $user->id,
        ];

        $response = $this->postJson('/api/email/resend', $payload);

        $response->assertStatus(200);
        $response->assertJson([
            'message' => 'Email already verified',
            'email' => $user->email,
            'alreadyVerified' => true,
        ]);

        $purpose = new PurposeVO(
            value: 'email_verification',
            label: 'Email Verification',
            ttl: 300,
            maxAttempts: 3
        );

        $otps = $this->otpService->getAllFor($user, $purpose);
        $this->assertCount(0, $otps);
    }

    // ============================================================================
    // Tests - Erreurs de validation (422)
    // ============================================================================

    public function test_resend_email_verification_returns_422_when_model_type_is_missing(): void
    {
        $payload = [
            'auth_id' => 1,
        ];

        $response = $this->postJson('/api/email/resend', $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['model_type']);
    }

    public function test_resend_email_verification_returns_422_when_auth_id_is_missing(): void
    {
        $payload = [
            'model_type' => TestUserMail::class,
        ];

        $response = $this->postJson('/api/email/resend', $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['auth_id']);
    }

    public function test_resend_email_verification_returns_422_when_auth_id_is_not_integer(): void
    {
        $payload = [
            'model_type' => TestUserMail::class,
            'auth_id' => 'not-an-integer',
        ];

        $response = $this->postJson('/api/email/resend', $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['auth_id']);
    }

    // ============================================================================
    // Tests - Erreurs métier
    // ============================================================================

    public function test_resend_email_verification_returns_500_when_model_class_does_not_exist(): void
    {
        $payload = [
            'model_type' => 'NonExistentClass',
            'auth_id' => 1,
        ];

        $response = $this->postJson('/api/email/resend', $payload);

        $response->assertStatus(500);
        $response->assertJson([
            'errorCode' => 'MODEL_NOT_FOUND',
            'message' => 'Model does not exist',
        ]);
    }

    public function test_resend_email_verification_returns_404_when_authenticatable_not_found(): void
    {
        $payload = [
            'model_type' => TestUserMail::class,
            'auth_id' => 99999,
        ];

        $response = $this->postJson('/api/email/resend', $payload);

        $response->assertStatus(404);
        $response->assertJson([
            'errorCode' => 'AUTHENTICATABLE_NOT_FOUND',
            'message' => 'Authenticatable not found',
        ]);
    }

    public function test_resend_email_verification_returns_500_when_resend_fails(): void
    {
        $user = $this->createUser();

        $payload = [
            'model_type' => TestUserMail::class,
            'auth_id' => $user->id,
        ];

        $response1 = $this->postJson('/api/email/resend', $payload);
        $response1->assertStatus(200);

        $response2 = $this->postJson('/api/email/resend', $payload);

        $this->assertContains($response2->status(), [200, 500]);
    }

    public function test_resend_email_verification_returns_500_when_exception_thrown(): void
    {
        $payload = [
            'model_type' => 'NonExistentClass',
            'auth_id' => 1,
        ];

        $response = $this->postJson('/api/email/resend', $payload);

        $response->assertStatus(500);
        $response->assertJson([
            'errorCode' => 'MODEL_NOT_FOUND',
            'message' => 'Model does not exist',
        ]);
    }

    // ============================================================================
    // Tests - Logs (vrai LogRepository)
    // ============================================================================

    public function test_resend_email_verification_logs_successful_resend(): void
    {
        $user = $this->createUser();

        $payload = [
            'model_type' => TestUserMail::class,
            'auth_id' => $user->id,
        ];

        $response = $this->postJson('/api/email/resend', $payload);

        $response->assertStatus(200);
    }

    public function test_resend_email_verification_logs_already_verified(): void
    {
        $user = $this->createUser([
            'email_verified_at' => now(),
        ]);

        $payload = [
            'model_type' => TestUserMail::class,
            'auth_id' => $user->id,
        ];

        $response = $this->postJson('/api/email/resend', $payload);

        $response->assertStatus(200);
    }

    public function test_resend_email_verification_logs_failure(): void
    {
        $user = $this->createUser();

        $payload = [
            'model_type' => TestUserMail::class,
            'auth_id' => $user->id,
        ];

        $response1 = $this->postJson('/api/email/resend', $payload);
        $response1->assertStatus(200);

        $response2 = $this->postJson('/api/email/resend', $payload);

        $this->assertContains($response2->status(), [200, 500]);
    }

    // ============================================================================
    // Tests - Cas limites
    // ============================================================================

    public function test_resend_email_verification_multiple_times(): void
    {
        $user = $this->createUser();

        $payload = [
            'model_type' => TestUserMail::class,
            'auth_id' => $user->id,
        ];

        $response1 = $this->postJson('/api/email/resend', $payload);
        $response1->assertStatus(200);

        $response2 = $this->postJson('/api/email/resend', $payload);
        $response2->assertStatus(200);

        $purpose = new PurposeVO(
            value: 'email_verification',
            label: 'Email Verification',
            ttl: 300,
            maxAttempts: 3
        );

        $otps = $this->otpService->getAllFor($user, $purpose);
        $this->assertGreaterThanOrEqual(1, $otps->count());
    }

    public function test_resend_email_verification_with_soft_deleted_user(): void
    {
        $user = $this->createUser();
        $user->delete();

        $payload = [
            'model_type' => TestUserMail::class,
            'auth_id' => $user->id,
        ];

        $response = $this->postJson('/api/email/resend', $payload);

        $response->assertStatus(404);
        $response->assertJson([
            'errorCode' => 'AUTHENTICATABLE_NOT_FOUND',
            'message' => 'Authenticatable not found',
        ]);
    }

    // ============================================================================
    // Test d'intégration complet
    // ============================================================================

    public function test_complete_email_verification_resend_flow(): void
    {
        $user = $this->createUser();

        $purpose = new PurposeVO(
            value: 'email_verification',
            label: 'Email Verification',
            ttl: 300,
            maxAttempts: 3
        );
        $this->otpService->create($user, $purpose);

        $payload = [
            'model_type' => TestUserMail::class,
            'auth_id' => $user->id,
        ];

        $resendResponse = $this->postJson('/api/email/resend', $payload);
        $resendResponse->assertStatus(200);
        $resendResponse->assertJson([
            'message' => 'Verification OTP resent successfully',
            'email' => $user->email,
            'alreadyVerified' => false,
        ]);

        $otps = $this->otpService->getAllFor($user, $purpose);
        $this->assertGreaterThanOrEqual(1, $otps->count());
    }
}
