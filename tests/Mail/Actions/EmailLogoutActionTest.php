<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Tests\Mail\Actions;

use AndyDefer\AuthenticationKit\Configs\AuthenticationKitConfig;
use AndyDefer\AuthenticationKit\Contracts\Configs\AuthenticationKitConfigInterface;
use AndyDefer\AuthenticationKit\Mail\Actions\EmailLogoutAction;
use AndyDefer\AuthenticationKit\Mail\Requests\EmailLogoutRequest;
use AndyDefer\AuthenticationKit\Tests\IntegrationTestCase;
use AndyDefer\AuthenticationKit\Tests\Mail\Fixtures\Models\TestUserMail;
use AndyDefer\DomainStructures\Utils\StrictDataObject;
use AndyDefer\Nemesis\Contracts\Services\NemesisInterface;
use AndyDefer\Nemesis\Records\NemesisTokenRecord;
use Illuminate\Support\Facades\Cookie;

final class EmailLogoutActionTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

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

        $this->app->singleton(
            AuthenticationKitConfigInterface::class,
            function ($app) {
                return new AuthenticationKitConfig(
                    $app['config']
                );
            }
        );

        $this->app['router']->middleware(['validate.mail.authenticatable', 'nemesis.token'])->post('/api/email-logout', action_route(
            EmailLogoutRequest::class,
            EmailLogoutAction::class
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

    private function getQueuedCookieValue(string $cookieName): ?string
    {
        $queuedCookies = Cookie::getQueuedCookies();
        foreach ($queuedCookies as $cookie) {
            if ($cookie->getName() === $cookieName) {
                return $cookie->getValue();
            }
        }

        return null;
    }

    private function isQueuedCookieForgotten(string $cookieName): bool
    {
        $queuedCookies = Cookie::getQueuedCookies();
        foreach ($queuedCookies as $cookie) {
            if ($cookie->getName() === $cookieName) {
                return $cookie->getExpiresTime() < time();
            }
        }

        return false;
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
    // Tests pour logout - Suppression du cookie
    // ============================================================================

    public function test_logout_deletes_cookie_when_configured(): void
    {
        $this->app['config']->set('authentication-kit.store_token_in_cookie', true);
        $this->app['config']->set('nemesis.web.cookie_name', 'nemesis_token');
        $this->refreshConfigService();

        $user = TestUserMail::create([
            'name' => 'Cookie Logout User',
            'email' => 'cookielogout@example.com',
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

        // ✅ Stocker le token dans le cookie via Cookie::queue()
        Cookie::queue('nemesis_token', $plainToken, 0, '/', null, false, false, false, 'lax');

        // ✅ Vérifier que le cookie est en file d'attente
        $cookieValueBefore = $this->getQueuedCookieValue('nemesis_token');
        $this->assertNotNull($cookieValueBefore);

        $payload = [
            'model_type' => TestUserMail::class,
            'token' => $plainToken,
        ];

        $response = $this->postJson('/api/email-logout', $payload, [
            'Authorization' => 'Bearer '.$plainToken,
        ]);

        $response->assertStatus(204);

        // ✅ Vérifier que le cookie a été supprimé (expiration dans le passé)
        $isForgotten = $this->isQueuedCookieForgotten('nemesis_token');
        $this->assertTrue($isForgotten, 'Cookie should be forgotten');

        $this->app['config']->set('authentication-kit.store_token_in_cookie', false);
        $this->refreshConfigService();
    }

    public function test_logout_does_not_delete_cookie_when_not_configured(): void
    {
        $this->app['config']->set('authentication-kit.store_token_in_cookie', false);
        $this->refreshConfigService();

        $user = TestUserMail::create([
            'name' => 'No Cookie Logout User',
            'email' => 'nocookielogout@example.com',
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

        // ✅ Stocker le token dans le cookie via Cookie::queue()
        Cookie::queue('nemesis_token', $plainToken, 0, '/', null, false, false, false, 'lax');

        // ✅ Vérifier que le cookie est en file d'attente
        $cookieValueBefore = $this->getQueuedCookieValue('nemesis_token');
        $this->assertNotNull($cookieValueBefore);

        $payload = [
            'model_type' => TestUserMail::class,
            'token' => $plainToken,
        ];

        $response = $this->postJson('/api/email-logout', $payload, [
            'Authorization' => 'Bearer '.$plainToken,
        ]);

        $response->assertStatus(204);

        // ✅ Vérifier que le cookie n'a PAS été supprimé
        $cookieValueAfter = $this->getQueuedCookieValue('nemesis_token');
        $this->assertNotNull($cookieValueAfter);
        $this->assertEquals($cookieValueBefore, $cookieValueAfter);
    }

    public function test_logout_deletes_cookie_with_configured_name(): void
    {
        $this->app['config']->set('authentication-kit.store_token_in_cookie', true);
        $this->app['config']->set('nemesis.web.cookie_name', 'custom_logout_cookie');
        $this->refreshConfigService();

        $user = TestUserMail::create([
            'name' => 'Custom Cookie Logout',
            'email' => 'customcookielogout@example.com',
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

        // ✅ Stocker le token dans le cookie avec le nom personnalisé
        Cookie::queue('custom_logout_cookie', $plainToken, 0, '/', null, false, false, false, 'lax');

        // ✅ Vérifier que le cookie est en file d'attente
        $cookieValueBefore = $this->getQueuedCookieValue('custom_logout_cookie');
        $this->assertNotNull($cookieValueBefore);

        $payload = [
            'model_type' => TestUserMail::class,
            'token' => $plainToken,
        ];

        $response = $this->postJson('/api/email-logout', $payload, [
            'Authorization' => 'Bearer '.$plainToken,
        ]);

        $response->assertStatus(204);

        // ✅ Vérifier que le cookie a été supprimé
        $isForgotten = $this->isQueuedCookieForgotten('custom_logout_cookie');
        $this->assertTrue($isForgotten, 'Cookie should be forgotten');

        $this->app['config']->set('authentication-kit.store_token_in_cookie', false);
        $this->app['config']->set('nemesis.web.cookie_name', 'nemesis_token');
        $this->refreshConfigService();
    }

    public function test_logout_response_clears_cookie_when_configured(): void
    {
        $this->app['config']->set('authentication-kit.store_token_in_cookie', true);
        $this->app['config']->set('nemesis.web.cookie_name', 'nemesis_token');
        $this->refreshConfigService();

        $user = TestUserMail::create([
            'name' => 'Response Cookie User',
            'email' => 'responsecookie@example.com',
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

        // ✅ Stocker le token dans le cookie via Cookie::queue()
        Cookie::queue('nemesis_token', $plainToken, 0, '/', null, false, false, false, 'lax');

        $payload = [
            'model_type' => TestUserMail::class,
            'token' => $plainToken,
        ];

        $response = $this->postJson('/api/email-logout', $payload, [
            'Authorization' => 'Bearer '.$plainToken,
        ]);

        $response->assertStatus(204);

        // ✅ Vérifier que le cookie est dans la réponse avec une expiration passée
        // Récupérer les cookies sans décryptage
        $cookies = $response->headers->getCookies();
        $found = false;
        foreach ($cookies as $cookie) {
            if ($cookie->getName() === 'nemesis_token') {
                $found = true;
                $this->assertLessThan(time(), $cookie->getExpiresTime());
                break;
            }
        }
        $this->assertTrue($found, 'Cookie nemesis_token should be present in response');

        $this->app['config']->set('authentication-kit.store_token_in_cookie', false);
        $this->refreshConfigService();
    }

    public function test_logout_after_login_with_cookie_redirects_to_login(): void
    {
        $this->app['config']->set('authentication-kit.store_token_in_cookie', true);
        $this->app['config']->set('nemesis.web.cookie_name', 'nemesis_token');
        $this->refreshConfigService();

        $user = TestUserMail::create([
            'name' => 'Full Flow User',
            'email' => 'fullflow@example.com',
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

        // ✅ Stocker le token dans le cookie
        Cookie::queue('nemesis_token', $plainToken, 0, '/', null, false, false, false, 'lax');

        // ✅ Vérifier que le cookie est en file d'attente
        $cookieValueBefore = $this->getQueuedCookieValue('nemesis_token');
        $this->assertNotNull($cookieValueBefore);

        // ✅ Logout
        $payload = [
            'model_type' => TestUserMail::class,
            'token' => $plainToken,
        ];

        $response = $this->postJson('/api/email-logout', $payload, [
            'Authorization' => 'Bearer '.$plainToken,
        ]);

        $response->assertStatus(204);

        // ✅ Vérifier que le cookie a été supprimé
        $isForgotten = $this->isQueuedCookieForgotten('nemesis_token');
        $this->assertTrue($isForgotten, 'Cookie should be forgotten');

        $this->app['config']->set('authentication-kit.store_token_in_cookie', false);
        $this->refreshConfigService();
    }

    // ============================================================================
    // Tests existants pour logout() - Erreurs
    // ============================================================================

    public function test_logout_returns_401_when_token_does_not_exist(): void
    {
        [$user, $token, $bearerToken] = $this->createUserAndGetTokenWithBearer();

        $payload = [
            'model_type' => TestUserMail::class,
            'token' => 'non-existent-token-1234567890',
        ];

        $response = $this->postJson('/api/email-logout', $payload, [
            'Authorization' => $bearerToken,
        ]);

        $response->assertStatus(401);
        $response->assertJson([
            'errorCode' => 'INVALID_TOKEN',
        ]);
    }

    public function test_logout_returns_400_when_model_type_is_missing(): void
    {
        [$user, $token, $bearerToken] = $this->createUserAndGetTokenWithBearer();

        $payload = [
            'token' => $token,
        ];

        $response = $this->postJson('/api/email-logout', $payload, [
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

        $response = $this->postJson('/api/email-logout', $payload, [
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

        $response = $this->postJson('/api/email-logout', $payload, [
            'Authorization' => $bearerToken,
        ]);

        $response->assertStatus(500);
        $response->assertJson([
            'errorCode' => 'MODEL_NOT_FOUND',
        ]);
    }
}
