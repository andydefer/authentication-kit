<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Tests\Integration\Google\Actions;

use AndyDefer\AuthenticationKit\Configs\AuthenticationKitConfig;
use AndyDefer\AuthenticationKit\Contracts\Configs\AuthenticationKitConfigInterface;
use AndyDefer\AuthenticationKit\Google\Actions\GoogleCallbackAction;
use AndyDefer\AuthenticationKit\Google\Contracts\GoogleStateStoreInterface;
use AndyDefer\AuthenticationKit\Google\Datas\GoogleIdTokenClaimsData;
use AndyDefer\AuthenticationKit\Google\Enums\OperationType;
use AndyDefer\AuthenticationKit\Google\Exceptions\GoogleOAuthException;
use AndyDefer\AuthenticationKit\Google\Records\GoogleStateRecord;
use AndyDefer\AuthenticationKit\Google\Requests\GoogleCallbackRequest;
use AndyDefer\AuthenticationKit\Google\Services\GoogleIdTokenVerifier;
use AndyDefer\AuthenticationKit\Google\Services\GoogleOAuthService;
use AndyDefer\AuthenticationKit\Google\Services\GooglePkceGenerator;
use AndyDefer\AuthenticationKit\Tests\IntegrationTestCase;
use AndyDefer\PhpClient\Clients\ClientService;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use Mockery;

final class GoogleCallbackActionTest extends IntegrationTestCase
{
    private MockHandler $mockHandler;

    private GoogleIdTokenVerifier $idTokenVerifierMock;

    protected function setUp(): void
    {
        parent::setUp();

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

        $this->app->singleton(
            AuthenticationKitConfigInterface::class,
            function ($app) {
                return new AuthenticationKitConfig($app['config']);
            }
        );

        $this->app->forgetInstance(AuthenticationKitConfig::class);
        $this->app->singleton(
            AuthenticationKitConfig::class,
            function ($app) {
                return new AuthenticationKitConfig($app['config']);
            }
        );

        $this->mockHandler = new MockHandler;
        $handlerStack = HandlerStack::create($this->mockHandler);
        $guzzle = new GuzzleClient(['handler' => $handlerStack]);

        $this->app->forgetInstance(ClientService::class);
        $this->app->instance(ClientService::class, new ClientService($guzzle));

        $this->idTokenVerifierMock = Mockery::mock(GoogleIdTokenVerifier::class);
        $this->app->instance(GoogleIdTokenVerifier::class, $this->idTokenVerifierMock);

        $this->app->forgetInstance(GoogleStateStoreInterface::class);
        $this->app->forgetInstance(GooglePkceGenerator::class);
        $this->app->forgetInstance(GoogleOAuthService::class);
        $this->app->forgetInstance(GoogleCallbackAction::class);

        $this->app['router']->get('/api/google-callback', action_route(
            GoogleCallbackRequest::class,
            GoogleCallbackAction::class
        ));
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // ============================================================================
    // Helpers
    // ============================================================================

    private function prepareValidState(
        OperationType $operation = OperationType::LOGIN,
    ): void {
        /** @var GoogleStateStoreInterface $store */
        $store = $this->app->make(GoogleStateStoreInterface::class);

        $store->put('valid-state', new GoogleStateRecord(
            code_verifier: 'verifier-xyz',
            nonce: 'nonce-xyz',
            expires_at: time() + 600,
            operation: $operation,
        ), 600);
    }

    private function queueTokenExchangeSuccess(): void
    {
        $this->mockHandler->append(new GuzzleResponse(
            200,
            ['Content-Type' => 'application/json'],
            json_encode([
                'id_token' => 'header.payload.signature',
                'access_token' => 'access-token-xyz',
                'refresh_token' => 'refresh-token-xyz',
                'expires_in' => 3600,
                'token_type' => 'Bearer',
                'scope' => 'openid email profile',
            ])
        ));
    }

    private function configureValidIdTokenVerifier(): void
    {
        $claims = GoogleIdTokenClaimsData::from([
            'sub' => 'sub-123',
            'email' => 'user@example.com',
            'emailVerified' => true,
            'iss' => 'https://accounts.google.com',
            'aud' => 'test-client-id.apps.googleusercontent.com',
            'exp' => time() + 3600,
            'iat' => time(),
            'nonce' => 'nonce-xyz',
        ]);

        $this->idTokenVerifierMock
            ->shouldReceive('verify')
            ->once()
            ->with('header.payload.signature', 'nonce-xyz')
            ->andReturn($claims);
    }

    // ============================================================================
    // Tests succès
    // ============================================================================

    public function test_callback_returns_google_tokens_on_valid_flow(): void
    {
        $this->prepareValidState();
        $this->queueTokenExchangeSuccess();
        $this->configureValidIdTokenVerifier();

        $response = $this->getJson('/api/google-callback?'.http_build_query([
            'code' => 'auth-code',
            'state' => 'valid-state',
        ]));

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'idToken',
            'accessToken',
            'expiresIn',
            'tokenType',
            'scope',
            'claims',
            'operation',
        ]);
    }

    public function test_callback_returns_correct_token_values(): void
    {
        $this->prepareValidState();
        $this->queueTokenExchangeSuccess();
        $this->configureValidIdTokenVerifier();

        $response = $this->getJson('/api/google-callback?'.http_build_query([
            'code' => 'auth-code',
            'state' => 'valid-state',
        ]));

        $response->assertStatus(200);
        $response->assertJson([
            'idToken' => 'header.payload.signature',
            'accessToken' => 'access-token-xyz',
            'expiresIn' => 3600,
            'tokenType' => 'Bearer',
            'scope' => 'openid email profile',
        ]);
    }

    public function test_callback_returns_verified_claims(): void
    {
        $this->prepareValidState();
        $this->queueTokenExchangeSuccess();
        $this->configureValidIdTokenVerifier();

        $response = $this->getJson('/api/google-callback?'.http_build_query([
            'code' => 'auth-code',
            'state' => 'valid-state',
        ]));

        $response->assertStatus(200);
        $response->assertJsonPath('claims.sub', 'sub-123');
        $response->assertJsonPath('claims.email', 'user@example.com');
        $response->assertJsonPath('claims.emailVerified', true);
    }

    public function test_callback_returns_operation_login(): void
    {
        $this->prepareValidState(OperationType::LOGIN);
        $this->queueTokenExchangeSuccess();
        $this->configureValidIdTokenVerifier();

        $response = $this->getJson('/api/google-callback?'.http_build_query([
            'code' => 'auth-code',
            'state' => 'valid-state',
        ]));

        $response->assertStatus(200);
        $response->assertJsonPath('operation', OperationType::LOGIN->value);
    }

    public function test_callback_returns_operation_register(): void
    {
        $this->prepareValidState(OperationType::REGISTER);
        $this->queueTokenExchangeSuccess();
        $this->configureValidIdTokenVerifier();

        $response = $this->getJson('/api/google-callback?'.http_build_query([
            'code' => 'auth-code',
            'state' => 'valid-state',
        ]));

        $response->assertStatus(200);
        $response->assertJsonPath('operation', OperationType::REGISTER->value);
    }

    // ============================================================================
    // Tests d'erreur — Google fournit une erreur
    // ============================================================================

    public function test_callback_returns_error_when_google_returns_error(): void
    {
        $response = $this->getJson('/api/google-callback?'.http_build_query([
            'state' => 'any',
            'error' => 'access_denied',
            'error_description' => 'User denied access',
        ]));

        $response->assertStatus(400);
        $response->assertJson([
            'errorCode' => 'GOOGLE_PROVIDER_ERROR',
        ]);
    }

    public function test_callback_returns_error_when_user_denies_access(): void
    {
        $response = $this->getJson('/api/google-callback?'.http_build_query([
            'state' => 'any',
            'error' => 'access_denied',
            'error_description' => 'The user denied the request',
        ]));

        $response->assertStatus(400);
        $response->assertJsonPath('errorCode', 'GOOGLE_PROVIDER_ERROR');
        $response->assertJsonPath('message', 'The user denied the request');
    }

    // ============================================================================
    // Tests d'erreur — state invalide
    // ============================================================================

    public function test_callback_returns_error_when_state_is_unknown(): void
    {
        $response = $this->getJson('/api/google-callback?'.http_build_query([
            'code' => 'auth-code',
            'state' => 'unknown-state',
        ]));

        $response->assertStatus(500);
        $response->assertJson([
            'errorCode' => 'GOOGLE_CALLBACK_ERROR',
        ]);
    }

    public function test_callback_returns_error_when_state_is_expired(): void
    {
        /** @var GoogleStateStoreInterface $store */
        $store = $this->app->make(GoogleStateStoreInterface::class);

        $store->put('expired-state', new GoogleStateRecord(
            code_verifier: 'v',
            nonce: 'n',
            expires_at: time() - 10,
            operation: OperationType::LOGIN,
        ), 600);

        $response = $this->getJson('/api/google-callback?'.http_build_query([
            'code' => 'auth-code',
            'state' => 'expired-state',
        ]));

        $response->assertStatus(500);
        $response->assertJson([
            'errorCode' => 'GOOGLE_CALLBACK_ERROR',
        ]);
    }

    // ============================================================================
    // Tests d'erreur — validation
    // ============================================================================

    public function test_callback_returns_error_when_code_is_missing(): void
    {
        $response = $this->getJson('/api/google-callback?'.http_build_query([
            'state' => 'valid-state',
        ]));

        $response->assertStatus(422);
    }

    public function test_callback_returns_error_when_state_is_missing(): void
    {
        $response = $this->getJson('/api/google-callback?'.http_build_query([
            'code' => 'auth-code',
        ]));

        $response->assertStatus(422);
    }

    // ============================================================================
    // Tests d'erreur — token exchange Google
    // ============================================================================

    public function test_callback_returns_error_when_google_token_exchange_fails(): void
    {
        $this->prepareValidState();

        $this->mockHandler->append(new GuzzleResponse(
            400,
            ['Content-Type' => 'application/json'],
            json_encode([
                'error' => 'invalid_grant',
                'error_description' => 'Authorization code has expired',
            ])
        ));

        $this->idTokenVerifierMock
            ->shouldReceive('verify')
            ->never();

        $response = $this->getJson('/api/google-callback?'.http_build_query([
            'code' => 'expired-code',
            'state' => 'valid-state',
        ]));

        $response->assertStatus(500);
        $response->assertJson([
            'errorCode' => 'GOOGLE_CALLBACK_ERROR',
        ]);
    }

    // ============================================================================
    // Tests d'erreur — vérification id_token
    // ============================================================================

    public function test_callback_returns_error_when_id_token_verification_fails(): void
    {
        $this->prepareValidState();
        $this->queueTokenExchangeSuccess();

        $this->idTokenVerifierMock
            ->shouldReceive('verify')
            ->once()
            ->with('header.payload.signature', 'nonce-xyz')
            ->andThrow(GoogleOAuthException::idTokenSignatureInvalid());

        $response = $this->getJson('/api/google-callback?'.http_build_query([
            'code' => 'auth-code',
            'state' => 'valid-state',
        ]));

        $response->assertStatus(500);
        $response->assertJson([
            'errorCode' => 'GOOGLE_CALLBACK_ERROR',
        ]);
    }

    public function test_callback_returns_error_when_email_is_not_verified(): void
    {
        $this->prepareValidState();
        $this->queueTokenExchangeSuccess();

        $this->idTokenVerifierMock
            ->shouldReceive('verify')
            ->once()
            ->with('header.payload.signature', 'nonce-xyz')
            ->andThrow(GoogleOAuthException::emailNotVerified());

        $response = $this->getJson('/api/google-callback?'.http_build_query([
            'code' => 'auth-code',
            'state' => 'valid-state',
        ]));

        $response->assertStatus(500);
        $response->assertJson([
            'errorCode' => 'GOOGLE_CALLBACK_ERROR',
        ]);
    }

    // ============================================================================
    // Tests d'unicité du state (anti-replay)
    // ============================================================================

    public function test_callback_consumes_state_so_it_cannot_be_replayed(): void
    {
        $this->prepareValidState();
        $this->queueTokenExchangeSuccess();
        $this->configureValidIdTokenVerifier();

        $first = $this->getJson('/api/google-callback?'.http_build_query([
            'code' => 'auth-code',
            'state' => 'valid-state',
        ]));

        $first->assertStatus(200);

        $second = $this->getJson('/api/google-callback?'.http_build_query([
            'code' => 'auth-code',
            'state' => 'valid-state',
        ]));

        $second->assertStatus(500);
        $second->assertJson(['errorCode' => 'GOOGLE_CALLBACK_ERROR']);
    }
}
