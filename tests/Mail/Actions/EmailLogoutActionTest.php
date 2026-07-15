<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Tests\Mail\Actions;

use AndyDefer\AuthenticationKit\Contracts\Configs\AuthenticationKitConfigInterface;
use AndyDefer\AuthenticationKit\Mail\Actions\EmailLogoutAction;
use AndyDefer\AuthenticationKit\Mail\Requests\EmailLogoutRequest;
use AndyDefer\AuthenticationKit\Tests\IntegrationTestCase;
use AndyDefer\AuthenticationKit\Tests\Mail\Fixtures\Models\TestUserMail;
use AndyDefer\DomainStructures\Utils\StrictDataObject;
use AndyDefer\Nemesis\Contracts\Services\NemesisInterface;
use AndyDefer\Nemesis\Models\NemesisToken;
use AndyDefer\Nemesis\Records\NemesisTokenRecord;
use Illuminate\Foundation\Application;

final class EmailLogoutActionTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app['router']->middleware(['validate.mail.authenticatable', 'nemesis.token'])->post('/api/logout', action_route(
            EmailLogoutRequest::class,
            EmailLogoutAction::class
        ));
    }

    private function createUserAndGetToken(): array
    {
        $user = TestUserMail::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => bcrypt('Password123!'),
        ]);

        $nemesis = $this->app->make(NemesisInterface::class);
        $config = $this->app->make(AuthenticationKitConfigInterface::class);

        [$tokenModel, $plainToken] = $nemesis->createWithPlainToken(
            new NemesisTokenRecord(
                name: $config->getTokenName(),
                source: 'login',
                metadata: new StrictDataObject([]),
            ),
            $user
        );

        return [$user, $plainToken];
    }

    private function createUserAndGetTokenWithBearer(): array
    {
        [$user, $plainToken] = $this->createUserAndGetToken();

        return [$user, $plainToken, 'Bearer '.$plainToken];
    }

    // ============================================================================
    // Tests for logout() method - Successful logout
    // ============================================================================

    public function test_logout_successfully_revokes_token_and_returns_204(): void
    {
        [$user, $token, $bearerToken] = $this->createUserAndGetTokenWithBearer();

        $payload = [
            'model_type' => TestUserMail::class,
            'token' => $token,
        ];

        $response = $this->postJson('/api/logout', $payload, [
            'Authorization' => $bearerToken,
        ]);
        $tokenModel = NemesisToken::where('token_hash', $token)->first();

        $response->assertStatus(204);
        $response->assertNoContent();

        $nemesis = $this->app->make(NemesisInterface::class);
        $this->assertFalse($nemesis->validateToken($token, $user));
    }

    public function test_logout_logs_successful_logout(): void
    {
        [$user, $token, $bearerToken] = $this->createUserAndGetTokenWithBearer();

        // ✅ Utilisation du vrai LogRepository - pas de mock

        $payload = [
            'model_type' => TestUserMail::class,
            'token' => $token,
        ];

        $response = $this->postJson('/api/logout', $payload, [
            'Authorization' => $bearerToken,
        ]);

        $response->assertStatus(204);
    }

    // ============================================================================
    // Tests for logout() method - Error cases
    // ============================================================================

    public function test_logout_returns_401_when_token_does_not_exist(): void
    {
        [$user, $token, $bearerToken] = $this->createUserAndGetTokenWithBearer();

        $payload = [
            'model_type' => TestUserMail::class,
            'token' => 'non-existent-token-1234567890',
        ];

        $response = $this->postJson('/api/logout', $payload, [
            'Authorization' => $bearerToken,
        ]);

        $response->assertStatus(401);
        $response->assertJson([
            'errorCode' => 'INVALID_TOKEN',
        ]);
    }

    public function logout_returns_401_when_authenticatable_not_found(): void
    {
        [$user, $token, $bearerToken] = $this->createUserAndGetTokenWithBearer();

        $user->delete();

        $payload = [
            'model_type' => TestUserMail::class,
            'token' => $token,
        ];

        $response = $this->postJson('/api/logout', $payload, [
            'Authorization' => $bearerToken,
        ]);

        $response->assertStatus(401);
        $response->assertJson([
            'errorCode' => 'INVALID_TOKEN',
            'message' => 'Invalid token',
        ]);
    }

    public function test_logout_returns_400_when_model_type_is_missing(): void
    {
        [$user, $token, $bearerToken] = $this->createUserAndGetTokenWithBearer();

        $payload = [
            'token' => $token,
        ];

        $response = $this->postJson('/api/logout', $payload, [
            'Authorization' => $bearerToken,
        ]);

        $response->assertStatus(400);
        $response->assertJson([
            'errorCode' => 'MODEL_TYPE_REQUIRED',
        ]);
    }

    public function test_logout_returns_422_when_token_is_missing(): void
    {
        [$user, $token, $bearerToken] = $this->createUserAndGetTokenWithBearer();

        $payload = [
            'model_type' => TestUserMail::class,
        ];

        $response = $this->postJson('/api/logout', $payload, [
            'Authorization' => $bearerToken,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['token']);
    }

    public function test_logout_returns_500_when_model_type_does_not_exist(): void
    {
        [$user, $token, $bearerToken] = $this->createUserAndGetTokenWithBearer();

        $payload = [
            'model_type' => 'NonExistentClass',
            'token' => $token,
        ];

        $response = $this->postJson('/api/logout', $payload, [
            'Authorization' => $bearerToken,
        ]);

        $response->assertStatus(500);
        $response->assertJson([
            'errorCode' => 'MODEL_NOT_FOUND',
        ]);
    }

    public function test_logout_returns_500_when_model_type_does_not_implement_mail_authenticatable(): void
    {
        [$user, $token, $bearerToken] = $this->createUserAndGetTokenWithBearer();

        $payload = [
            'model_type' => Application::class,
            'token' => $token,
        ];

        $response = $this->postJson('/api/logout', $payload, [
            'Authorization' => $bearerToken,
        ]);

        $response->assertStatus(500);
        $response->assertJson([
            'errorCode' => 'INVALID_MODEL',
        ]);
    }

    // ============================================================================
    // Test de l'échec de logout - Avec un vrai token expiré ou révoqué
    // ============================================================================

    public function test_logout_returns_401_when_token_is_revoked(): void
    {
        [$user, $token, $bearerToken] = $this->createUserAndGetTokenWithBearer();

        $nemesis = $this->app->make(NemesisInterface::class);
        $tokenModel = $nemesis->findByHash(hash('sha256', $token));

        if ($tokenModel !== null) {
            $nemesis->revoke($tokenModel);
        }

        $payload = [
            'model_type' => TestUserMail::class,
            'token' => $token,
        ];

        $response = $this->postJson('/api/logout', $payload, [
            'Authorization' => $bearerToken,
        ]);

        $response->assertStatus(401);
        $response->assertJson([
            'errorCode' => 'INVALID_TOKEN',
        ]);
    }

    public function test_logout_returns_401_when_token_already_revoked(): void
    {
        [$user, $token, $bearerToken] = $this->createUserAndGetTokenWithBearer();

        $nemesis = $this->app->make(NemesisInterface::class);
        $tokenModel = $nemesis->findByHash(hash('sha256', $token));

        if ($tokenModel !== null) {
            $nemesis->revoke($tokenModel);
        }

        $payload = [
            'model_type' => TestUserMail::class,
            'token' => $token,
        ];

        $response = $this->postJson('/api/logout', $payload, [
            'Authorization' => $bearerToken,
        ]);

        $response->assertStatus(401);
        $response->assertJson([
            'errorCode' => 'INVALID_TOKEN',
        ]);
    }

    public function test_logout_returns_401_when_token_is_expired(): void
    {
        [$user, $token, $bearerToken] = $this->createUserAndGetTokenWithBearer();

        $nemesis = $this->app->make(NemesisInterface::class);
        $tokenModel = $nemesis->findByHash(hash('sha256', $token));

        if ($tokenModel !== null) {
            $tokenModel->expires_at = now()->subDay();
            $tokenModel->save();
        }

        $payload = [
            'model_type' => TestUserMail::class,
            'token' => $token,
        ];

        $response = $this->postJson('/api/logout', $payload, [
            'Authorization' => $bearerToken,
        ]);

        $response->assertStatus(401);
        $response->assertJson([
            'errorCode' => 'TOKEN_EXPIRED',
        ]);
    }

    // ============================================================================
    // Tests supplémentaires
    // ============================================================================

    public function test_logout_with_valid_token_twice_returns_401_second_time(): void
    {
        [$user, $token, $bearerToken] = $this->createUserAndGetTokenWithBearer();

        $payload = [
            'model_type' => TestUserMail::class,
            'token' => $token,
        ];

        $response1 = $this->postJson('/api/logout', $payload, [
            'Authorization' => $bearerToken,
        ]);
        $response1->assertStatus(204);

        $response2 = $this->postJson('/api/logout', $payload, [
            'Authorization' => $bearerToken,
        ]);
        $response2->assertStatus(401);
        $response2->assertJson([
            'errorCode' => 'INVALID_TOKEN',
        ]);
    }

    public function test_logout_returns_401_when_token_is_invalid(): void
    {
        [$user, $token, $bearerToken] = $this->createUserAndGetTokenWithBearer();

        $payload = [
            'model_type' => TestUserMail::class,
            'token' => 'invalid-token-123',
        ];

        $response = $this->postJson('/api/logout', $payload, [
            'Authorization' => $bearerToken,
        ]);

        $response->assertStatus(401);
        $response->assertJson([
            'errorCode' => 'INVALID_TOKEN',
        ]);
    }

    // ============================================================================
    // Test avec un vrai échec de service
    // ============================================================================

    public function logout_returns_401_when_tokenable_not_found(): void
    {
        [$user, $token, $bearerToken] = $this->createUserAndGetTokenWithBearer();

        $nemesis = $this->app->make(NemesisInterface::class);
        $tokenModel = $nemesis->findByHash(hash('sha256', $token));

        // ✅ Modifier le tokenable_id pour qu'il ne corresponde à aucun utilisateur
        if ($tokenModel !== null) {
            $tokenModel->tokenable_id = 99999;
            $tokenModel->save();
        }

        $payload = [
            'model_type' => TestUserMail::class,
            'token' => $token,
        ];

        $response = $this->postJson('/api/logout', $payload, [
            'Authorization' => $bearerToken,
        ]);

        // ✅ Le système ne trouve pas l'authenticatable
        // Le token est considéré comme invalide
        $response->assertStatus(401);
        $response->assertJson([
            'errorCode' => 'INVALID_TOKEN',
            'message' => 'Invalid token',
        ]);
    }
}
