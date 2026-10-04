<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Tests\Mail\Actions;

use AndyDefer\AuthenticationKit\Configs\AuthenticationKitConfig;
use AndyDefer\AuthenticationKit\Contracts\Configs\AuthenticationKitConfigInterface;
use AndyDefer\AuthenticationKit\Mail\Actions\EmailRegisterAction;
use AndyDefer\AuthenticationKit\Mail\Requests\EmailRegisterRequest;
use AndyDefer\AuthenticationKit\Tests\IntegrationTestCase;
use AndyDefer\AuthenticationKit\Tests\Mail\Fixtures\Models\TestUserMail;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Cookie;

final class EmailRegisterActionTest extends IntegrationTestCase
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
            'cookie_duration' => 525600,
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

        $this->app['router']->middleware(['validate.mail.authenticatable'])->post('/api/email-register', action_route(
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

    private function getCookieObject(TestResponse $response, string $cookieName): ?Cookie
    {
        foreach ($response->headers->getCookies() as $cookie) {
            if ($cookie->getName() === $cookieName) {
                return $cookie;
            }
        }

        return null;
    }

    // ============================================================================
    // Tests existants
    // ============================================================================

    public function test_register_auth_successfully_without_token(): void
    {
        $payload = [
            'model_type' => TestUserMail::class,
            'with_token' => false,
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ];

        $response = $this->postJson('/api/email-register', $payload);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'message',
            'auth' => [
                'id',
                'name',
                'email',
                'createdAt',
            ],
        ]);
        $response->assertJsonMissing(['token']);

        $this->assertDatabaseHas('test_users', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);
    }

    public function test_register_auth_successfully_with_token(): void
    {
        $payload = [
            'model_type' => TestUserMail::class,
            'with_token' => true,
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ];

        $response = $this->postJson('/api/email-register', $payload);

        $response->assertStatus(201);
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

        $this->assertDatabaseHas('test_users', [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
        ]);
    }

    public function test_register_logs_successful_registration(): void
    {
        $payload = [
            'model_type' => TestUserMail::class,
            'with_token' => true,
            'name' => 'Log Test',
            'email' => 'log@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ];

        $response = $this->postJson('/api/email-register', $payload);

        $response->assertStatus(201);
    }

    public function test_register_logs_failed_registration(): void
    {
        $payload = [
            'model_type' => TestUserMail::class,
            'name' => 'John Doe',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ];

        $response = $this->postJson('/api/email-register', $payload);

        $response->assertStatus(422);
    }

    public function test_register_returns_validation_error_when_email_is_missing(): void
    {
        $payload = [
            'model_type' => TestUserMail::class,
            'name' => 'John Doe',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ];

        $response = $this->postJson('/api/email-register', $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);
    }

    public function test_register_returns_validation_error_when_password_is_too_short(): void
    {
        $payload = [
            'model_type' => TestUserMail::class,
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => '123',
            'password_confirmation' => '123',
        ];

        $response = $this->postJson('/api/email-register', $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['password']);
    }

    public function test_register_returns_validation_error_when_password_confirmation_does_not_match(): void
    {
        $payload = [
            'model_type' => TestUserMail::class,
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'WrongPassword!',
        ];

        $response = $this->postJson('/api/email-register', $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['password']);
    }

    public function test_register_returns_error_when_model_type_does_not_exist(): void
    {
        $payload = [
            'model_type' => 'NonExistentClass',
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ];

        $response = $this->postJson('/api/email-register', $payload);

        $response->assertStatus(422);
        $response->assertJson([
            'message' => 'Model NonExistentClass does not exist',
            'status' => 422,
            'errorCode' => 'MODEL_NOT_FOUND',
        ]);
    }

    public function test_register_prevents_duplicate_email(): void
    {
        $payload = [
            'model_type' => TestUserMail::class,
            'with_token' => false,
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ];

        $this->postJson('/api/email-register', $payload);

        $response = $this->postJson('/api/email-register', $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);
    }

    public function test_register_returns_validation_error_when_name_is_missing(): void
    {
        $payload = [
            'model_type' => TestUserMail::class,
            'email' => 'john@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ];

        $response = $this->postJson('/api/email-register', $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name']);
    }

    public function test_register_returns_validation_error_when_model_type_is_missing(): void
    {
        $payload = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ];

        $response = $this->postJson('/api/email-register', $payload);

        $response->assertStatus(400);
        $response->assertJson([
            'message' => 'model_type is required',
            'status' => 400,
            'errorCode' => 'MODEL_TYPE_REQUIRED',
        ]);
    }

    public function test_register_returns_validation_error_when_model_type_is_empty_string(): void
    {
        $payload = [
            'model_type' => '',
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ];

        $response = $this->postJson('/api/email-register', $payload);

        $response->assertStatus(400);
        $response->assertJson([
            'message' => 'model_type is required',
            'status' => 400,
            'errorCode' => 'MODEL_TYPE_REQUIRED',
        ]);
    }

    // ============================================================================
    // Tests cookie avec with_token
    // ============================================================================

    public function test_register_stores_cookie_when_with_token_and_configured(): void
    {
        $this->app['config']->set('authentication-kit.store_token_in_cookie', true);
        $this->app['config']->set('nemesis.web.cookie_name', 'nemesis_token');
        $this->refreshConfigService();

        $payload = [
            'model_type' => TestUserMail::class,
            'with_token' => true,
            'name' => 'Cookie Register',
            'email' => 'cookieregister@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ];

        $response = $this->postJson('/api/email-register', $payload);

        $response->assertStatus(201);

        $cookieValue = $this->getCookieValue($response, 'nemesis_token');
        $this->assertNotNull($cookieValue);
        $this->assertNotEmpty($cookieValue);

        $this->app['config']->set('authentication-kit.store_token_in_cookie', false);
        $this->refreshConfigService();
    }

    public function test_register_does_not_store_cookie_when_with_token_but_not_configured(): void
    {
        $this->app['config']->set('authentication-kit.store_token_in_cookie', false);
        $this->refreshConfigService();

        $payload = [
            'model_type' => TestUserMail::class,
            'with_token' => true,
            'name' => 'No Cookie Register',
            'email' => 'nocookieregister@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ];

        $response = $this->postJson('/api/email-register', $payload);

        $response->assertStatus(201);

        $cookieValue = $this->getCookieValue($response, 'nemesis_token');
        $this->assertNull($cookieValue);
    }

    public function test_register_does_not_store_cookie_when_with_token_false_even_configured(): void
    {
        $this->app['config']->set('authentication-kit.store_token_in_cookie', true);
        $this->app['config']->set('nemesis.web.cookie_name', 'nemesis_token');
        $this->refreshConfigService();

        $payload = [
            'model_type' => TestUserMail::class,
            'with_token' => false,
            'name' => 'No Token Register',
            'email' => 'notokenregister@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ];

        $response = $this->postJson('/api/email-register', $payload);

        $response->assertStatus(201);

        $cookieValue = $this->getCookieValue($response, 'nemesis_token');
        $this->assertNull($cookieValue);

        $this->app['config']->set('authentication-kit.store_token_in_cookie', false);
        $this->refreshConfigService();
    }

    public function test_register_uses_configured_cookie_name_with_token(): void
    {
        $this->app['config']->set('authentication-kit.store_token_in_cookie', true);
        $this->app['config']->set('nemesis.web.cookie_name', 'custom_register_cookie');
        $this->refreshConfigService();

        $payload = [
            'model_type' => TestUserMail::class,
            'with_token' => true,
            'name' => 'Custom Cookie Register',
            'email' => 'customcookieregister@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ];

        $response = $this->postJson('/api/email-register', $payload);

        $response->assertStatus(201);

        $cookieValue = $this->getCookieValue($response, 'custom_register_cookie');
        $this->assertNotNull($cookieValue);
        $this->assertNotEmpty($cookieValue);

        $this->app['config']->set('authentication-kit.store_token_in_cookie', false);
        $this->app['config']->set('nemesis.web.cookie_name', 'nemesis_token');
        $this->refreshConfigService();
    }

    public function test_register_cookie_contains_valid_token(): void
    {
        $this->app['config']->set('authentication-kit.store_token_in_cookie', true);
        $this->app['config']->set('nemesis.web.cookie_name', 'nemesis_token');
        $this->refreshConfigService();

        $payload = [
            'model_type' => TestUserMail::class,
            'with_token' => true,
            'name' => 'Token Register',
            'email' => 'tokenregister@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ];

        $response = $this->postJson('/api/email-register', $payload);

        $response->assertStatus(201);

        $cookieValue = $this->getCookieValue($response, 'nemesis_token');
        $this->assertNotNull($cookieValue);
        $this->assertNotEmpty($cookieValue);
        $this->assertIsString($cookieValue);
        $this->assertGreaterThan(20, strlen($cookieValue));

        $this->app['config']->set('authentication-kit.store_token_in_cookie', false);
        $this->refreshConfigService();
    }

    public function test_register_with_token_and_cookie_protects_web_route(): void
    {
        $this->app['config']->set('authentication-kit.store_token_in_cookie', true);
        $this->app['config']->set('nemesis.web.cookie_name', 'nemesis_token');
        $this->refreshConfigService();

        $this->app['router']->middleware(['nemesis.web'])->get('/protected-register', function () {
            return response()->json(['message' => 'Protected content']);
        });

        $payload = [
            'model_type' => TestUserMail::class,
            'with_token' => true,
            'name' => 'Web Register',
            'email' => 'webregister@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ];

        $registerResponse = $this->postJson('/api/email-register', $payload);
        $registerResponse->assertStatus(201);

        $cookieValue = $this->getCookieValue($registerResponse, 'nemesis_token');
        $this->assertNotNull($cookieValue);
        $this->assertNotEmpty($cookieValue);

        $protectedResponse = $this->withUnencryptedCookie('nemesis_token', $cookieValue)
            ->get('/protected-register');

        $protectedResponse->assertStatus(200);
        $protectedResponse->assertJson(['message' => 'Protected content']);

        $this->app['config']->set('authentication-kit.store_token_in_cookie', false);
        $this->refreshConfigService();
    }

    // ============================================================================
    // Tests remember me
    // ============================================================================

    public function test_register_with_remember_me_true_stores_persistent_cookie(): void
    {
        // Arrange: cookie storage on, with_token and remember_me both enabled
        $this->app['config']->set('authentication-kit.store_token_in_cookie', true);
        $this->app['config']->set('authentication-kit.cookie_duration', 525600);
        $this->app['config']->set('nemesis.web.cookie_name', 'nemesis_token');
        $this->refreshConfigService();

        $payload = [
            'model_type' => TestUserMail::class,
            'with_token' => true,
            'remember_me' => true,
            'name' => 'Remember Register',
            'email' => 'rememberregister@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ];

        // Act
        $response = $this->postJson('/api/email-register', $payload);

        // Assert: the cookie is issued with an expiration matching the configured duration
        $response->assertStatus(201);

        $cookie = $this->getCookieObject($response, 'nemesis_token');
        $this->assertNotNull($cookie);

        $expectedExpiration = now()->addMinutes(525600)->getTimestamp();
        $this->assertEqualsWithDelta($expectedExpiration, $cookie->getExpiresTime(), 5);

        $this->app['config']->set('authentication-kit.store_token_in_cookie', false);
        $this->refreshConfigService();
    }

    public function test_register_with_remember_me_false_stores_session_cookie(): void
    {
        // Arrange: cookie storage on, with_token enabled, remember_me disabled
        $this->app['config']->set('authentication-kit.store_token_in_cookie', true);
        $this->app['config']->set('nemesis.web.cookie_name', 'nemesis_token');
        $this->refreshConfigService();

        $payload = [
            'model_type' => TestUserMail::class,
            'with_token' => true,
            'remember_me' => false,
            'name' => 'No Remember Register',
            'email' => 'norememberregister@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ];

        // Act
        $response = $this->postJson('/api/email-register', $payload);

        // Assert: the cookie is a session cookie (no expiration)
        $response->assertStatus(201);

        $cookie = $this->getCookieObject($response, 'nemesis_token');
        $this->assertNotNull($cookie);
        $this->assertSame(0, $cookie->getExpiresTime());

        $this->app['config']->set('authentication-kit.store_token_in_cookie', false);
        $this->refreshConfigService();
    }

    public function test_register_without_remember_me_stores_session_cookie_by_default(): void
    {
        // Arrange: cookie storage on, remember_me omitted from the request
        $this->app['config']->set('authentication-kit.store_token_in_cookie', true);
        $this->app['config']->set('nemesis.web.cookie_name', 'nemesis_token');
        $this->refreshConfigService();

        $payload = [
            'model_type' => TestUserMail::class,
            'with_token' => true,
            'name' => 'Default Register',
            'email' => 'defaultregister@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ];

        // Act
        $response = $this->postJson('/api/email-register', $payload);

        // Assert: default behavior produces a session cookie
        $response->assertStatus(201);

        $cookie = $this->getCookieObject($response, 'nemesis_token');
        $this->assertNotNull($cookie);
        $this->assertSame(0, $cookie->getExpiresTime());

        $this->app['config']->set('authentication-kit.store_token_in_cookie', false);
        $this->refreshConfigService();
    }

    public function test_register_with_remember_me_does_not_store_cookie_when_disabled(): void
    {
        // Arrange: cookie storage explicitly off, remember_me requested
        $this->app['config']->set('authentication-kit.store_token_in_cookie', false);
        $this->refreshConfigService();

        $payload = [
            'model_type' => TestUserMail::class,
            'with_token' => true,
            'remember_me' => true,
            'name' => 'No Cookie Remember Register',
            'email' => 'nocookierememberregister@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ];

        // Act
        $response = $this->postJson('/api/email-register', $payload);

        // Assert: registration succeeds but no cookie is emitted
        $response->assertStatus(201);
        $this->assertNull($this->getCookieValue($response, 'nemesis_token'));
    }

    public function test_register_with_remember_me_but_no_token_does_not_store_cookie(): void
    {
        // Arrange: cookie storage on, remember_me true, but with_token false
        $this->app['config']->set('authentication-kit.store_token_in_cookie', true);
        $this->app['config']->set('nemesis.web.cookie_name', 'nemesis_token');
        $this->refreshConfigService();

        $payload = [
            'model_type' => TestUserMail::class,
            'with_token' => false,
            'remember_me' => true,
            'name' => 'No Token Remember Register',
            'email' => 'notokenrememberregister@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ];

        // Act
        $response = $this->postJson('/api/email-register', $payload);

        // Assert: no token issued, so no cookie should be emitted
        $response->assertStatus(201);
        $this->assertNull($this->getCookieValue($response, 'nemesis_token'));

        $this->app['config']->set('authentication-kit.store_token_in_cookie', false);
        $this->refreshConfigService();
    }

    public function test_register_rejects_non_boolean_remember_me(): void
    {
        // Arrange: remember_me provided with an invalid type
        $payload = [
            'model_type' => TestUserMail::class,
            'with_token' => true,
            'remember_me' => 'yes-please',
            'name' => 'Invalid Remember Register',
            'email' => 'invalidrememberregister@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ];

        // Act
        $response = $this->postJson('/api/email-register', $payload);

        // Assert: validation rejects the payload
        $response->assertStatus(422);
    }

    public function test_register_uses_configured_cookie_duration_for_remember_me(): void
    {
        // Arrange: custom cookie duration applied when remember_me is true
        $this->app['config']->set('authentication-kit.store_token_in_cookie', true);
        $this->app['config']->set('authentication-kit.cookie_duration', 1440);
        $this->app['config']->set('nemesis.web.cookie_name', 'nemesis_token');
        $this->refreshConfigService();

        $payload = [
            'model_type' => TestUserMail::class,
            'with_token' => true,
            'remember_me' => true,
            'name' => 'Custom Duration Register',
            'email' => 'customdurationregister@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ];

        // Act
        $response = $this->postJson('/api/email-register', $payload);

        // Assert: expiration matches the custom 1440 minutes (one day)
        $response->assertStatus(201);

        $cookie = $this->getCookieObject($response, 'nemesis_token');
        $this->assertNotNull($cookie);

        $expectedExpiration = now()->addMinutes(1440)->getTimestamp();
        $this->assertEqualsWithDelta($expectedExpiration, $cookie->getExpiresTime(), 5);

        $this->app['config']->set('authentication-kit.store_token_in_cookie', false);
        $this->app['config']->set('authentication-kit.cookie_duration', 525600);
        $this->refreshConfigService();
    }
}
