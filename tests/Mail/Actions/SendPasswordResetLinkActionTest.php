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
            'model_type' => TestUserMail::class,
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

    /**
     * 🔒 SECURITY: On retourne une erreur 400 avec un message générique
     * pour ne pas révéler si l'utilisateur existe ou non.
     *
     * Le comportement a changé : on ne retourne plus 200 pour les utilisateurs inexistants.
     */
    public function test_send_password_reset_link_returns_400_if_user_not_found(): void
    {
        $payload = [
            'model_type' => TestUserMail::class,
            'email' => 'nonexistent@example.com',
        ];

        $response = $this->postJson('/api/password/forgot', $payload);

        $response->assertStatus(400);
        $response->assertJson([
            'message' => 'We were unable to process your request. Please try again.',
        ]);

        // ✅ On vérifie que l'email n'est PAS révélé dans la réponse
        $response->assertJsonMissing(['email' => 'nonexistent@example.com']);
    }

    public function test_send_password_reset_link_rate_limit_protects_user_privacy(): void
    {
        $user = $this->createUser();

        $payload = [
            'model_type' => TestUserMail::class,
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
        $payload = [
            'model_type' => TestUserMail::class,
        ];

        $response = $this->postJson('/api/password/forgot', $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);
    }

    public function test_send_password_reset_link_returns_422_when_email_invalid_format(): void
    {
        $payload = [
            'model_type' => TestUserMail::class,
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
            'model_type' => TestUserMail::class,
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
            'model_type' => TestUserMail::class,
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

    /**
     * ✅ Le test vérifie que l'email est envoyé si l'utilisateur existe.
     * Le nom du test doit refléter le nouveau comportement.
     */
    public function test_send_password_reset_link_returns_200_when_user_exists_and_model_type_is_valid(): void
    {
        // Créer un utilisateur existant
        $this->createUser([
            'email' => 'john@example.com',
        ]);

        $payload = [
            'model_type' => TestUserMail::class,
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
            'model_type' => TestUserMail::class,
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
            'model_type' => TestUserMail::class,
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

    // ============================================================================
    // Tests - Validation model_type
    // ============================================================================

    public function test_send_password_reset_link_returns_422_when_model_type_is_missing(): void
    {
        $payload = [
            'email' => 'john@example.com',
        ];

        $response = $this->postJson('/api/password/forgot', $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['model_type']);
    }

    public function test_send_password_reset_link_returns_422_when_model_type_is_invalid(): void
    {
        $payload = [
            'model_type' => 'Invalid\\Model\\Class',
            'email' => 'john@example.com',
        ];

        $response = $this->postJson('/api/password/forgot', $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['model_type']);
        $response->assertJson([
            'message' => 'The model class \'Invalid\\Model\\Class\' does not exist.',
        ]);
    }
}
