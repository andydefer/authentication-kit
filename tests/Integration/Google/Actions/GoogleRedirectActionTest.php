<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Tests\Integration\Google\Actions;

use AndyDefer\AuthenticationKit\Configs\AuthenticationKitConfig;
use AndyDefer\AuthenticationKit\Contracts\Configs\AuthenticationKitConfigInterface;
use AndyDefer\AuthenticationKit\Google\Actions\GoogleRedirectAction;
use AndyDefer\AuthenticationKit\Google\Contracts\GoogleStateStoreInterface;
use AndyDefer\AuthenticationKit\Google\Requests\GoogleRedirectRequest;
use AndyDefer\AuthenticationKit\Google\Services\GoogleOAuthService;
use AndyDefer\AuthenticationKit\Google\Services\GooglePkceGenerator;
use AndyDefer\AuthenticationKit\Tests\IntegrationTestCase;

final class GoogleRedirectActionTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // ✅ Définir la configuration
        $this->app['config']->set('authentication-kit', [
            'token_name' => 'authentication-kit',
            'password_reset_rate_limit' => 3,
            'email_verification_rate_limit' => 5,
            'email_update_rate_limit' => 3,
            'two_factor_rate_limit' => 3,
            'store_token_in_cookie' => false,
            'cookie_duration' => 525600,

            'google' => [
                'client_id' => 'test-client-id.apps.googleusercontent.com',
                'client_secret' => 'test-client-secret',
                'redirect_uri' => 'https://app.test/auth/google/callback',

                'authorization_endpoint' => 'https://accounts.google.com/o/oauth2/v2/auth',
                'token_endpoint' => 'https://oauth2.googleapis.com/token',
                'jwks_endpoint' => 'https://www.googleapis.com/oauth2/v3/certs',

                'scopes' => ['openid', 'email', 'profile'],

                'state_lifetime' => 600,
                'pkce_verifier_length' => 64,
            ],
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

        // ✅ Reconstruire les services dépendants de la config
        $this->app->forgetInstance(AuthenticationKitConfig::class);
        $this->app->singleton(
            AuthenticationKitConfig::class,
            function ($app) {
                return new AuthenticationKitConfig(
                    $app['config']
                );
            }
        );

        $this->app->forgetInstance(GooglePkceGenerator::class);
        $this->app->forgetInstance(GoogleStateStoreInterface::class);
        $this->app->forgetInstance(GoogleOAuthService::class);
        $this->app->forgetInstance(GoogleRedirectAction::class);

        // ✅ Enregistrer la route
        $this->app['router']->post('/api/google-redirect', action_route(
            GoogleRedirectRequest::class,
            GoogleRedirectAction::class
        ));
    }

    // ============================================================================
    // Tests succès
    // ============================================================================

    public function test_redirect_returns_google_authorization_url(): void
    {
        $payload = [
            'state' => '',
            'code_challenge' => '',
            'nonce' => '',
        ];

        $response = $this->postJson('/api/google-redirect', $payload);

        $response->assertStatus(200);
        $response->assertJsonStructure(['url']);
    }

    public function test_redirect_url_starts_with_google_authorization_endpoint(): void
    {
        $payload = [
            'state' => '',
            'code_challenge' => '',
            'nonce' => '',
        ];

        $response = $this->postJson('/api/google-redirect', $payload);

        $response->assertStatus(200);

        $url = $response->json('url');
        $this->assertStringStartsWith(
            'https://accounts.google.com/o/oauth2/v2/auth',
            $url
        );
    }

    public function test_redirect_url_includes_client_id(): void
    {
        $payload = [
            'state' => '',
            'code_challenge' => '',
            'nonce' => '',
        ];

        $response = $this->postJson('/api/google-redirect', $payload);

        $url = $response->json('url');
        $this->assertStringContainsString(
            'client_id=test-client-id.apps.googleusercontent.com',
            $url
        );
    }

    public function test_redirect_url_includes_redirect_uri(): void
    {
        $payload = [
            'state' => '',
            'code_challenge' => '',
            'nonce' => '',
        ];

        $response = $this->postJson('/api/google-redirect', $payload);

        $url = $response->json('url');
        $this->assertStringContainsString(
            'redirect_uri='.urlencode('https://app.test/auth/google/callback'),
            $url
        );
    }

    public function test_redirect_url_includes_pkce_challenge(): void
    {
        $payload = [
            'state' => '',
            'code_challenge' => '',
            'nonce' => '',
        ];

        $response = $this->postJson('/api/google-redirect', $payload);

        $url = $response->json('url');
        $this->assertStringContainsString('code_challenge=', $url);
        $this->assertStringContainsString('code_challenge_method=S256', $url);
    }

    public function test_redirect_url_includes_state(): void
    {
        $payload = [
            'state' => '',
            'code_challenge' => '',
            'nonce' => '',
        ];

        $response = $this->postJson('/api/google-redirect', $payload);

        $url = $response->json('url');
        $this->assertStringContainsString('state=', $url);
    }

    public function test_redirect_url_includes_nonce(): void
    {
        $payload = [
            'state' => '',
            'code_challenge' => '',
            'nonce' => '',
        ];

        $response = $this->postJson('/api/google-redirect', $payload);

        $url = $response->json('url');
        $this->assertStringContainsString('nonce=', $url);
    }

    public function test_redirect_url_includes_configured_scopes(): void
    {
        $payload = [
            'state' => '',
            'code_challenge' => '',
            'nonce' => '',
        ];

        $response = $this->postJson('/api/google-redirect', $payload);

        $url = $response->json('url');
        $this->assertStringContainsString('scope=openid%20email%20profile', $url);
    }

    public function test_redirect_url_includes_response_type_code(): void
    {
        $payload = [
            'state' => '',
            'code_challenge' => '',
            'nonce' => '',
        ];

        $response = $this->postJson('/api/google-redirect', $payload);

        $url = $response->json('url');
        $this->assertStringContainsString('response_type=code', $url);
    }

    // ============================================================================
    // Tests des paramètres passés
    // ============================================================================

    public function test_redirect_uses_provided_state(): void
    {
        $payload = [
            'state' => 'my-custom-state',
            'code_challenge' => '',
            'nonce' => '',
        ];

        $response = $this->postJson('/api/google-redirect', $payload);

        $url = $response->json('url');
        $this->assertStringContainsString('state=my-custom-state', $url);
    }

    public function test_redirect_uses_provided_nonce(): void
    {
        $payload = [
            'state' => '',
            'code_challenge' => '',
            'nonce' => 'my-custom-nonce',
        ];

        $response = $this->postJson('/api/google-redirect', $payload);

        $url = $response->json('url');
        $this->assertStringContainsString('nonce=my-custom-nonce', $url);
    }

    // ============================================================================
    // Tests d'erreur
    // ============================================================================

    public function test_redirect_handles_empty_payload_gracefully(): void
    {
        $payload = [];

        $response = $this->postJson('/api/google-redirect', $payload);

        // Even with empty payload, the service must generate state and nonce
        $response->assertStatus(200);
        $response->assertJsonStructure(['url']);
    }

    public function test_redirect_generates_unique_state_on_each_call(): void
    {
        $payload = [];

        $first = $this->postJson('/api/google-redirect', $payload)->json('url');
        $second = $this->postJson('/api/google-redirect', $payload)->json('url');

        $this->assertNotSame($first, $second);
    }

    public function test_redirect_generates_unique_nonce_on_each_call(): void
    {
        $payload = [];

        $first = $this->postJson('/api/google-redirect', $payload)->json('url');
        $second = $this->postJson('/api/google-redirect', $payload)->json('url');

        // Extract nonce from both URLs and assert they differ
        preg_match('/nonce=([^&]+)/', $first, $matchesFirst);
        preg_match('/nonce=([^&]+)/', $second, $matchesSecond);

        $this->assertNotSame($matchesFirst[1] ?? null, $matchesSecond[1] ?? null);
    }

    public function test_redirect_stores_state_for_later_use(): void
    {
        $payload = [
            'state' => 'stored-state',
            'code_challenge' => '',
            'nonce' => 'stored-nonce',
        ];

        $this->postJson('/api/google-redirect', $payload);

        /** @var GoogleStateStoreInterface $store */
        $store = $this->app->make(GoogleStateStoreInterface::class);

        $stateRecord = $store->pull('stored-state');

        $this->assertNotNull($stateRecord);
        $this->assertSame('stored-nonce', $stateRecord->nonce);
        $this->assertNotEmpty($stateRecord->code_verifier);
    }
}
