<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Tests\Integration\Google\Services;

use AndyDefer\AuthenticationKit\Configs\AuthenticationKitConfig;
use AndyDefer\AuthenticationKit\Contracts\Configs\AuthenticationKitConfigInterface;
use AndyDefer\AuthenticationKit\Enums\ErrorCode;
use AndyDefer\AuthenticationKit\Google\Contracts\GoogleStateStoreInterface;
use AndyDefer\AuthenticationKit\Google\Datas\GoogleAndroidCallbackData;
use AndyDefer\AuthenticationKit\Google\Datas\GoogleCallbackData;
use AndyDefer\AuthenticationKit\Google\Enums\OperationType;
use AndyDefer\AuthenticationKit\Google\Records\GoogleAndroidCallbackRecord;
use AndyDefer\AuthenticationKit\Google\Records\GoogleCallbackRecord;
use AndyDefer\AuthenticationKit\Google\Records\GoogleRedirectRecord;
use AndyDefer\AuthenticationKit\Google\Records\GoogleStateRecord;
use AndyDefer\AuthenticationKit\Google\Services\GoogleIdTokenVerifier;
use AndyDefer\AuthenticationKit\Google\Services\GoogleOAuthService;
use AndyDefer\AuthenticationKit\Google\Services\GooglePkceGenerator;
use AndyDefer\AuthenticationKit\Google\ValueObjects\GoogleAndroidCallbackVO;
use AndyDefer\AuthenticationKit\Google\ValueObjects\GoogleCallbackVO;
use AndyDefer\AuthenticationKit\Google\ValueObjects\GoogleIdTokenClaimsVO;
use AndyDefer\AuthenticationKit\Tests\Integration\Google\Fixtures\Models\TestGoogleUser;
use AndyDefer\AuthenticationKit\Tests\IntegrationTestCase;
use AndyDefer\DomainStructures\Utils\StrictAssociative;
use AndyDefer\Nemesis\Datas\ErrorResponseData;
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

            'callback' => [
                'type' => 'view',
                'target' => 'auth.google-callback',
            ],
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

        $this->app->singleton(
            AuthenticationKitConfigInterface::class,
            function ($app): AuthenticationKitConfigInterface {
                return $app->make(AuthenticationKitConfig::class);
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

    // ==================== CALLBACK — ERRORS (returned as ErrorResponseData) ====================

    public function test_callback_returns_provider_error_when_google_returns_error(): void
    {
        $record = new GoogleCallbackRecord(
            code: '',
            state: 'any',
            error: 'access_denied',
            error_description: 'User denied access',
        );

        $result = $this->service->callback($record);

        $this->assertInstanceOf(ErrorResponseData::class, $result);
        $this->assertSame(ErrorCode::GOOGLE_PROVIDER_ERROR->value, $result->errorCode);
        $this->assertSame('User denied access', $result->message);
    }

    public function test_callback_returns_state_mismatch_when_state_is_unknown(): void
    {
        $record = new GoogleCallbackRecord(
            code: 'auth-code',
            state: 'unknown-state',
        );

        $result = $this->service->callback($record);

        $this->assertInstanceOf(ErrorResponseData::class, $result);
        $this->assertSame(ErrorCode::GOOGLE_STATE_MISMATCH->value, $result->errorCode);
    }

    public function test_callback_returns_state_expired_when_state_is_expired(): void
    {
        $this->stateStore->put('expired-state', new GoogleStateRecord(
            code_verifier: 'v',
            nonce: 'n',
            expires_at: time() - 10,
            operation: OperationType::LOGIN,
        ), 600);

        $record = new GoogleCallbackRecord(code: 'code', state: 'expired-state');

        $result = $this->service->callback($record);

        $this->assertInstanceOf(ErrorResponseData::class, $result);
        $this->assertSame(ErrorCode::GOOGLE_STATE_EXPIRED->value, $result->errorCode);
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

        $result = $this->service->callback($record);

        $this->assertInstanceOf(ErrorResponseData::class, $result);
        $this->assertSame(ErrorCode::GOOGLE_STATE_MISMATCH->value, $result->errorCode);
    }

    // ==================== CALLBACK — SUCCESS (returns GoogleCallbackData from onCallbackSuccess) ====================

    public function test_callback_exchanges_code_and_delegates_to_on_callback_success(): void
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

        $claims = $this->makeClaims(
            email: 'user@example.com',
            overrides: ['nonce' => 'nonce-xyz'],
        );

        $this->idTokenVerifierMock
            ->shouldReceive('verify')
            ->once()
            ->with('header.payload.signature', 'nonce-xyz')
            ->andReturn($claims);

        $service = new class(client: $this->app->make(ClientService::class), config: $this->app->make(AuthenticationKitConfig::class), pkceGenerator: $this->app->make(GooglePkceGenerator::class), stateStore: $this->stateStore, idTokenVerifier: $this->idTokenVerifierMock) extends GoogleOAuthService
        {
            public ?GoogleCallbackVO $captured = null;

            public function onCallbackSuccess(GoogleCallbackVO $vo): GoogleCallbackData|ErrorResponseData
            {
                $this->captured = $vo;

                return new GoogleCallbackData(
                    idToken: $vo->getIdToken(),
                    accessToken: $vo->getAccessToken(),
                    expiresIn: $vo->getExpiresIn(),
                    tokenType: $vo->getTokenType(),
                    scope: $vo->getScope(),
                    claims: $vo->getClaims(),
                    operation: $vo->getOperation(),
                    auth: new StrictAssociative(['id' => 1, 'email' => $vo->getClaims()->getEmail()]),
                    token: 'plain-test-token',
                    isNewUser: false,
                );
            }
        };

        $record = new GoogleCallbackRecord(code: 'auth-code', state: 'valid-state');
        $result = $service->callback($record);

        $this->assertInstanceOf(GoogleCallbackVO::class, $service->captured);
        $this->assertInstanceOf(GoogleCallbackData::class, $result);

        $vo = $service->captured;

        $this->assertSame('header.payload.signature', $vo->getIdToken());
        $this->assertSame('access-token-xyz', $vo->getAccessToken());
        $this->assertSame(3600, $vo->getExpiresIn());
        $this->assertSame('Bearer', $vo->getTokenType());
        $this->assertSame('openid email profile', $vo->getScope());
        $this->assertSame('sub-'.md5('user@example.com'), $vo->getClaims()->getSub());
        $this->assertSame(OperationType::REGISTER, $vo->getOperation());

        $this->assertSame('plain-test-token', $result->token);
        $this->assertSame(OperationType::REGISTER, $result->operation);
    }

    // ==================== ANDROID CALLBACK — VALIDATION (returns ErrorResponseData) ====================

    public function test_android_callback_returns_model_not_found_when_model_does_not_exist(): void
    {
        $claims = $this->makeClaims('user@example.com');

        $this->idTokenVerifierMock
            ->shouldReceive('verify')
            ->once()
            ->with('fake.jwt.token', null)
            ->andReturn($claims);

        $record = new GoogleAndroidCallbackRecord(
            model_type: 'App\\Models\\DoesNotExist',
            id_token: 'fake.jwt.token',
        );

        $result = $this->service->androidCallback($record);

        $this->assertInstanceOf(ErrorResponseData::class, $result);
        $this->assertSame(ErrorCode::GOOGLE_MODEL_NOT_FOUND->value, $result->errorCode);
        $this->assertStringContainsString('App\\Models\\DoesNotExist', $result->message);
    }

    public function test_android_callback_returns_invalid_model_when_model_does_not_implement_mail_authenticatable(): void
    {
        $claims = $this->makeClaims('user@example.com');

        $this->idTokenVerifierMock
            ->shouldReceive('verify')
            ->once()
            ->with('fake.jwt.token', null)
            ->andReturn($claims);

        $record = new GoogleAndroidCallbackRecord(
            model_type: \stdClass::class,
            id_token: 'fake.jwt.token',
        );

        $result = $this->service->androidCallback($record);

        $this->assertInstanceOf(ErrorResponseData::class, $result);
        $this->assertSame(ErrorCode::GOOGLE_INVALID_MODEL->value, $result->errorCode);
        $this->assertStringContainsString('must implement MailAuthenticatable', $result->message);
    }

    public function test_android_callback_returns_callback_error_when_email_is_not_verified(): void
    {
        $claims = $this->makeClaims(
            email: 'user@example.com',
            overrides: ['emailVerified' => false],
        );

        $this->idTokenVerifierMock
            ->shouldReceive('verify')
            ->once()
            ->with('fake.jwt.token', null)
            ->andReturn($claims);

        $record = new GoogleAndroidCallbackRecord(
            model_type: TestGoogleUser::class,
            id_token: 'fake.jwt.token',
        );

        $result = $this->service->androidCallback($record);

        $this->assertInstanceOf(ErrorResponseData::class, $result);
        $this->assertSame(ErrorCode::GOOGLE_CALLBACK_ERROR->value, $result->errorCode);
        $this->assertSame('Google email is not verified', $result->message);
    }

    // ==================== ANDROID CALLBACK — SUCCESS (delegates to onAndroidCallbackSuccess with a VO) ====================

    public function test_android_callback_passes_claims_and_model_type_vo_to_on_android_callback_success(): void
    {
        $claims = $this->makeClaims('user@example.com');

        $this->idTokenVerifierMock
            ->shouldReceive('verify')
            ->once()
            ->with('fake.jwt.token', null)
            ->andReturn($claims);

        $service = new class(client: $this->app->make(ClientService::class), config: $this->app->make(AuthenticationKitConfig::class), pkceGenerator: $this->app->make(GooglePkceGenerator::class), stateStore: $this->stateStore, idTokenVerifier: $this->idTokenVerifierMock) extends GoogleOAuthService
        {
            public ?GoogleAndroidCallbackVO $captured = null;

            public function onAndroidCallbackSuccess(GoogleAndroidCallbackVO $vo): GoogleAndroidCallbackData|ErrorResponseData
            {
                $this->captured = $vo;

                return new GoogleAndroidCallbackData(
                    claims: $vo->getClaims(),
                    modelType: $vo->getModelType(),
                    auth: new StrictAssociative(['id' => 42, 'email' => $vo->getClaims()->getEmail()]),
                    token: 'plain-android-token',
                    isNewUser: true,
                );
            }
        };

        $record = new GoogleAndroidCallbackRecord(
            model_type: TestGoogleUser::class,
            id_token: 'fake.jwt.token',
        );

        $result = $service->androidCallback($record);

        $this->assertInstanceOf(GoogleAndroidCallbackVO::class, $service->captured);
        $this->assertSame(TestGoogleUser::class, $service->captured->getModelType());
        $this->assertSame('user@example.com', $service->captured->getClaims()->getEmail());

        $this->assertInstanceOf(GoogleAndroidCallbackData::class, $result);
        $this->assertSame(TestGoogleUser::class, $result->modelType);
        $this->assertSame('plain-android-token', $result->token);
        $this->assertTrue($result->isNewUser);
        $this->assertSame('user@example.com', $result->auth['email'] ?? null);
    }

    public function test_android_callback_does_not_create_user_nor_issue_token_itself(): void
    {
        $claims = $this->makeClaims('should-not-exist@example.com');

        $this->idTokenVerifierMock
            ->shouldReceive('verify')
            ->once()
            ->with('fake.jwt.token', null)
            ->andReturn($claims);

        $service = new class(client: $this->app->make(ClientService::class), config: $this->app->make(AuthenticationKitConfig::class), pkceGenerator: $this->app->make(GooglePkceGenerator::class), stateStore: $this->stateStore, idTokenVerifier: $this->idTokenVerifierMock) extends GoogleOAuthService
        {
            public function onAndroidCallbackSuccess(GoogleAndroidCallbackVO $vo): GoogleAndroidCallbackData|ErrorResponseData
            {
                // No user creation, no token issuance — service must not do it either.
                return new GoogleAndroidCallbackData(
                    claims: $vo->getClaims(),
                    modelType: $vo->getModelType(),
                    auth: new StrictAssociative(['id' => 0]),
                    token: 'whatever',
                    isNewUser: true,
                );
            }
        };

        $record = new GoogleAndroidCallbackRecord(
            model_type: TestGoogleUser::class,
            id_token: 'fake.jwt.token',
        );

        $service->androidCallback($record);

        $this->assertDatabaseMissing('test_google_users', [
            'email' => 'should-not-exist@example.com',
        ]);
    }

    // ==================== HELPERS ====================

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeClaims(string $email, array $overrides = []): GoogleIdTokenClaimsVO
    {
        $payload = array_merge([
            'sub' => 'sub-'.md5($email),
            'email' => $email,
            'emailVerified' => true,
            'iss' => 'https://accounts.google.com',
            'aud' => 'test-client-id.apps.googleusercontent.com',
            'exp' => time() + 3600,
            'iat' => time(),
        ], $overrides);

        return new GoogleIdTokenClaimsVO(
            sub: $payload['sub'],
            email: $payload['email'],
            emailVerified: $payload['emailVerified'],
            iss: $payload['iss'],
            aud: $payload['aud'],
            exp: $payload['exp'],
            iat: $payload['iat'],
        );
    }
}
