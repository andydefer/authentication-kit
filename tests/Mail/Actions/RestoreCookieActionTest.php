<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Tests\Mail\Actions;

use AndyDefer\AuthenticationKit\Configs\AuthenticationKitConfig;
use AndyDefer\AuthenticationKit\Contracts\Configs\AuthenticationKitConfigInterface;
use AndyDefer\AuthenticationKit\Mail\Actions\EmailLoginAction;
use AndyDefer\AuthenticationKit\Mail\Actions\RestoreCookieAction;
use AndyDefer\AuthenticationKit\Mail\Requests\EmailLoginRequest;
use AndyDefer\AuthenticationKit\Mail\Requests\RestoreCookieRequest;
use AndyDefer\AuthenticationKit\Tests\IntegrationTestCase;
use AndyDefer\AuthenticationKit\Tests\Mail\Fixtures\Models\TestUserMail;
use AndyDefer\Nemesis\Models\NemesisToken;
use Illuminate\Testing\TestResponse;
use ReflectionClass;
use Symfony\Component\HttpFoundation\Cookie;

final class RestoreCookieActionTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Base configuration: cookie storage off by default, each test opts in.
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

        // Rebind the config service so it picks up runtime overrides.
        $this->app->singleton(
            AuthenticationKitConfigInterface::class,
            function ($app) {
                return new AuthenticationKitConfig(
                    $app['config']
                );
            }
        );

        $this->app['router']
            ->middleware(['validate.mail.authenticatable'])
            ->post('/api/restore-cookie', action_route(
                RestoreCookieRequest::class,
                RestoreCookieAction::class
            ));
    }

    /**
     * Register the login route used by `issuePlainToken()`.
     */
    protected function defineRoutes($router): void
    {
        $router->middleware(['validate.mail.authenticatable'])->post('/api/email-login', action_route(
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

    /**
     * Discard every cookie currently queued on the container's CookieJar.
     *
     * The jar is a long-lived singleton that accumulates cookies across all
     * calls made inside a single test. Assertions such as "no cookie was
     * emitted" would otherwise be polluted by cookies emitted by earlier
     * operations (typically the `login()` used to obtain the plain token).
     * Flushing the jar right before the action under test ensures that the
     * response only ever reflects cookies emitted by that action.
     */
    private function flushQueuedCookies(): void
    {
        $cookieJar = app('cookie');

        if (method_exists($cookieJar, 'flushQueuedCookies')) {
            $cookieJar->flushQueuedCookies();

            return;
        }

        $reflection = new ReflectionClass($cookieJar);
        $property = $reflection->getProperty('queued');
        $property->setAccessible(true);
        $property->setValue($cookieJar, []);
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

    private function getCookieValue(TestResponse $response, string $cookieName): ?string
    {
        return $this->getCookieObject($response, $cookieName)?->getValue();
    }

    /**
     * Issue a real login for the given user and return the plain token,
     * as the client would keep it (typically in localStorage).
     */
    private function issuePlainToken(TestUserMail $user, string $password = 'Password123!'): string
    {
        $response = $this->postJson('/api/email-login', [
            'model_type' => TestUserMail::class,
            'email' => $user->email,
            'password' => $password,
        ]);

        $response->assertStatus(200);

        return (string) $response->json('token');
    }

    // ============================================================================
    // SUCCESS
    // ============================================================================

    public function test_restore_cookie_returns_authenticatable_and_stores_cookie(): void
    {
        // Arrange: cookie storage on, existing user with a valid login token.
        $this->app['config']->set('authentication-kit.store_token_in_cookie', true);
        $this->refreshConfigService();

        $user = TestUserMail::create([
            'name' => 'Restore User',
            'email' => 'restore@example.com',
            'password' => bcrypt('Password123!'),
        ]);

        $plainToken = $this->issuePlainToken($user);
        $this->flushQueuedCookies();

        // Act
        $response = $this->postJson('/api/restore-cookie', [
            'model_type' => TestUserMail::class,
            'token' => $plainToken,
            'remember_me' => false,
        ]);

        // Assert: 200, cookie queued with the same identity.
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'message',
            'auth' => ['id', 'name', 'email', 'createdAt'],
        ]);
        $response->assertJson([
            'message' => 'Cookie restored successfully',
            'auth' => [
                'id' => $user->id,
                'name' => 'Restore User',
                'email' => 'restore@example.com',
            ],
        ]);

        $cookie = $this->getCookieObject($response, 'nemesis_token');
        $this->assertNotNull($cookie);
        $this->assertNotEmpty($cookie->getValue());
        $this->assertSame(0, $cookie->getExpiresTime());

        $this->app['config']->set('authentication-kit.store_token_in_cookie', false);
        $this->refreshConfigService();
    }

    public function test_restore_cookie_with_remember_me_true_issues_persistent_cookie(): void
    {
        // Arrange
        $this->app['config']->set('authentication-kit.store_token_in_cookie', true);
        $this->app['config']->set('authentication-kit.cookie_duration', 525600);
        $this->refreshConfigService();

        $user = TestUserMail::create([
            'name' => 'Remember Restore',
            'email' => 'remember-restore@example.com',
            'password' => bcrypt('Password123!'),
        ]);

        $plainToken = $this->issuePlainToken($user);
        $this->flushQueuedCookies();

        // Act
        $response = $this->postJson('/api/restore-cookie', [
            'model_type' => TestUserMail::class,
            'token' => $plainToken,
            'remember_me' => true,
        ]);

        // Assert: expiration matches the configured duration.
        $response->assertStatus(200);

        $cookie = $this->getCookieObject($response, 'nemesis_token');
        $this->assertNotNull($cookie);

        $expectedExpiration = now()->addMinutes(525600)->getTimestamp();
        $this->assertEqualsWithDelta($expectedExpiration, $cookie->getExpiresTime(), 5);

        $this->app['config']->set('authentication-kit.store_token_in_cookie', false);
        $this->app['config']->set('authentication-kit.cookie_duration', 525600);
        $this->refreshConfigService();
    }

    public function test_restore_cookie_without_remember_me_issues_session_cookie(): void
    {
        // Arrange
        $this->app['config']->set('authentication-kit.store_token_in_cookie', true);
        $this->refreshConfigService();

        $user = TestUserMail::create([
            'name' => 'Session Restore',
            'email' => 'session-restore@example.com',
            'password' => bcrypt('Password123!'),
        ]);

        $plainToken = $this->issuePlainToken($user);
        $this->flushQueuedCookies();

        // Act
        $response = $this->postJson('/api/restore-cookie', [
            'model_type' => TestUserMail::class,
            'token' => $plainToken,
        ]);

        // Assert
        $response->assertStatus(200);

        $cookie = $this->getCookieObject($response, 'nemesis_token');
        $this->assertNotNull($cookie);
        $this->assertSame(0, $cookie->getExpiresTime());

        $this->app['config']->set('authentication-kit.store_token_in_cookie', false);
        $this->refreshConfigService();
    }

    public function test_restore_cookie_uses_configured_cookie_name(): void
    {
        // Arrange
        $this->app['config']->set('authentication-kit.store_token_in_cookie', true);
        $this->app['config']->set('nemesis.web.cookie_name', 'custom_restore_cookie');
        $this->refreshConfigService();

        $user = TestUserMail::create([
            'name' => 'Custom Cookie Restore',
            'email' => 'custom-cookie-restore@example.com',
            'password' => bcrypt('Password123!'),
        ]);

        $plainToken = $this->issuePlainToken($user);
        $this->flushQueuedCookies();

        // Act
        $response = $this->postJson('/api/restore-cookie', [
            'model_type' => TestUserMail::class,
            'token' => $plainToken,
        ]);

        // Assert
        $response->assertStatus(200);

        $this->assertNotNull($this->getCookieValue($response, 'custom_restore_cookie'));
        $this->assertNull($this->getCookieValue($response, 'nemesis_token'));

        $this->app['config']->set('authentication-kit.store_token_in_cookie', false);
        $this->app['config']->set('nemesis.web.cookie_name', 'nemesis_token');
        $this->refreshConfigService();
    }

    public function test_restore_cookie_uses_configured_cookie_duration(): void
    {
        // Arrange
        $this->app['config']->set('authentication-kit.store_token_in_cookie', true);
        $this->app['config']->set('authentication-kit.cookie_duration', 1440);
        $this->refreshConfigService();

        $user = TestUserMail::create([
            'name' => 'Custom Duration Restore',
            'email' => 'custom-duration-restore@example.com',
            'password' => bcrypt('Password123!'),
        ]);

        $plainToken = $this->issuePlainToken($user);
        $this->flushQueuedCookies();

        // Act
        $response = $this->postJson('/api/restore-cookie', [
            'model_type' => TestUserMail::class,
            'token' => $plainToken,
            'remember_me' => true,
        ]);

        // Assert
        $response->assertStatus(200);

        $cookie = $this->getCookieObject($response, 'nemesis_token');
        $this->assertNotNull($cookie);

        $expectedExpiration = now()->addMinutes(1440)->getTimestamp();
        $this->assertEqualsWithDelta($expectedExpiration, $cookie->getExpiresTime(), 5);

        $this->app['config']->set('authentication-kit.store_token_in_cookie', false);
        $this->app['config']->set('authentication-kit.cookie_duration', 525600);
        $this->refreshConfigService();
    }

    public function test_restored_cookie_protects_web_route(): void
    {
        // Arrange
        $this->app['config']->set('authentication-kit.store_token_in_cookie', true);
        $this->refreshConfigService();

        $this->app['router']->middleware(['nemesis.web'])->get('/protected-restore', function () {
            return response()->json(['message' => 'Protected content']);
        });

        $user = TestUserMail::create([
            'name' => 'Protected Restore',
            'email' => 'protected-restore@example.com',
            'password' => bcrypt('Password123!'),
        ]);

        $plainToken = $this->issuePlainToken($user);
        $this->flushQueuedCookies();

        // Act
        $restoreResponse = $this->postJson('/api/restore-cookie', [
            'model_type' => TestUserMail::class,
            'token' => $plainToken,
            'remember_me' => true,
        ]);

        $restoreResponse->assertStatus(200);

        $cookieValue = $this->getCookieValue($restoreResponse, 'nemesis_token');
        $this->assertNotNull($cookieValue);

        // Assert: the restored cookie authenticates the protected route.
        $protectedResponse = $this->withUnencryptedCookie('nemesis_token', $cookieValue)
            ->get('/protected-restore');

        $protectedResponse->assertStatus(200);
        $protectedResponse->assertJson(['message' => 'Protected content']);

        $this->app['config']->set('authentication-kit.store_token_in_cookie', false);
        $this->refreshConfigService();
    }

    // ============================================================================
    // FAILURE — VALIDATION
    // ============================================================================

    public function test_restore_cookie_returns_error_when_model_type_is_missing(): void
    {
        // Act
        $response = $this->postJson('/api/restore-cookie', [
            'token' => 'some-plain-token-value-abcdefghijklmnop',
        ]);

        // Assert: AbstractRequest intercepts and produces its own error shape.
        $response->assertStatus(400);
        $response->assertJson([
            'message' => 'model_type is required',
            'status' => 400,
            'errorCode' => 'MODEL_TYPE_REQUIRED',
        ]);
    }

    public function test_restore_cookie_returns_error_when_token_is_missing(): void
    {
        // Act
        $response = $this->postJson('/api/restore-cookie', [
            'model_type' => TestUserMail::class,
        ]);

        // Assert
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['token']);
    }

    public function test_restore_cookie_returns_error_when_token_is_too_short(): void
    {
        // Act
        $response = $this->postJson('/api/restore-cookie', [
            'model_type' => TestUserMail::class,
            'token' => 'short',
        ]);

        // Assert
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['token']);
    }

    public function test_restore_cookie_returns_error_when_model_type_does_not_exist(): void
    {
        // Act
        $response = $this->postJson('/api/restore-cookie', [
            'model_type' => 'NonExistentClass',
            'token' => 'some-plain-token-value-abcdefghijklmnop',
        ]);

        // Assert: the action returns a flat error payload, not validation errors.
        $response->assertStatus(422);
        $response->assertJson([
            'message' => 'Model NonExistentClass does not exist',
            'status' => 422,
            'errorCode' => 'MODEL_NOT_FOUND',
        ]);
    }

    public function test_restore_cookie_rejects_non_boolean_remember_me(): void
    {
        // Arrange
        $this->app['config']->set('authentication-kit.store_token_in_cookie', true);
        $this->refreshConfigService();

        $user = TestUserMail::create([
            'name' => 'Invalid Remember Restore',
            'email' => 'invalid-remember-restore@example.com',
            'password' => bcrypt('Password123!'),
        ]);

        $plainToken = $this->issuePlainToken($user);
        $this->flushQueuedCookies();

        // Act
        $response = $this->postJson('/api/restore-cookie', [
            'model_type' => TestUserMail::class,
            'token' => $plainToken,
            'remember_me' => 'yes-please',
        ]);

        // Assert
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['remember_me']);

        $this->app['config']->set('authentication-kit.store_token_in_cookie', false);
        $this->refreshConfigService();
    }

    // ============================================================================
    // FAILURE — TOKEN
    // ============================================================================

    public function test_restore_cookie_returns_error_for_invalid_token(): void
    {
        // Arrange
        $this->app['config']->set('authentication-kit.store_token_in_cookie', true);
        $this->refreshConfigService();

        TestUserMail::create([
            'name' => 'Invalid Token Restore',
            'email' => 'invalid-token-restore@example.com',
            'password' => bcrypt('Password123!'),
        ]);

        $this->flushQueuedCookies();

        // Act
        $response = $this->postJson('/api/restore-cookie', [
            'model_type' => TestUserMail::class,
            'token' => 'this-is-not-a-valid-plain-token-abcdefghij',
        ]);

        // Assert
        $response->assertStatus(401);
        $this->assertNull($this->getCookieValue($response, 'nemesis_token'));

        $this->app['config']->set('authentication-kit.store_token_in_cookie', false);
        $this->refreshConfigService();
    }

    public function test_restore_cookie_returns_error_for_revoked_token(): void
    {
        // Arrange
        $this->app['config']->set('authentication-kit.store_token_in_cookie', true);
        $this->refreshConfigService();

        $user = TestUserMail::create([
            'name' => 'Revoked Restore',
            'email' => 'revoked-restore@example.com',
            'password' => bcrypt('Password123!'),
        ]);

        $plainToken = $this->issuePlainToken($user);

        // Revoke all tokens for this user.
        NemesisToken::where('tokenable_type', TestUserMail::class)
            ->where('tokenable_id', $user->id)
            ->delete();

        $this->flushQueuedCookies();

        // Act
        $response = $this->postJson('/api/restore-cookie', [
            'model_type' => TestUserMail::class,
            'token' => $plainToken,
        ]);

        // Assert
        $response->assertStatus(401);
        $this->assertNull($this->getCookieValue($response, 'nemesis_token'));

        $this->app['config']->set('authentication-kit.store_token_in_cookie', false);
        $this->refreshConfigService();
    }

    public function test_restore_cookie_returns_error_for_expired_token(): void
    {
        // Arrange
        $this->app['config']->set('authentication-kit.store_token_in_cookie', true);
        $this->refreshConfigService();

        $user = TestUserMail::create([
            'name' => 'Expired Restore',
            'email' => 'expired-restore@example.com',
            'password' => bcrypt('Password123!'),
        ]);

        $plainToken = $this->issuePlainToken($user);

        $token = NemesisToken::where('tokenable_type', TestUserMail::class)
            ->where('tokenable_id', $user->id)
            ->first();

        $this->assertNotNull($token);
        $token->expires_at = now()->subMinutes(5);
        $token->save();

        $this->flushQueuedCookies();

        // Act
        $response = $this->postJson('/api/restore-cookie', [
            'model_type' => TestUserMail::class,
            'token' => $plainToken,
        ]);

        // Assert
        $response->assertStatus(401);
        $this->assertNull($this->getCookieValue($response, 'nemesis_token'));

        $this->app['config']->set('authentication-kit.store_token_in_cookie', false);
        $this->refreshConfigService();
    }

    public function test_restore_cookie_returns_error_when_store_token_in_cookie_disabled(): void
    {
        // Arrange: cookie storage off, but a valid token still exists.
        $this->app['config']->set('authentication-kit.store_token_in_cookie', false);
        $this->refreshConfigService();

        $user = TestUserMail::create([
            'name' => 'No Cookie Restore',
            'email' => 'no-cookie-restore@example.com',
            'password' => bcrypt('Password123!'),
        ]);

        $plainToken = $this->issuePlainToken($user);
        $this->flushQueuedCookies();

        // Act
        $response = $this->postJson('/api/restore-cookie', [
            'model_type' => TestUserMail::class,
            'token' => $plainToken,
            'remember_me' => true,
        ]);

        // Assert
        $response->assertStatus(401);
        $this->assertNull($this->getCookieValue($response, 'nemesis_token'));
    }

    public function test_restore_cookie_returns_error_when_authenticatable_is_missing(): void
    {
        // Arrange
        $this->app['config']->set('authentication-kit.store_token_in_cookie', true);
        $this->refreshConfigService();

        $user = TestUserMail::create([
            'name' => 'Missing Restore',
            'email' => 'missing-restore@example.com',
            'password' => bcrypt('Password123!'),
        ]);

        $plainToken = $this->issuePlainToken($user);

        // Hard delete the user while leaving the token intact.
        $user->forceDelete();

        $this->flushQueuedCookies();

        // Act
        $response = $this->postJson('/api/restore-cookie', [
            'model_type' => TestUserMail::class,
            'token' => $plainToken,
        ]);

        // Assert
        $response->assertStatus(401);
        $this->assertNull($this->getCookieValue($response, 'nemesis_token'));

        $this->app['config']->set('authentication-kit.store_token_in_cookie', false);
        $this->refreshConfigService();
    }

    public function test_restore_cookie_returns_error_for_mismatched_model_type(): void
    {
        // Arrange
        $this->app['config']->set('authentication-kit.store_token_in_cookie', true);
        $this->refreshConfigService();

        $user = TestUserMail::create([
            'name' => 'Mismatch Restore',
            'email' => 'mismatch-restore@example.com',
            'password' => bcrypt('Password123!'),
        ]);

        $plainToken = $this->issuePlainToken($user);

        // Force a wrong tokenable_type in DB.
        $token = NemesisToken::where('tokenable_type', TestUserMail::class)
            ->where('tokenable_id', $user->id)
            ->first();

        $this->assertNotNull($token);
        $token->tokenable_type = 'App\\Models\\OtherUser';
        $token->save();

        $this->flushQueuedCookies();

        // Act
        $response = $this->postJson('/api/restore-cookie', [
            'model_type' => TestUserMail::class,
            'token' => $plainToken,
        ]);

        // Assert
        $response->assertStatus(401);
        $this->assertNull($this->getCookieValue($response, 'nemesis_token'));

        $this->app['config']->set('authentication-kit.store_token_in_cookie', false);
        $this->refreshConfigService();
    }
}
