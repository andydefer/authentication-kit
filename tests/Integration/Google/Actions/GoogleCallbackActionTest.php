<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Tests\Integration\Google\Actions;

use AndyDefer\Actions\Enums\HttpResponseType;
use AndyDefer\AuthenticationKit\Configs\AuthenticationKitConfig;
use AndyDefer\AuthenticationKit\Contracts\Configs\AuthenticationKitConfigInterface;
use AndyDefer\AuthenticationKit\Google\Actions\GoogleCallbackAction;
use AndyDefer\AuthenticationKit\Google\Contracts\GoogleStateStoreInterface;
use AndyDefer\AuthenticationKit\Google\Datas\GoogleCallbackData;
use AndyDefer\AuthenticationKit\Google\Datas\GoogleIdTokenClaimsData;
use AndyDefer\AuthenticationKit\Google\Enums\OperationType;
use AndyDefer\AuthenticationKit\Google\Exceptions\GoogleOAuthException;
use AndyDefer\AuthenticationKit\Google\Records\GoogleCallbackRecord;
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
use Illuminate\Testing\TestResponse;
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

                'callback' => [
                    'type' => 'view',
                    'target' => 'auth.google-callback',
                ],
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

        // Enregistre un dossier de vues de test + la vue attendue
        $viewDir = sys_get_temp_dir().'/auth-kit-test-views-'.uniqid();
        mkdir($viewDir.'/auth', 0777, true);
        file_put_contents(
            $viewDir.'/auth/google-callback.blade.php',
            'OK'
        );
        $this->app['config']->set('view.paths', [$viewDir]);
        $this->app['view']->addLocation($viewDir);

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

        $this->app['router']->get('/google-callback', action_route(
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

    private function assertCallbackErrorView(TestResponse $response): void
    {
        $response->assertViewIs('auth.google-callback');
        $response->assertViewHas('error');
    }

    /**
     * Invokes the action directly, bypassing the HTTP request validation,
     * so we can inspect the ResponseFactory type without routing.
     */
    private function runAction(string $code, string $state)
    {
        $action = $this->app->make(GoogleCallbackAction::class);

        $record = GoogleCallbackRecord::from([
            'code' => $code,
            'state' => $state,
        ]);

        return $action->run($record);
    }

    // ============================================================================
    // Tests succès
    // ============================================================================

    public function test_callback_renders_configured_view_on_valid_flow(): void
    {
        $this->prepareValidState();
        $this->queueTokenExchangeSuccess();
        $this->configureValidIdTokenVerifier();

        $response = $this->get('/google-callback?'.http_build_query([
            'code' => 'auth-code',
            'state' => 'valid-state',
        ]));

        $response->assertStatus(200);
        $response->assertViewIs('auth.google-callback');
    }

    public function test_callback_returns_view_type_on_success(): void
    {
        $this->prepareValidState();
        $this->queueTokenExchangeSuccess();
        $this->configureValidIdTokenVerifier();

        $factory = $this->runAction('auth-code', 'valid-state');

        $this->assertSame(HttpResponseType::VIEW, $factory->getType());
    }

    public function test_callback_returns_inertia_type_when_configured(): void
    {
        $this->app['config']->set(
            'authentication-kit.google.callback.type',
            HttpResponseType::INERTIA->value
        );
        $this->app['config']->set(
            'authentication-kit.google.callback.target',
            'Auth/GoogleCallback'
        );

        $this->app->forgetInstance(AuthenticationKitConfigInterface::class);
        $this->app->forgetInstance(AuthenticationKitConfig::class);
        $this->app->forgetInstance(GoogleOAuthService::class);
        $this->app->forgetInstance(GoogleCallbackAction::class);

        $this->prepareValidState();
        $this->queueTokenExchangeSuccess();
        $this->configureValidIdTokenVerifier();

        $factory = $this->runAction('auth-code', 'valid-state');

        $this->assertSame(HttpResponseType::INERTIA, $factory->getType());
        $this->assertSame('Auth/GoogleCallback', $factory->getContent()['component']);
    }

    public function test_callback_renders_operation_login(): void
    {
        $this->prepareValidState(OperationType::LOGIN);
        $this->queueTokenExchangeSuccess();
        $this->configureValidIdTokenVerifier();

        $response = $this->get('/google-callback?'.http_build_query([
            'code' => 'auth-code',
            'state' => 'valid-state',
        ]));

        $response->assertStatus(200);
        $response->assertViewIs('auth.google-callback');

        /** @var GoogleCallbackData $data */
        $data = $response->viewData('data');

        $this->assertInstanceOf(GoogleCallbackData::class, $data);
        $this->assertSame(OperationType::LOGIN, $data->operation);
    }

    public function test_callback_renders_operation_register(): void
    {
        $this->prepareValidState(OperationType::REGISTER);
        $this->queueTokenExchangeSuccess();
        $this->configureValidIdTokenVerifier();

        $response = $this->get('/google-callback?'.http_build_query([
            'code' => 'auth-code',
            'state' => 'valid-state',
        ]));

        $response->assertStatus(200);
        $response->assertViewIs('auth.google-callback');

        /** @var GoogleCallbackData $data */
        $data = $response->viewData('data');

        $this->assertInstanceOf(GoogleCallbackData::class, $data);
        $this->assertSame(OperationType::REGISTER, $data->operation);
    }

    // ============================================================================
    // Tests d'erreur — Google fournit une erreur
    // ============================================================================

    public function test_callback_renders_error_view_when_google_returns_error(): void
    {
        $response = $this->get('/google-callback?'.http_build_query([
            'state' => 'any',
            'error' => 'access_denied',
            'error_description' => 'User denied access',
        ]));

        $response->assertStatus(200);
        $this->assertCallbackErrorView($response);
    }

    public function test_callback_error_prop_has_provider_error_code_on_google_error(): void
    {
        $response = $this->get('/google-callback?'.http_build_query([
            'state' => 'any',
            'error' => 'access_denied',
            'error_description' => 'The user denied the request',
        ]));

        $error = $response->viewData('error');

        $this->assertSame('GOOGLE_PROVIDER_ERROR', $error->errorCode);
        $this->assertSame('The user denied the request', $error->message);
    }

    // ============================================================================
    // Tests d'erreur — state invalide
    // ============================================================================

    public function test_callback_renders_error_view_when_state_is_unknown(): void
    {
        $response = $this->get('/google-callback?'.http_build_query([
            'code' => 'auth-code',
            'state' => 'unknown-state',
        ]));

        $response->assertStatus(200);
        $this->assertCallbackErrorView($response);

        $error = $response->viewData('error');
        $this->assertSame('GOOGLE_CALLBACK_ERROR', $error->errorCode);
    }

    public function test_callback_renders_error_view_when_state_is_expired(): void
    {
        /** @var GoogleStateStoreInterface $store */
        $store = $this->app->make(GoogleStateStoreInterface::class);

        $store->put('expired-state', new GoogleStateRecord(
            code_verifier: 'v',
            nonce: 'n',
            expires_at: time() - 10,
            operation: OperationType::LOGIN,
        ), 600);

        $response = $this->get('/google-callback?'.http_build_query([
            'code' => 'auth-code',
            'state' => 'expired-state',
        ]));

        $response->assertStatus(200);
        $this->assertCallbackErrorView($response);

        $error = $response->viewData('error');
        $this->assertSame('GOOGLE_CALLBACK_ERROR', $error->errorCode);
    }

    // ============================================================================
    // Tests d'erreur — token exchange Google
    // ============================================================================

    public function test_callback_renders_error_view_when_google_token_exchange_fails(): void
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

        $this->idTokenVerifierMock->shouldReceive('verify')->never();

        $response = $this->get('/google-callback?'.http_build_query([
            'code' => 'expired-code',
            'state' => 'valid-state',
        ]));

        $response->assertStatus(200);
        $this->assertCallbackErrorView($response);

        $error = $response->viewData('error');
        $this->assertSame('GOOGLE_CALLBACK_ERROR', $error->errorCode);
    }

    // ============================================================================
    // Tests d'erreur — vérification id_token
    // ============================================================================

    public function test_callback_renders_error_view_when_id_token_verification_fails(): void
    {
        $this->prepareValidState();
        $this->queueTokenExchangeSuccess();

        $this->idTokenVerifierMock
            ->shouldReceive('verify')
            ->once()
            ->with('header.payload.signature', 'nonce-xyz')
            ->andThrow(GoogleOAuthException::idTokenSignatureInvalid());

        $response = $this->get('/google-callback?'.http_build_query([
            'code' => 'auth-code',
            'state' => 'valid-state',
        ]));

        $response->assertStatus(200);
        $this->assertCallbackErrorView($response);

        $error = $response->viewData('error');
        $this->assertSame('GOOGLE_CALLBACK_ERROR', $error->errorCode);
    }

    public function test_callback_renders_error_view_when_email_is_not_verified(): void
    {
        $this->prepareValidState();
        $this->queueTokenExchangeSuccess();

        $this->idTokenVerifierMock
            ->shouldReceive('verify')
            ->once()
            ->with('header.payload.signature', 'nonce-xyz')
            ->andThrow(GoogleOAuthException::emailNotVerified());

        $response = $this->get('/google-callback?'.http_build_query([
            'code' => 'auth-code',
            'state' => 'valid-state',
        ]));

        $response->assertStatus(200);
        $this->assertCallbackErrorView($response);

        $error = $response->viewData('error');
        $this->assertSame('GOOGLE_CALLBACK_ERROR', $error->errorCode);
    }

    // ============================================================================
    // Tests d'unicité du state (anti-replay)
    // ============================================================================

    public function test_callback_consumes_state_so_it_cannot_be_replayed(): void
    {
        $this->prepareValidState();
        $this->queueTokenExchangeSuccess();
        $this->configureValidIdTokenVerifier();

        $first = $this->get('/google-callback?'.http_build_query([
            'code' => 'auth-code',
            'state' => 'valid-state',
        ]));

        $first->assertStatus(200);

        $second = $this->get('/google-callback?'.http_build_query([
            'code' => 'auth-code',
            'state' => 'valid-state',
        ]));

        $second->assertStatus(200);
        $this->assertCallbackErrorView($second);

        $error = $second->viewData('error');
        $this->assertSame('GOOGLE_CALLBACK_ERROR', $error->errorCode);
    }
}
