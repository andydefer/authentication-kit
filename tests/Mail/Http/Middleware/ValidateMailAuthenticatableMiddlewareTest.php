<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Tests\Mail\Http\Middleware;

use AndyDefer\AuthenticationKit\Configs\AuthenticationKitConfig;
use AndyDefer\AuthenticationKit\Contracts\Configs\AuthenticationKitConfigInterface;
use AndyDefer\AuthenticationKit\Mail\Actions\EmailLoginAction;
use AndyDefer\AuthenticationKit\Mail\Actions\EmailRegisterAction;
use AndyDefer\AuthenticationKit\Mail\Contracts\MailAuthenticatable;
use AndyDefer\AuthenticationKit\Mail\Contracts\MailAuthenticationInterface;
use AndyDefer\AuthenticationKit\Mail\Requests\EmailLoginRequest;
use AndyDefer\AuthenticationKit\Mail\Requests\EmailRegisterRequest;
use AndyDefer\AuthenticationKit\Tests\IntegrationTestCase;
use AndyDefer\AuthenticationKit\Tests\Mail\Fixtures\Models\TestUserMail;
use Illuminate\Support\Facades\Route;

final class ValidateMailAuthenticatableMiddlewareTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // ✅ Définir la configuration
        $this->app['config']->set('authentication-kit', [
            'token_name' => 'authentication-kit',
            'password_reset_rate_limit' => 3,
            'email_verification_rate_limit' => 5,
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

        // ✅ Re-binder le service avec la config
        $this->app->singleton(
            AuthenticationKitConfigInterface::class,
            function ($app) {

                return new AuthenticationKitConfig(
                    $app['config']
                );
            }
        );

        Route::middleware(['validate.mail.authenticatable'])->post('/test-validate', function () {
            $service = app(MailAuthenticationInterface::class);

            return response()->json(['success' => true, 'service' => get_class($service)]);
        });

        Route::middleware(['validate.mail.authenticatable'])->post('/api/test-login', action_route(
            EmailLoginRequest::class,
            EmailLoginAction::class
        ));

        Route::middleware(['validate.mail.authenticatable'])->post('/api/test-register', action_route(
            EmailRegisterRequest::class,
            EmailRegisterAction::class
        ));
    }

    private function refreshConfigService(): void
    {
        $this->app->forgetInstance(AuthenticationKitConfigInterface::class);
        $this->app->singleton(
            AuthenticationKitConfigInterface::class,
            function ($app) {
                return new AuthenticationKitConfig(
                    $app['config'] // ✅ Passer l'instance de config directement
                );
            }
        );
    }

    // ============================================================================
    // Validation Tests
    // ============================================================================

    public function test_middleware_returns_error_when_model_type_is_missing(): void
    {
        $payload = [
            'email' => 'john@example.com',
            'password' => 'Password123!',
        ];

        $response = $this->postJson('/test-validate', $payload);

        $response->assertStatus(400);
        $response->assertJson([
            'message' => 'model_type is required',
            'status' => 400,
            'errorCode' => 'MODEL_TYPE_REQUIRED',
        ]);
    }

    public function test_middleware_returns_error_when_model_type_does_not_exist(): void
    {
        $payload = [
            'model_type' => 'NonExistentClass',
            'email' => 'john@example.com',
            'password' => 'Password123!',
        ];

        $response = $this->postJson('/test-validate', $payload);

        $response->assertStatus(500);
        $response->assertJson([
            'message' => 'Model NonExistentClass does not exist',
            'status' => 500,
            'errorCode' => 'MODEL_NOT_FOUND',
        ]);
    }

    public function test_middleware_returns_error_when_model_type_does_not_implement_interface(): void
    {
        $payload = [
            'model_type' => \stdClass::class,
            'email' => 'john@example.com',
            'password' => 'Password123!',
        ];

        $response = $this->postJson('/test-validate', $payload);

        $response->assertStatus(500);
        $response->assertJson([
            'message' => 'Model stdClass must implement '.MailAuthenticatable::class,
            'status' => 500,
            'errorCode' => 'INVALID_MODEL',
        ]);
    }

    public function test_middleware_passes_with_valid_model_type(): void
    {
        $payload = [
            'model_type' => TestUserMail::class,
            'email' => 'john@example.com',
            'password' => 'Password123!',
        ];

        $response = $this->postJson('/test-validate', $payload);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);
    }

    public function test_middleware_binds_correct_service(): void
    {
        $payload = [
            'model_type' => TestUserMail::class,
            'email' => 'john@example.com',
            'password' => 'Password123!',
        ];

        $response = $this->postJson('/test-validate', $payload);

        $response->assertStatus(200);

        $data = $response->json();
        $this->assertStringContainsString('MailAuthenticationService', $data['service']);
    }

    // ============================================================================
    // Cookie Storage Tests via Login
    // ============================================================================

    public function test_middleware_allows_cookie_to_be_stored_via_login(): void
    {
        $this->app['config']->set('authentication-kit.store_token_in_cookie', true);
        $this->app['config']->set('nemesis.web.cookie_name', 'nemesis_token');

        $this->refreshConfigService();

        $user = TestUserMail::create([
            'name' => 'Cookie Test User',
            'email' => 'cookie@example.com',
            'password' => bcrypt('Password123!'),
        ]);

        $payload = [
            'model_type' => TestUserMail::class,
            'email' => 'cookie@example.com',
            'password' => 'Password123!',
        ];

        $response = $this->postJson('/api/test-login', $payload);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'message',
            'auth',
            'token',
        ]);

        $response->assertCookie('nemesis_token');

        $this->app['config']->set('authentication-kit.store_token_in_cookie', false);
        $this->refreshConfigService();
    }

    public function test_middleware_allows_login_without_cookie_when_not_configured(): void
    {
        $this->app['config']->set('authentication-kit.store_token_in_cookie', false);
        $this->refreshConfigService();

        $user = TestUserMail::create([
            'name' => 'No Cookie User',
            'email' => 'nocookie@example.com',
            'password' => bcrypt('Password123!'),
        ]);

        $payload = [
            'model_type' => TestUserMail::class,
            'email' => 'nocookie@example.com',
            'password' => 'Password123!',
        ];

        $response = $this->postJson('/api/test-login', $payload);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'message',
            'auth',
            'token',
        ]);

        $response->assertCookieMissing('nemesis_token');
    }

    public function test_middleware_works_with_register_and_cookie(): void
    {
        $this->app['config']->set('authentication-kit.store_token_in_cookie', true);
        $this->app['config']->set('nemesis.web.cookie_name', 'nemesis_token');

        $this->refreshConfigService();

        $payload = [
            'model_type' => TestUserMail::class,
            'name' => 'John|Doe',
            'email' => 'registercookie@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'with_token' => true,
        ];

        $response = $this->postJson('/api/test-register', $payload);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'message',
            'auth',
            'token',
        ]);

        $response->assertCookie('nemesis_token');

        $this->app['config']->set('authentication-kit.store_token_in_cookie', false);
        $this->refreshConfigService();
    }

    public function test_middleware_register_without_cookie_when_not_configured(): void
    {
        $this->app['config']->set('authentication-kit.store_token_in_cookie', false);
        $this->refreshConfigService();

        $payload = [
            'model_type' => TestUserMail::class,
            'name' => 'Jane|Doe',
            'email' => 'registernocookie@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'with_token' => true,
        ];

        $response = $this->postJson('/api/test-register', $payload);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'message',
            'auth',
            'token',
        ]);

        $response->assertCookieMissing('nemesis_token');
    }

    public function test_middleware_handles_login_with_soft_deleted_user(): void
    {
        $this->app['config']->set('authentication-kit.store_token_in_cookie', true);
        $this->refreshConfigService();

        $user = TestUserMail::create([
            'name' => 'Soft Delete User',
            'email' => 'softdelete@example.com',
            'password' => bcrypt('Password123!'),
        ]);

        $user->delete();

        $payload = [
            'model_type' => TestUserMail::class,
            'email' => 'softdelete@example.com',
            'password' => 'Password123!',
        ];

        $response = $this->postJson('/api/test-login', $payload);

        $response->assertStatus(401);
        $response->assertJson([
            'message' => 'Invalid credentials',
            'status' => 401,
            'errorCode' => 'INVALID_CREDENTIALS',
        ]);

        $this->app['config']->set('authentication-kit.store_token_in_cookie', false);
        $this->refreshConfigService();
    }

    public function test_middleware_handles_invalid_credentials(): void
    {
        $this->app['config']->set('authentication-kit.store_token_in_cookie', true);
        $this->refreshConfigService();

        $payload = [
            'model_type' => TestUserMail::class,
            'email' => 'nonexistent@example.com',
            'password' => 'WrongPassword!',
        ];

        $response = $this->postJson('/api/test-login', $payload);

        $response->assertStatus(401);
        $response->assertJson([
            'message' => 'Invalid credentials',
            'status' => 401,
            'errorCode' => 'INVALID_CREDENTIALS',
        ]);

        $this->app['config']->set('authentication-kit.store_token_in_cookie', false);
        $this->refreshConfigService();
    }
}
