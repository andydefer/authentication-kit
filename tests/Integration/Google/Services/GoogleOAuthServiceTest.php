<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Tests\Integration\Google\Services;

use AndyDefer\Actions\Http\ResponseFactory;
use AndyDefer\AuthenticationKit\Configs\AuthenticationKitConfig;
use AndyDefer\AuthenticationKit\Google\Contracts\GoogleStateStoreInterface;
use AndyDefer\AuthenticationKit\Google\Datas\GoogleCallbackData;
use AndyDefer\AuthenticationKit\Google\Datas\GoogleIdTokenClaimsData;
use AndyDefer\AuthenticationKit\Google\Enums\OperationType;
use AndyDefer\AuthenticationKit\Google\Exceptions\GoogleOAuthException;
use AndyDefer\AuthenticationKit\Google\Records\GoogleCallbackRecord;
use AndyDefer\AuthenticationKit\Google\Records\GoogleRedirectRecord;
use AndyDefer\AuthenticationKit\Google\Records\GoogleStateRecord;
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

final class GoogleOAuthServiceTest extends IntegrationTestCase
{
    private MockHandler $mockHandler;

    private GoogleStateStoreInterface $stateStore;

    private GoogleOAuthService $service;

    private GoogleIdTokenVerifier $idTokenVerifierMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app['config']->set('authentication-kit.google', [
            'client_id' => 'test-client-id.apps.googleusercontent.com',
            'client_secret' => 'test-client-secret',
            'redirect_uri' => 'https://app.test/auth/google/callback',

            'authorization_endpoint' => 'https://accounts.google.com/o/oauth2/v2/auth',
            'token_endpoint' => 'https://oauth2.googleapis.com/token',
            'jwks_endpoint' => 'https://www.googleapis.com/oauth2/v3/certs',

            'scopes' => ['openid', 'email', 'profile'],

            'state_lifetime' => 600,
            'pkce_verifier_length' => 64,
        ]);

        $this->app->forgetInstance(AuthenticationKitConfig::class);
        $this->app->forgetInstance(ClientService::class);
        $this->app->forgetInstance(GooglePkceGenerator::class);
        $this->app->forgetInstance(GoogleStateStoreInterface::class);

        $this->app->singleton(
            AuthenticationKitConfig::class,
            function ($app): AuthenticationKitConfig {
                return new AuthenticationKitConfig($app['config']);
            }
        );

        $this->mockHandler = new MockHandler;
        $handlerStack = HandlerStack::create($this->mockHandler);
        $guzzle = new GuzzleClient(['handler' => $handlerStack]);

        $this->app->instance(ClientService::class, new ClientService($guzzle));

        $this->stateStore = $this->app->make(GoogleStateStoreInterface::class);
        $this->idTokenVerifierMock = Mockery::mock(GoogleIdTokenVerifier::class);

        $this->service = new GoogleOAuthService(
            client: $this->app->make(ClientService::class),
            config: $this->app->make(AuthenticationKitConfig::class),
            pkceGenerator: $this->app->make(GooglePkceGenerator::class),
            stateStore: $this->stateStore,
            idTokenVerifier: $this->idTokenVerifierMock,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // ==================== AUTHORIZATION REDIRECT ====================

    public function test_authorization_redirect_returns_url(): void
    {
        $record = new GoogleRedirectRecord(
            state: '',
            code_challenge: '',
            nonce: '',
            operation: OperationType::LOGIN,
        );

        $result = $this->service->authorizationRedirect($record);

        $this->assertStringStartsWith('https://accounts.google.com/o/oauth2/v2/auth', $result->url);
    }

    public function test_authorization_redirect_includes_client_id(): void
    {
        $record = new GoogleRedirectRecord(
            state: '',
            code_challenge: '',
            nonce: '',
            operation: OperationType::LOGIN,
        );

        $result = $this->service->authorizationRedirect($record);

        $this->assertStringContainsString('client_id=test-client-id', $result->url);
    }

    public function test_authorization_redirect_includes_pkce_challenge(): void
    {
        $record = new GoogleRedirectRecord(
            state: '',
            code_challenge: '',
            nonce: '',
            operation: OperationType::REGISTER,
        );

        $result = $this->service->authorizationRedirect($record);

        $this->assertStringContainsString('code_challenge=', $result->url);
        $this->assertStringContainsString('code_challenge_method=S256', $result->url);
    }

    public function test_authorization_redirect_stores_state_record(): void
    {
        $record = new GoogleRedirectRecord(
            state: 'my-state',
            code_challenge: '',
            nonce: 'my-nonce',
            operation: OperationType::REGISTER,
        );

        $this->service->authorizationRedirect($record);

        $pulled = $this->stateStore->pull('my-state');

        $this->assertInstanceOf(GoogleStateRecord::class, $pulled);
        $this->assertSame('my-nonce', $pulled->nonce);
        $this->assertSame(OperationType::REGISTER, $pulled->operation);
        $this->assertNotEmpty($pulled->code_verifier);
    }

    // ==================== CALLBACK ====================

    public function test_callback_throws_when_google_returns_error(): void
    {
        $record = new GoogleCallbackRecord(
            code: '',
            state: 'any',
            error: 'access_denied',
            error_description: 'User denied access',
        );

        $this->expectException(GoogleOAuthException::class);
        $this->expectExceptionMessage('User denied access');

        $this->service->callback($record);
    }

    public function test_callback_throws_when_state_is_unknown(): void
    {
        $record = new GoogleCallbackRecord(
            code: 'auth-code',
            state: 'unknown-state',
        );

        $this->expectException(GoogleOAuthException::class);
        $this->expectExceptionMessage('state not found');

        $this->service->callback($record);
    }

    public function test_callback_throws_when_state_is_expired(): void
    {
        $this->stateStore->put('expired-state', new GoogleStateRecord(
            code_verifier: 'v',
            nonce: 'n',
            expires_at: time() - 10,
            operation: OperationType::LOGIN,
        ), 600);

        $record = new GoogleCallbackRecord(code: 'code', state: 'expired-state');

        $this->expectException(GoogleOAuthException::class);
        $this->expectExceptionMessage('state has expired');

        $this->service->callback($record);
    }

    public function test_callback_exchanges_code_and_returns_tokens(): void
    {
        $this->stateStore->put('valid-state', new GoogleStateRecord(
            code_verifier: 'verifier-xyz',
            nonce: 'nonce-xyz',
            expires_at: time() + 600,
            operation: OperationType::REGISTER,
        ), 600);

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

        $service = new class(client: $this->app->make(ClientService::class), config: $this->app->make(AuthenticationKitConfig::class), pkceGenerator: $this->app->make(GooglePkceGenerator::class), stateStore: $this->stateStore, idTokenVerifier: $this->idTokenVerifierMock) extends GoogleOAuthService
        {
            public ?GoogleCallbackData $captured = null;

            public function onCallbackSuccess(GoogleCallbackData $data): ResponseFactory
            {
                $this->captured = $data;

                return ResponseFactory::json($data);
            }
        };

        $record = new GoogleCallbackRecord(code: 'auth-code', state: 'valid-state');
        $response = $service->callback($record);

        $this->assertInstanceOf(ResponseFactory::class, $response);
        $this->assertInstanceOf(GoogleCallbackData::class, $service->captured);

        $data = $service->captured;

        $this->assertSame('header.payload.signature', $data->idToken);
        $this->assertSame('access-token-xyz', $data->accessToken);
        $this->assertSame(3600, $data->expiresIn);
        $this->assertSame('Bearer', $data->tokenType);
        $this->assertSame('openid email profile', $data->scope);
        $this->assertSame('sub-123', $data->claims->sub);
        $this->assertSame(OperationType::REGISTER, $data->operation);
    }

    public function test_callback_returns_response_factory_from_on_callback_success(): void
    {
        $this->stateStore->put('valid-state', new GoogleStateRecord(
            code_verifier: 'verifier-xyz',
            nonce: 'nonce-xyz',
            expires_at: time() + 600,
            operation: OperationType::LOGIN,
        ), 600);

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

        $service = new class(client: $this->app->make(ClientService::class), config: $this->app->make(AuthenticationKitConfig::class), pkceGenerator: $this->app->make(GooglePkceGenerator::class), stateStore: $this->stateStore, idTokenVerifier: $this->idTokenVerifierMock) extends GoogleOAuthService
        {
            public function onCallbackSuccess(GoogleCallbackData $data): ResponseFactory
            {
                return ResponseFactory::redirect('/dashboard');
            }
        };

        $record = new GoogleCallbackRecord(code: 'auth-code', state: 'valid-state');
        $response = $service->callback($record);

        $this->assertInstanceOf(ResponseFactory::class, $response);
    }

    public function test_callback_consumes_state_so_it_cannot_be_replayed(): void
    {
        $this->stateStore->put('one-shot', new GoogleStateRecord(
            code_verifier: 'v',
            nonce: 'n',
            expires_at: time() + 600,
            operation: OperationType::LOGIN,
        ), 600);

        $this->assertNotNull($this->stateStore->pull('one-shot'));

        $record = new GoogleCallbackRecord(code: 'code', state: 'one-shot');

        $this->expectException(GoogleOAuthException::class);
        $this->expectExceptionMessage('state not found');

        $this->service->callback($record);
    }
}
