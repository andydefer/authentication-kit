<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Tests\Mail\Actions;

use AndyDefer\AuthenticationKit\Configs\AuthenticationKitConfig;
use AndyDefer\AuthenticationKit\Contracts\Configs\AuthenticationKitConfigInterface;
use AndyDefer\AuthenticationKit\Mail\Actions\EmailLoginAction;
use AndyDefer\AuthenticationKit\Mail\Requests\EmailLoginRequest;
use AndyDefer\AuthenticationKit\Tests\IntegrationTestCase;
use AndyDefer\AuthenticationKit\Tests\Mail\Fixtures\Models\TestUserMail;
use Illuminate\Testing\TestResponse;

final class EmailLoginActionTest extends IntegrationTestCase
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

        $this->app['router']->middleware(['validate.mail.authenticatable'])->post('/api/email-login', action_route(
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

    private function getCookieValue(TestResponse $response, string $cookieName): ?string
    {
        $cookies = $response->headers->getCookies();
        foreach ($cookies as $cookie) {
            if ($cookie->getName() === $cookieName) {
                return $cookie->getValue();
            }
        }

        return null;
    }

    // ============================================================================
    // Tests existants
    // ============================================================================

    public function test_login_auth_successfully(): void
    {
        $user = TestUserMail::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => bcrypt('Password123!'),
        ]);

        $payload = [
            'model_type' => TestUserMail::class,
            'email' => 'john@example.com',
            'password' => 'Password123!',
        ];

        $response = $this->postJson('/api/email-login', $payload);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'message',
            'auth' => [
                'id',
                'name',
                'email',
                'createdAt',
            ],
            'token',
        ]);

        $response->assertJson([
            'message' => 'Login successful',
            'auth' => [
                'id' => $user->id,
                'name' => 'John Doe',
                'email' => 'john@example.com',
            ],
        ]);
    }

    public function test_login_returns_error_when_email_is_missing(): void
    {
        $payload = [
            'model_type' => TestUserMail::class,
            'password' => 'Password123!',
        ];

        $response = $this->postJson('/api/email-login', $payload);

        $response->assertStatus(400);
        $response->assertJson([
            'message' => 'Email and password are required',
            'status' => 400,
            'errorCode' => 'MISSING_CREDENTIALS',
        ]);
    }

    public function test_login_returns_error_when_password_is_missing(): void
    {
        $payload = [
            'model_type' => TestUserMail::class,
            'email' => 'john@example.com',
        ];

        $response = $this->postJson('/api/email-login', $payload);

        $response->assertStatus(400);
        $response->assertJson([
            'message' => 'Email and password are required',
            'status' => 400,
            'errorCode' => 'MISSING_CREDENTIALS',
        ]);
    }

    public function test_login_returns_error_when_credentials_are_invalid(): void
    {
        $payload = [
            'model_type' => TestUserMail::class,
            'email' => 'john@example.com',
            'password' => 'WrongPassword!',
        ];

        $response = $this->postJson('/api/email-login', $payload);

        $response->assertStatus(401);
        $response->assertJson([
            'message' => 'Invalid credentials',
            'status' => 401,
            'errorCode' => 'INVALID_CREDENTIALS',
        ]);
    }

    public function test_login_returns_error_when_user_does_not_exist(): void
    {
        $payload = [
            'model_type' => TestUserMail::class,
            'email' => 'nonexistent@example.com',
            'password' => 'Password123!',
        ];

        $response = $this->postJson('/api/email-login', $payload);

        $response->assertStatus(401);
        $response->assertJson([
            'message' => 'Invalid credentials',
            'status' => 401,
            'errorCode' => 'INVALID_CREDENTIALS',
        ]);
    }

    public function test_login_logs_successful_login(): void
    {
        $user = TestUserMail::create([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => bcrypt('Password123!'),
        ]);

        $payload = [
            'model_type' => TestUserMail::class,
            'email' => 'jane@example.com',
            'password' => 'Password123!',
        ];

        $response = $this->postJson('/api/email-login', $payload);

        $response->assertStatus(200);
    }

    public function test_login_returns_error_when_model_type_does_not_exist(): void
    {
        $payload = [
            'model_type' => 'NonExistentClass',
            'email' => 'john@example.com',
            'password' => 'Password123!',
        ];

        $response = $this->postJson('/api/email-login', $payload);

        $response->assertStatus(500);
        $response->assertJson([
            'message' => 'Model NonExistentClass does not exist',
            'status' => 500,
            'errorCode' => 'MODEL_NOT_FOUND',
        ]);
    }

    public function test_login_returns_error_when_model_type_is_missing(): void
    {
        $payload = [
            'email' => 'john@example.com',
            'password' => 'Password123!',
        ];

        $response = $this->postJson('/api/email-login', $payload);

        $response->assertStatus(400);
        $response->assertJson([
            'message' => 'model_type is required',
            'status' => 400,
            'errorCode' => 'MODEL_TYPE_REQUIRED',
        ]);
    }

    public function test_login_returns_error_when_model_type_is_empty_string(): void
    {
        $payload = [
            'model_type' => '',
            'email' => 'john@example.com',
            'password' => 'Password123!',
        ];

        $response = $this->postJson('/api/email-login', $payload);

        $response->assertStatus(400);
        $response->assertJson([
            'message' => 'model_type is required',
            'status' => 400,
            'errorCode' => 'MODEL_TYPE_REQUIRED',
        ]);
    }

    // ============================================================================
    // Tests cookie
    // ============================================================================

    public function test_login_stores_cookie_when_configured(): void
    {
        $this->app['config']->set('authentication-kit.store_token_in_cookie', true);
        $this->app['config']->set('nemesis.web.cookie_name', 'nemesis_token');
        $this->refreshConfigService();

        $user = TestUserMail::create([
            'name' => 'Cookie User',
            'email' => 'cookie@example.com',
            'password' => bcrypt('Password123!'),
        ]);

        $payload = [
            'model_type' => TestUserMail::class,
            'email' => 'cookie@example.com',
            'password' => 'Password123!',
        ];

        $response = $this->postJson('/api/email-login', $payload);

        $response->assertStatus(200);

        $cookieValue = $this->getCookieValue($response, 'nemesis_token');
        $this->assertNotNull($cookieValue);
        $this->assertNotEmpty($cookieValue);

        $this->app['config']->set('authentication-kit.store_token_in_cookie', false);
        $this->refreshConfigService();
    }

    public function test_login_does_not_store_cookie_when_not_configured(): void
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

        $response = $this->postJson('/api/email-login', $payload);

        $response->assertStatus(200);

        $cookieValue = $this->getCookieValue($response, 'nemesis_token');
        $this->assertNull($cookieValue);
    }

    public function test_login_uses_configured_cookie_name(): void
    {
        $this->app['config']->set('authentication-kit.store_token_in_cookie', true);
        $this->app['config']->set('nemesis.web.cookie_name', 'custom_cookie_name');
        $this->refreshConfigService();

        $user = TestUserMail::create([
            'name' => 'Custom Cookie User',
            'email' => 'customcookie@example.com',
            'password' => bcrypt('Password123!'),
        ]);

        $payload = [
            'model_type' => TestUserMail::class,
            'email' => 'customcookie@example.com',
            'password' => 'Password123!',
        ];

        $response = $this->postJson('/api/email-login', $payload);

        $response->assertStatus(200);

        $cookieValue = $this->getCookieValue($response, 'custom_cookie_name');
        $this->assertNotNull($cookieValue);
        $this->assertNotEmpty($cookieValue);

        $this->app['config']->set('authentication-kit.store_token_in_cookie', false);
        $this->app['config']->set('nemesis.web.cookie_name', 'nemesis_token');
        $this->refreshConfigService();
    }

    public function test_login_cookie_contains_valid_token(): void
    {
        $this->app['config']->set('authentication-kit.store_token_in_cookie', true);
        $this->app['config']->set('nemesis.web.cookie_name', 'nemesis_token');
        $this->refreshConfigService();

        $user = TestUserMail::create([
            'name' => 'Token User',
            'email' => 'tokenuser@example.com',
            'password' => bcrypt('Password123!'),
        ]);

        $payload = [
            'model_type' => TestUserMail::class,
            'email' => 'tokenuser@example.com',
            'password' => 'Password123!',
        ];

        $response = $this->postJson('/api/email-login', $payload);

        $response->assertStatus(200);

        $cookieValue = $this->getCookieValue($response, 'nemesis_token');
        $this->assertNotNull($cookieValue);
        $this->assertNotEmpty($cookieValue);
        $this->assertIsString($cookieValue);
        $this->assertGreaterThan(20, strlen($cookieValue));

        $this->app['config']->set('authentication-kit.store_token_in_cookie', false);
        $this->refreshConfigService();
    }

    public function test_login_with_cookie_and_web_middleware(): void
    {
        $this->app['config']->set('authentication-kit.store_token_in_cookie', true);
        $this->app['config']->set('nemesis.web.cookie_name', 'nemesis_token');
        $this->refreshConfigService();

        $this->app['router']->middleware(['nemesis.web'])->get('/protected', function () {
            return response()->json(['message' => 'Protected content']);
        });

        $user = TestUserMail::create([
            'name' => 'Web User',
            'email' => 'webuser@example.com',
            'password' => bcrypt('Password123!'),
        ]);

        $payload = [
            'model_type' => TestUserMail::class,
            'email' => 'webuser@example.com',
            'password' => 'Password123!',
        ];

        $loginResponse = $this->postJson('/api/email-login', $payload);
        $loginResponse->assertStatus(200);

        $cookieValue = $this->getCookieValue($loginResponse, 'nemesis_token');
        $this->assertNotNull($cookieValue);
        $this->assertNotEmpty($cookieValue);

        // ✅ Utiliser withUnencryptedCookie au lieu de withCookie
        $protectedResponse = $this->withUnencryptedCookie('nemesis_token', $cookieValue)
            ->get('/protected');

        $protectedResponse->assertStatus(200);
        $protectedResponse->assertJson(['message' => 'Protected content']);

        $this->app['config']->set('authentication-kit.store_token_in_cookie', false);
        $this->refreshConfigService();
    }
}
