<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Tests\Mail\Actions;

use AndyDefer\AuthenticationKit\Mail\Actions\ResetPasswordAction;
use AndyDefer\AuthenticationKit\Mail\Requests\ResetPasswordRequest;
use AndyDefer\AuthenticationKit\Tests\IntegrationTestCase;
use AndyDefer\AuthenticationKit\Tests\Mail\Fixtures\Models\TestUserMail;
use AndyDefer\LaravelOtp\Services\OtpService;
use AndyDefer\LaravelOtp\ValueObjects\PurposeVO;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;

final class ResetPasswordActionTest extends IntegrationTestCase
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
            'driver' => 'mail',
            'default_from' => 'test@example.com',
            'default_from_name' => 'Test App',
        ]);

        $this->app['router']->post('/api/reset-password', action_route(
            ResetPasswordRequest::class,
            ResetPasswordAction::class
        ));

        $this->otpService = $this->app->make(OtpService::class);
    }

    private function createUser(array $overrides = []): TestUserMail
    {
        $defaults = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => bcrypt('Password123!'),
            'email_verified_at' => now(),
        ];

        $data = array_merge($defaults, $overrides);

        return TestUserMail::create($data);
    }

    private function createPasswordResetOtp(TestUserMail $user): string
    {
        $purpose = new PurposeVO(
            value: 'password_reset',
            label: 'Password Reset',
            ttl: 600,
            maxAttempts: 3
        );

        $otp = $this->otpService->create($user, $purpose);

        return $otp->code;
    }

    // ============================================================================
    // Tests - Succès
    // ============================================================================

    public function test_reset_password_successfully_with_valid_otp(): void
    {
        $user = $this->createUser();
        $otpCode = $this->createPasswordResetOtp($user);

        $payload = [
            'model_type' => TestUserMail::class,
            'email' => $user->email,
            'token' => $otpCode,
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ];

        $response = $this->postJson('/api/reset-password', $payload);

        $response->assertStatus(200);
        $response->assertJson([
            'message' => 'Password reset successfully',
            'email' => $user->email,
        ]);

        $user->refresh();
        $this->assertTrue(Hash::check('NewPassword123!', $user->password));

        $purpose = new PurposeVO(
            value: 'password_reset',
            label: 'Password Reset',
            ttl: 600,
            maxAttempts: 3
        );

        $otps = $this->otpService->getAllFor($user, $purpose);
        $this->assertCount(1, $otps);

        $otp = $otps->first();
        $this->assertTrue($otp->isUsed());
    }

    // ============================================================================
    // Tests - Erreurs de validation (422)
    // ============================================================================

    public function test_reset_password_returns_422_when_model_type_is_missing(): void
    {
        $payload = [
            'email' => 'john@example.com',
            'token' => '123456',
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ];

        $response = $this->postJson('/api/reset-password', $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['model_type']);
    }

    public function test_reset_password_returns_422_when_email_is_missing(): void
    {
        $payload = [
            'model_type' => TestUserMail::class,
            'token' => '123456',
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ];

        $response = $this->postJson('/api/reset-password', $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);
    }

    public function test_reset_password_returns_422_when_token_is_missing(): void
    {
        $payload = [
            'model_type' => TestUserMail::class,
            'email' => 'john@example.com',
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ];

        $response = $this->postJson('/api/reset-password', $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['token']);
    }

    public function test_reset_password_returns_422_when_password_is_missing(): void
    {
        $payload = [
            'model_type' => TestUserMail::class,
            'email' => 'john@example.com',
            'token' => '123456',
            'password_confirmation' => 'NewPassword123!',
        ];

        $response = $this->postJson('/api/reset-password', $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['password']);
    }

    public function test_reset_password_returns_422_when_password_confirmation_is_missing(): void
    {
        $payload = [
            'model_type' => TestUserMail::class,
            'email' => 'john@example.com',
            'token' => '123456',
            'password' => 'NewPassword123!',
        ];

        $response = $this->postJson('/api/reset-password', $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['password_confirmation']);
    }

    public function test_reset_password_returns_422_when_email_invalid_format(): void
    {
        $payload = [
            'model_type' => TestUserMail::class,
            'email' => 'invalid-email',
            'token' => '123456',
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ];

        $response = $this->postJson('/api/reset-password', $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);
    }

    public function test_reset_password_returns_422_when_password_too_short(): void
    {
        $user = $this->createUser();
        $otpCode = $this->createPasswordResetOtp($user);

        $payload = [
            'model_type' => TestUserMail::class,
            'email' => $user->email,
            'token' => $otpCode,
            'password' => '1234567',
            'password_confirmation' => '1234567',
        ];

        $response = $this->postJson('/api/reset-password', $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['password']);
    }

    public function test_reset_password_returns_422_when_passwords_do_not_match(): void
    {
        $user = $this->createUser();
        $otpCode = $this->createPasswordResetOtp($user);

        $payload = [
            'model_type' => TestUserMail::class,
            'email' => $user->email,
            'token' => $otpCode,
            'password' => 'NewPassword123!',
            'password_confirmation' => 'DifferentPassword123!',
        ];

        $response = $this->postJson('/api/reset-password', $payload);

        // ✅ Validation Laravel → 422 avec message d'erreur
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['password']);
        $response->assertJson([
            'message' => 'Password confirmation does not match',
        ]);
    }

    // ============================================================================
    // Tests - Erreurs OTP (toutes en 422 car ValidOtpRule échoue)
    // ============================================================================

    public function test_reset_password_returns_422_when_otp_invalid(): void
    {
        $user = $this->createUser();

        $payload = [
            'model_type' => TestUserMail::class,
            'email' => $user->email,
            'token' => '000000',
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ];

        $response = $this->postJson('/api/reset-password', $payload);

        // ✅ La règle ValidOtpRule échoue → 422
        $response->assertStatus(422);
        $response->assertJson([
            'message' => 'Invalid or expired verification code.',
        ]);
        $response->assertJsonValidationErrors(['token']);
    }

    public function test_reset_password_returns_422_when_otp_expired(): void
    {
        $user = $this->createUser();
        $otpCode = $this->createPasswordResetOtp($user);

        $purpose = new PurposeVO(
            value: 'password_reset',
            label: 'Password Reset',
            ttl: 600,
            maxAttempts: 3
        );

        $otps = $this->otpService->getAllFor($user, $purpose);
        $otp = $otps->first();
        $otp->expires_at = now()->subSecond();
        $otp->save();

        $payload = [
            'model_type' => TestUserMail::class,
            'email' => $user->email,
            'token' => $otpCode,
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ];

        $response = $this->postJson('/api/reset-password', $payload);

        // ✅ OTP expiré → ValidOtpRule échoue → 422
        $response->assertStatus(422);
        $response->assertJson([
            'message' => 'Invalid or expired verification code.',
        ]);
        $response->assertJsonValidationErrors(['token']);
    }

    public function test_reset_password_returns_422_when_otp_already_used(): void
    {
        $user = $this->createUser();
        $otpCode = $this->createPasswordResetOtp($user);

        $payload = [
            'model_type' => TestUserMail::class,
            'email' => $user->email,
            'token' => $otpCode,
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ];

        $response1 = $this->postJson('/api/reset-password', $payload);
        $response1->assertStatus(200);

        $response2 = $this->postJson('/api/reset-password', $payload);

        // ✅ OTP déjà utilisé → ValidOtpRule échoue → 422
        $response2->assertStatus(422);
        $response2->assertJson([
            'message' => 'Invalid or expired verification code.',
        ]);
        $response2->assertJsonValidationErrors(['token']);
    }

    public function test_reset_password_returns_422_when_user_not_found(): void
    {
        $payload = [
            'model_type' => TestUserMail::class,
            'email' => 'nonexistent@example.com',
            'token' => '123456',
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ];

        $response = $this->postJson('/api/reset-password', $payload);

        // ✅ User not found → ValidOtpRule échoue → 422
        $response->assertStatus(422);
        $response->assertJson([
            'message' => 'No user found with this email address.',
        ]);
        $response->assertJsonValidationErrors(['token']);
    }

    public function test_reset_password_returns_422_when_otp_wrong_purpose(): void
    {
        $user = $this->createUser();

        $purpose = new PurposeVO(
            value: 'email_verification',
            label: 'Email Verification',
            ttl: 300,
            maxAttempts: 3
        );

        $otp = $this->otpService->create($user, $purpose);

        $payload = [
            'model_type' => TestUserMail::class,
            'email' => $user->email,
            'token' => $otp->code,
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ];

        $response = $this->postJson('/api/reset-password', $payload);

        // ✅ Mauvais purpose → ValidOtpRule échoue → 422
        $response->assertStatus(422);
        $response->assertJson([
            'message' => 'Invalid or expired verification code.',
        ]);
        $response->assertJsonValidationErrors(['token']);
    }

    // ============================================================================
    // Tests - Erreurs 500
    // ============================================================================

    public function test_reset_password_returns_422_when_model_type_invalid(): void
    {
        $payload = [
            'model_type' => 'InvalidModel',
            'email' => 'john@example.com',
            'token' => '123456',
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ];

        $response = $this->postJson('/api/reset-password', $payload);

        // ✅ Modèle invalide → ValidOtpRule échoue → 422
        $response->assertStatus(422);
        $response->assertJson([
            'message' => 'The specified model type does not exist.',
        ]);
        $response->assertJsonValidationErrors(['token']);
    }

    // ============================================================================
    // Tests - Cas limites
    // ============================================================================

    public function test_reset_password_with_uppercase_email(): void
    {
        $user = $this->createUser();
        $otpCode = $this->createPasswordResetOtp($user);

        $payload = [
            'model_type' => TestUserMail::class,
            'email' => strtoupper($user->email),
            'token' => $otpCode,
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ];

        $response = $this->postJson('/api/reset-password', $payload);

        $response->assertStatus(200);
        $response->assertJson([
            'message' => 'Password reset successfully',
            'email' => strtoupper($user->email),
        ]);

        $user->refresh();
        $this->assertTrue(Hash::check('NewPassword123!', $user->password));
    }

    public function test_reset_password_with_whitespace_in_email(): void
    {
        $user = $this->createUser();
        $otpCode = $this->createPasswordResetOtp($user);

        $payload = [
            'model_type' => TestUserMail::class,
            'email' => '  '.$user->email.'  ',
            'token' => $otpCode,
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ];

        $response = $this->postJson('/api/reset-password', $payload);

        $response->assertStatus(200);
        $response->assertJson([
            'message' => 'Password reset successfully',
            'email' => trim($user->email),
        ]);

        $user->refresh();
        $this->assertTrue(Hash::check('NewPassword123!', $user->password));
    }

    public function test_reset_password_uses_otp_once(): void
    {
        $user = $this->createUser();
        $otpCode = $this->createPasswordResetOtp($user);

        $payload = [
            'model_type' => TestUserMail::class,
            'email' => $user->email,
            'token' => $otpCode,
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ];

        $response1 = $this->postJson('/api/reset-password', $payload);
        $response1->assertStatus(200);

        $response2 = $this->postJson('/api/reset-password', $payload);

        // ✅ OTP déjà utilisé → 422
        $response2->assertStatus(422);
        $response2->assertJson([
            'message' => 'Invalid or expired verification code.',
        ]);
        $response2->assertJsonValidationErrors(['token']);

        $user->refresh();
        $this->assertTrue(Hash::check('NewPassword123!', $user->password));
    }

    // ============================================================================
    // Test d'intégration complet
    // ============================================================================

    public function test_complete_password_reset_flow(): void
    {
        $user = $this->createUser();

        $this->app['router']->post('/api/forgot-password', function () use ($user) {
            $purpose = new PurposeVO(
                value: 'password_reset',
                label: 'Password Reset',
                ttl: 600,
                maxAttempts: 3
            );
            $this->otpService->create($user, $purpose);

            return response()->json(['message' => 'OTP sent']);
        });

        $forgotResponse = $this->postJson('/api/forgot-password', ['email' => $user->email]);
        $forgotResponse->assertStatus(200);

        $purpose = new PurposeVO(
            value: 'password_reset',
            label: 'Password Reset',
            ttl: 600,
            maxAttempts: 3
        );
        $otps = $this->otpService->getAllFor($user, $purpose);
        $otpCode = $otps->first()->code;

        $payload = [
            'model_type' => TestUserMail::class,
            'email' => $user->email,
            'token' => $otpCode,
            'password' => 'NewSecurePassword123!',
            'password_confirmation' => 'NewSecurePassword123!',
        ];

        $resetResponse = $this->postJson('/api/reset-password', $payload);
        $resetResponse->assertStatus(200);
        $resetResponse->assertJson([
            'message' => 'Password reset successfully',
            'email' => $user->email,
        ]);

        $user->refresh();
        $this->assertTrue(Hash::check('NewSecurePassword123!', $user->password));

        $otps = $this->otpService->getAllFor($user, $purpose);
        $this->assertTrue($otps->first()->isUsed());
    }
}
