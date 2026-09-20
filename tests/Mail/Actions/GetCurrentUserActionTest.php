<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Tests\Mail\Actions;

use AndyDefer\AuthenticationKit\Configs\AuthenticationKitConfig;
use AndyDefer\AuthenticationKit\Contracts\Configs\AuthenticationKitConfigInterface;
use AndyDefer\AuthenticationKit\Mail\Actions\EmailLoginAction;
use AndyDefer\AuthenticationKit\Mail\Actions\GetCurrentUserAction;
use AndyDefer\AuthenticationKit\Mail\Requests\EmailLoginRequest;
use AndyDefer\AuthenticationKit\Mail\Requests\GetCurrentUserRequest;
use AndyDefer\AuthenticationKit\Tests\IntegrationTestCase;
use AndyDefer\AuthenticationKit\Tests\Mail\Fixtures\Models\TestUserMail;
use AndyDefer\Nemesis\Contracts\Services\NemesisInterface;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;

final class GetCurrentUserActionTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('authentication-kit', [
            'token_name' => 'authentication-kit',
            'password_reset_rate_limit' => 3,
            'email_verification_rate_limit' => 5,
            'store_token_in_cookie' => false,
        ]);

        Config::set('nemesis.web', [
            'login_route' => '/login',
            'dashboard_route' => '/dashboard',
            'cookie_name' => 'nemesis_token',
            'cookie_secure' => false,
            'cookie_httponly' => false,
            'cookie_samesite' => 'lax',
        ]);

        $this->refreshConfigService();

        // ✅ Une seule route pour les deux cas (Bearer token ET cookie)
        Route::post('api/get-current-user', action_route(
            GetCurrentUserRequest::class,
            GetCurrentUserAction::class
        ))->name('get-current-user');

        Route::middleware(['validate.mail.authenticatable'])->post('/login', action_route(
            EmailLoginRequest::class,
            EmailLoginAction::class
        ));
    }

    private function refreshConfigService(): void
    {
        $this->app->forgetInstance(AuthenticationKitConfigInterface::class);
        $this->app->singleton(
            AuthenticationKitConfigInterface::class,
            function ($app) {
                return new AuthenticationKitConfig(
                    $app['config']
                );
            }
        );
    }

    private function createUserAndLogin(bool $storeInCookie = false): array
    {
        $user = TestUserMail::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('Password123!'),
        ]);

        if ($storeInCookie) {
            Config::set('authentication-kit.store_token_in_cookie', true);
            $this->refreshConfigService();
        }

        $payload = [
            'model_type' => TestUserMail::class,
            'email' => 'test@example.com',
            'password' => 'Password123!',
        ];

        $response = $this->postJson('/login', $payload);
        $response->assertStatus(200);

        $token = $response->json('token');

        return [$user, $token];
    }

    // ============================================================================
    // Tests avec Bearer token
    // ============================================================================

    public function test_me_returns_user_with_bearer_token(): void
    {
        [$user, $token] = $this->createUserAndLogin();

        $response = $this->postJson('api/get-current-user', [
            'model_type' => TestUserMail::class,
        ], [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'id' => $user->id,
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);
        $response->assertJsonStructure([
            'id',
            'name',
            'email',
            'createdAt',
            'updatedAt',
        ]);
    }

    public function test_me_returns_401_when_no_bearer_token(): void
    {
        $response = $this->postJson('api/get-current-user', [
            'model_type' => TestUserMail::class,
        ]);

        $response->assertStatus(401);
        $response->assertJson([
            'errorCode' => 'UNAUTHENTICATED',
        ]);
    }

    public function test_me_returns_401_with_invalid_bearer_token(): void
    {
        $response = $this->postJson('api/get-current-user', [
            'model_type' => TestUserMail::class,
        ], [
            'Authorization' => 'Bearer invalid-token',
        ]);

        $response->assertStatus(401);
        $response->assertJson([
            'errorCode' => 'UNAUTHENTICATED',
        ]);
    }

    // ============================================================================
    // Tests avec cookie
    // ============================================================================

    public function test_me_returns_user_with_cookie(): void
    {
        [$user, $token] = $this->createUserAndLogin(true);

        $response = $this->call('POST', 'api/get-current-user', [
            'model_type' => TestUserMail::class,
        ], [
            'nemesis_token' => $token,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'id' => $user->id,
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);
    }

    public function test_me_returns_401_when_no_cookie(): void
    {
        $response = $this->postJson('api/get-current-user', [
            'model_type' => TestUserMail::class,
        ]);

        $response->assertStatus(401);
        $response->assertJson([
            'errorCode' => 'UNAUTHENTICATED',
        ]);
    }

    public function test_me_returns_401_with_invalid_cookie(): void
    {
        $response = $this->call('POST', 'api/get-current-user', [
            'model_type' => TestUserMail::class,
        ], [
            'nemesis_token' => 'invalid-token',
        ]);

        $response->assertStatus(401);
        $response->assertJson([
            'errorCode' => 'UNAUTHENTICATED',
        ]);
    }

    // ============================================================================
    // Tests avec token expiré
    // ============================================================================

    public function test_me_returns_401_with_expired_bearer_token(): void
    {
        [$user, $token] = $this->createUserAndLogin();

        $nemesis = $this->app->make(NemesisInterface::class);
        $hashedToken = hash('sha256', $token);
        $tokenModel = $nemesis->findByHash($hashedToken);

        if ($tokenModel !== null) {
            $tokenModel->expires_at = now()->subDay();
            $tokenModel->save();
        }

        $response = $this->postJson('api/get-current-user', [
            'model_type' => TestUserMail::class,
        ], [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertStatus(401);
        $response->assertJson([
            'errorCode' => 'UNAUTHENTICATED',
        ]);
    }

    public function test_me_returns_401_with_expired_cookie(): void
    {
        [$user, $token] = $this->createUserAndLogin(true);

        $nemesis = $this->app->make(NemesisInterface::class);
        $hashedToken = hash('sha256', $token);
        $tokenModel = $nemesis->findByHash($hashedToken);

        if ($tokenModel !== null) {
            $tokenModel->expires_at = now()->subDay();
            $tokenModel->save();
        }

        $response = $this->call('POST', 'api/get-current-user', [
            'model_type' => TestUserMail::class,
        ], [
            'nemesis_token' => $token,
        ]);

        $response->assertStatus(401);
        $response->assertJson([
            'errorCode' => 'UNAUTHENTICATED',
        ]);
    }

    // ============================================================================
    // Tests avec token révoqué
    // ============================================================================

    public function test_me_returns_401_with_revoked_bearer_token(): void
    {
        [$user, $token] = $this->createUserAndLogin();

        $nemesis = $this->app->make(NemesisInterface::class);
        $hashedToken = hash('sha256', $token);
        $tokenModel = $nemesis->findByHash($hashedToken);

        if ($tokenModel !== null) {
            $nemesis->revoke($tokenModel);
        }

        $response = $this->postJson('api/get-current-user', [
            'model_type' => TestUserMail::class,
        ], [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertStatus(401);
        $response->assertJson([
            'errorCode' => 'UNAUTHENTICATED',
        ]);
    }

    public function test_me_returns_401_with_revoked_cookie(): void
    {
        [$user, $token] = $this->createUserAndLogin(true);

        $nemesis = $this->app->make(NemesisInterface::class);
        $hashedToken = hash('sha256', $token);
        $tokenModel = $nemesis->findByHash($hashedToken);

        if ($tokenModel !== null) {
            $nemesis->revoke($tokenModel);
        }

        $response = $this->call('POST', 'api/get-current-user', [
            'model_type' => TestUserMail::class,
        ], [
            'nemesis_token' => $token,
        ]);

        $response->assertStatus(401);
        $response->assertJson([
            'errorCode' => 'UNAUTHENTICATED',
        ]);
    }

    // ============================================================================
    // Test de performance
    // ============================================================================

    public function test_me_response_structure_is_consistent(): void
    {
        [$user, $token] = $this->createUserAndLogin();

        $response = $this->postJson('api/get-current-user', [
            'model_type' => TestUserMail::class,
        ], [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'id',
            'name',
            'email',
            'emailVerifiedAt',
            'createdAt',
            'updatedAt',
        ]);
    }

    public function test_me_returns_401_when_user_deleted(): void
    {
        [$user, $token] = $this->createUserAndLogin();

        $user->delete();

        $response = $this->postJson('api/get-current-user', [
            'model_type' => TestUserMail::class,
        ], [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertStatus(401);
        $response->assertJson([
            'errorCode' => 'UNAUTHENTICATED',
        ]);
    }

    public function test_me_handles_multiple_requests_with_same_bearer_token(): void
    {
        [$user, $token] = $this->createUserAndLogin();

        $response1 = $this->postJson('api/get-current-user', [
            'model_type' => TestUserMail::class,
        ], [
            'Authorization' => 'Bearer '.$token,
        ]);
        $response1->assertStatus(200);
        $response1->assertJson(['id' => $user->id]);

        $response2 = $this->postJson('api/get-current-user', [
            'model_type' => TestUserMail::class,
        ], [
            'Authorization' => 'Bearer '.$token,
        ]);
        $response2->assertStatus(200);
        $response2->assertJson(['id' => $user->id]);
    }

    public function test_me_handles_multiple_requests_with_same_cookie(): void
    {
        [$user, $token] = $this->createUserAndLogin(true);

        $response1 = $this->call('POST', 'api/get-current-user', [
            'model_type' => TestUserMail::class,
        ], [
            'nemesis_token' => $token,
        ]);
        $response1->assertStatus(200);
        $response1->assertJson(['id' => $user->id]);

        $response2 = $this->call('POST', 'api/get-current-user', [
            'model_type' => TestUserMail::class,
        ], [
            'nemesis_token' => $token,
        ]);
        $response2->assertStatus(200);
        $response2->assertJson(['id' => $user->id]);
    }

    // ============================================================================
    // Test avec soft delete
    // ============================================================================

    public function test_me_returns_401_with_soft_deleted_user_and_bearer_token(): void
    {
        [$user, $token] = $this->createUserAndLogin();

        $user->delete();

        $response = $this->postJson('api/get-current-user', [
            'model_type' => TestUserMail::class,
        ], [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertStatus(401);
        $response->assertJson([
            'errorCode' => 'UNAUTHENTICATED',
        ]);
    }

    public function test_me_returns_401_with_soft_deleted_user_and_cookie(): void
    {
        [$user, $token] = $this->createUserAndLogin(true);

        $user->delete();

        $response = $this->call('POST', 'api/get-current-user', [
            'model_type' => TestUserMail::class,
        ], [
            'nemesis_token' => $token,
        ]);

        $response->assertStatus(401);
        $response->assertJson([
            'errorCode' => 'UNAUTHENTICATED',
        ]);
    }

    // ============================================================================
    // Tests mode=simple (défaut) et mode=detailed
    // ============================================================================

    public function test_me_defaults_to_simple_mode_when_no_mode_provided(): void
    {
        [$user, $token] = $this->createUserAndLogin();

        $response = $this->postJson('api/get-current-user', [], [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'id' => $user->id,
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);
        $response->assertJsonMissing(['modelType']);
        $response->assertJsonMissing(['user']);
    }

    public function test_me_returns_simple_payload_when_mode_is_simple(): void
    {
        [$user, $token] = $this->createUserAndLogin();

        $response = $this->postJson('api/get-current-user', [
            'mode' => 'simple',
        ], [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'id' => $user->id,
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);
        $response->assertJsonMissing(['modelType']);
        $response->assertJsonMissing(['user']);
    }

    public function test_me_returns_detailed_payload_when_mode_is_detailed(): void
    {
        [$user, $token] = $this->createUserAndLogin();

        $response = $this->postJson('api/get-current-user', [
            'mode' => 'detailed',
        ], [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'user' => [
                'id',
                'name',
                'email',
                'createdAt',
                'updatedAt',
            ],
            'modelType',
        ]);
        $response->assertJsonPath('user.id', $user->id);
        $response->assertJsonPath('user.name', 'Test User');
        $response->assertJsonPath('user.email', 'test@example.com');
        $response->assertJsonPath('modelType', TestUserMail::class);
    }

    public function test_me_returns_422_when_mode_is_invalid(): void
    {
        [, $token] = $this->createUserAndLogin();

        $response = $this->postJson('api/get-current-user', [
            'mode' => 'invalid_mode',
        ], [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['mode']);
    }

    public function test_me_returns_401_when_detailed_and_unauthenticated(): void
    {
        $response = $this->postJson('api/get-current-user', [
            'mode' => 'detailed',
        ]);

        $response->assertStatus(401);
        $response->assertJson([
            'errorCode' => 'UNAUTHENTICATED',
        ]);
    }

    public function test_me_detailed_mode_works_with_cookie(): void
    {
        [$user, $token] = $this->createUserAndLogin(true);

        $response = $this->call('POST', 'api/get-current-user', [
            'mode' => 'detailed',
        ], [
            'nemesis_token' => $token,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('user.id', $user->id);
        $response->assertJsonPath('modelType', TestUserMail::class);
    }

    public function test_me_detailed_mode_does_not_leak_extra_fields(): void
    {
        [$user, $token] = $this->createUserAndLogin();

        $response = $this->postJson('api/get-current-user', [
            'mode' => 'detailed',
        ], [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertStatus(200);
        $response->assertJsonMissingPath('user.password');
        $response->assertJsonMissingPath('user.remember_token');
    }
}
