<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Tests\Mail\Actions;

use AndyDefer\AuthenticationKit\Mail\Actions\SendPasswordResetLinkAction;
use AndyDefer\AuthenticationKit\Mail\Requests\SendPasswordResetLinkRequest;
use AndyDefer\AuthenticationKit\Tests\IntegrationTestCase;
use AndyDefer\AuthenticationKit\Tests\Mail\Fixtures\Models\TestUserMail;
use AndyDefer\LaravelOtp\Services\OtpService;
use AndyDefer\LaravelOtp\ValueObjects\PurposeVO;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Config;

final class SendPasswordResetLinkActionTest extends IntegrationTestCase
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

        $this->app['router']->post('/api/password/forgot', action_route(
            SendPasswordResetLinkRequest::class,
            SendPasswordResetLinkAction::class
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

    // ============================================================================
    // Tests - Succès
    // ============================================================================

    public function test_send_password_reset_link_successfully_sends_otp(): void
    {
        $user = $this->createUser();

        $payload = [
            'email' => $user->email,
        ];

        $response = $this->postJson('/api/password/forgot', $payload);

        $response->assertStatus(200);
        $response->assertJson([
            'message' => 'Password reset OTP sent successfully',
            'email' => $user->email,
        ]);

        $purpose = new PurposeVO(
            value: 'password_reset',
            label: 'Password Reset',
            ttl: 600,
            maxAttempts: 3
        );

        $otps = $this->otpService->getAllFor($user, $purpose);
        $this->assertCount(1, $otps);
    }

    public function test_send_password_reset_link_returns_200_even_if_user_not_found(): void
    {
        $payload = [
            'email' => 'nonexistent@example.com',
        ];

        $response = $this->postJson('/api/password/forgot', $payload);

        $response->assertStatus(200);
        $response->assertJson([
            'message' => 'Password reset OTP sent successfully',
            'email' => 'nonexistent@example.com',
        ]);
    }

    public function test_send_password_reset_link_rate_limit_protects_user_privacy(): void
    {
        $user = $this->createUser();

        $payload = [
            'email' => $user->email,
        ];

        // ✅ Premier envoi - OK
        $response1 = $this->postJson('/api/password/forgot', $payload);
        $response1->assertStatus(200);

        // ✅ Second envoi - Rate limit atteint (seuil = 1)
        $response2 = $this->postJson('/api/password/forgot', $payload);
        $response2->assertStatus(200);
        $response2->assertJson([
            'message' => 'Password reset OTP sent successfully',
            'email' => $user->email,
        ]);

        // ✅ Vérifier qu'un seul OTP a été créé
        $purpose = new PurposeVO(
            value: 'password_reset',
            label: 'Password Reset',
            ttl: 600,
            maxAttempts: 3
        );

        $otps = $this->otpService->getAllFor($user, $purpose);
        $this->assertCount(1, $otps);
    }

    // ============================================================================
    // Tests - Erreurs de validation
    // ============================================================================

    public function test_send_password_reset_link_returns_422_when_email_is_missing(): void
    {
        $payload = [];

        $response = $this->postJson('/api/password/forgot', $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);
    }

    public function test_send_password_reset_link_returns_422_when_email_invalid_format(): void
    {
        $payload = [
            'email' => 'invalid-email',
        ];

        $response = $this->postJson('/api/password/forgot', $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);
    }

    // ============================================================================
    // Tests - Cas limites
    // ============================================================================

    public function test_send_password_reset_link_with_uppercase_email(): void
    {
        $user = $this->createUser();

        $payload = [
            'email' => strtoupper($user->email),
        ];

        $response = $this->postJson('/api/password/forgot', $payload);

        $response->assertStatus(200);
        $response->assertJson([
            'message' => 'Password reset OTP sent successfully',
            'email' => strtoupper($user->email),
        ]);

        // ✅ Vérifier que l'OTP est créé (normalisation dans le service)
        $purpose = new PurposeVO(
            value: 'password_reset',
            label: 'Password Reset',
            ttl: 600,
            maxAttempts: 3
        );

        $otps = $this->otpService->getAllFor($user, $purpose);
        $this->assertCount(1, $otps);
    }

    public function test_send_password_reset_link_with_whitespace_in_email(): void
    {
        $user = $this->createUser();

        $payload = [
            'email' => '  '.$user->email.'  ',
        ];

        $response = $this->postJson('/api/password/forgot', $payload);

        // ✅ Le middleware TrimStrings nettoie les espaces
        $response->assertStatus(200);
        $response->assertJson([
            'message' => 'Password reset OTP sent successfully',
            'email' => trim($user->email),
        ]);
    }

    public function test_send_password_reset_link_returns_200_when_model_type_not_needed(): void
    {
        $payload = [
            'email' => 'john@example.com',
        ];

        $response = $this->postJson('/api/password/forgot', $payload);

        $response->assertStatus(200);
        $response->assertJson([
            'message' => 'Password reset OTP sent successfully',
            'email' => 'john@example.com',
        ]);
    }

    // ============================================================================
    // Tests d'intégration avec la base de données
    // ============================================================================

    public function test_send_password_reset_link_creates_otp_in_database(): void
    {
        $user = $this->createUser();

        $payload = [
            'email' => $user->email,
        ];

        $purpose = new PurposeVO(
            value: 'password_reset',
            label: 'Password Reset',
            ttl: 600,
            maxAttempts: 3
        );

        $otpsBefore = $this->otpService->getAllFor($user, $purpose);
        $this->assertCount(0, $otpsBefore);

        $response = $this->postJson('/api/password/forgot', $payload);

        $response->assertStatus(200);

        $otpsAfter = $this->otpService->getAllFor($user, $purpose);
        $this->assertCount(1, $otpsAfter);

        $otp = $otpsAfter->first();
        $this->assertNotNull($otp->expires_at);
        $this->assertTrue($otp->expires_at > now());
    }

    public function test_send_password_reset_link_for_user_with_verified_email(): void
    {
        $user = $this->createUser([
            'email_verified_at' => now(),
        ]);

        $payload = [
            'email' => $user->email,
        ];

        $response = $this->postJson('/api/password/forgot', $payload);

        $response->assertStatus(200);
        $response->assertJson([
            'message' => 'Password reset OTP sent successfully',
            'email' => $user->email,
        ]);

        $purpose = new PurposeVO(
            value: 'password_reset',
            label: 'Password Reset',
            ttl: 600,
            maxAttempts: 3
        );

        $otps = $this->otpService->getAllFor($user, $purpose);
        $this->assertCount(1, $otps);
    }
}
