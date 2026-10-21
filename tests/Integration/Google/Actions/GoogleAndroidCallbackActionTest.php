<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Tests\Integration\Google\Actions;

use AndyDefer\Actions\Enums\HttpResponseType;
use AndyDefer\Actions\Http\ResponseFactory;
use AndyDefer\AuthenticationKit\Contracts\Configs\AuthenticationKitConfigInterface;
use AndyDefer\AuthenticationKit\Google\Actions\GoogleAndroidCallbackAction;
use AndyDefer\AuthenticationKit\Google\Contracts\GoogleOAuthInterface;
use AndyDefer\AuthenticationKit\Google\Exceptions\GoogleOAuthException;
use AndyDefer\AuthenticationKit\Google\Records\GoogleAndroidCallbackRecord;
use AndyDefer\AuthenticationKit\Tests\IntegrationTestCase;
use AndyDefer\DomainStructures\Abstracts\AbstractData;
use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\Nemesis\Datas\ErrorResponseData;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use Mockery;
use RuntimeException;

final class GoogleAndroidCallbackActionTest extends IntegrationTestCase
{
    private GoogleOAuthInterface $googleOAuth;

    private GoogleAndroidCallbackAction $action;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->forgetInstance(AuthenticationKitConfigInterface::class);

        $this->googleOAuth = Mockery::mock(GoogleOAuthInterface::class);

        $this->action = new GoogleAndroidCallbackAction(
            googleOAuth: $this->googleOAuth,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function makeRecord(
        string $modelType = 'App\\Models\\User',
        string $idToken = 'fake.jwt.token',
    ): GoogleAndroidCallbackRecord {
        return new GoogleAndroidCallbackRecord(
            model_type: $modelType,
            id_token: $idToken,
        );
    }

    private function okFactory(): ResponseFactory
    {
        $data = new class('Login successful') extends AbstractData
        {
            public function __construct(public readonly string $message) {}
        };

        return ResponseFactory::json($data);
    }

    // ==================== before() GUARD ====================

    public function test_before_throws_when_record_is_not_android_callback_record(): void
    {
        $invalidRecord = new class extends AbstractRecord
        {
            public function __construct() {}
        };

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid record type');

        $this->action->run($invalidRecord);
    }

    // ==================== SUCCESS PATH ====================

    public function test_handle_returns_json_response_on_success(): void
    {
        $record = $this->makeRecord();

        $this->googleOAuth
            ->shouldReceive('androidCallback')
            ->once()
            ->with(Mockery::type(GoogleAndroidCallbackRecord::class))
            ->andReturn($this->okFactory());

        $response = $this->action->run($record);

        $this->assertSame(HttpResponseType::JSON, $response->getType());
    }

    public function test_handle_passes_exact_record_to_service(): void
    {
        $record = $this->makeRecord('App\\Models\\User', 'specific.id.token');

        $this->googleOAuth
            ->shouldReceive('androidCallback')
            ->once()
            ->withArgs(function (GoogleAndroidCallbackRecord $incoming) {
                return $incoming->model_type === 'App\\Models\\User'
                    && $incoming->id_token === 'specific.id.token';
            })
            ->andReturn($this->okFactory());

        $response = $this->action->run($record);

        $this->assertSame(HttpResponseType::JSON, $response->getType());
    }

    // ==================== UNEXPECTED RESPONSE TYPE ====================

    public function test_handle_returns_callback_unexpected_error_when_service_returns_view(): void
    {
        $record = $this->makeRecord();

        $this->googleOAuth
            ->shouldReceive('androidCallback')
            ->once()
            ->andReturn(ResponseFactory::view('some.view', []));

        $response = $this->action->run($record);

        $this->assertSame(HttpResponseType::JSON, $response->getType());

        /** @var ErrorResponseData $payload */
        $payload = $response->getContent();

        $this->assertSame('GOOGLE_CALLBACK_UNEXPECTED_ERROR', $payload->errorCode);
    }

    public function test_handle_returns_callback_unexpected_error_when_service_returns_redirect(): void
    {
        $record = $this->makeRecord();

        $this->googleOAuth
            ->shouldReceive('androidCallback')
            ->once()
            ->andReturn(ResponseFactory::redirect('/somewhere'));

        $response = $this->action->run($record);

        /** @var ErrorResponseData $payload */
        $payload = $response->getContent();

        $this->assertSame('GOOGLE_CALLBACK_UNEXPECTED_ERROR', $payload->errorCode);
    }

    // ==================== GOOGLE OAUTH EXCEPTIONS ====================

    public function test_handle_returns_provider_error_when_exception_is_provider_error(): void
    {
        $record = $this->makeRecord();

        $this->googleOAuth
            ->shouldReceive('androidCallback')
            ->once()
            ->andThrow(GoogleOAuthException::providerError('User denied access'));

        $response = $this->action->run($record);

        $this->assertSame(HttpResponseType::JSON, $response->getType());

        /** @var ErrorResponseData $payload */
        $payload = $response->getContent();

        $this->assertSame('GOOGLE_PROVIDER_ERROR', $payload->errorCode);
        $this->assertSame('User denied access', $payload->message);
    }

    public function test_handle_returns_callback_error_when_exception_is_not_provider_error(): void
    {
        $record = $this->makeRecord();

        $this->googleOAuth
            ->shouldReceive('androidCallback')
            ->once()
            ->andThrow(GoogleOAuthException::stateNotFound());

        $response = $this->action->run($record);

        /** @var ErrorResponseData $payload */
        $payload = $response->getContent();

        $this->assertSame('GOOGLE_CALLBACK_ERROR', $payload->errorCode);
        $this->assertSame('Google OAuth state not found', $payload->message);
    }

    public function test_handle_returns_callback_error_when_email_not_verified(): void
    {
        $record = $this->makeRecord();

        $this->googleOAuth
            ->shouldReceive('androidCallback')
            ->once()
            ->andThrow(GoogleOAuthException::emailNotVerified());

        $response = $this->action->run($record);

        /** @var ErrorResponseData $payload */
        $payload = $response->getContent();

        $this->assertSame('GOOGLE_CALLBACK_ERROR', $payload->errorCode);
        $this->assertSame('Google email is not verified', $payload->message);
    }

    public function test_handle_returns_callback_error_when_model_not_found(): void
    {
        $record = $this->makeRecord();

        $this->googleOAuth
            ->shouldReceive('androidCallback')
            ->once()
            ->andThrow(GoogleOAuthException::modelNotFound('App\\Models\\Nope'));

        $response = $this->action->run($record);

        /** @var ErrorResponseData $payload */
        $payload = $response->getContent();

        $this->assertSame('GOOGLE_CALLBACK_ERROR', $payload->errorCode);
        $this->assertStringContainsString('App\\Models\\Nope', $payload->message);
    }

    public function test_handle_returns_callback_error_when_model_not_google_authenticatable(): void
    {
        $record = $this->makeRecord();

        $this->googleOAuth
            ->shouldReceive('androidCallback')
            ->once()
            ->andThrow(GoogleOAuthException::modelNotGoogleAuthenticatable('App\\Models\\User'));

        $response = $this->action->run($record);

        /** @var ErrorResponseData $payload */
        $payload = $response->getContent();

        $this->assertSame('GOOGLE_CALLBACK_ERROR', $payload->errorCode);
        $this->assertStringContainsString('cannot handle the Google Android flow', $payload->message);
    }

    // ==================== GENERIC EXCEPTIONS ====================

    public function test_handle_returns_callback_error_when_guzzle_exception_direct(): void
    {
        $record = $this->makeRecord();

        $guzzleError = new ConnectException(
            'Connection refused',
            new Request('POST', 'https://oauth2.googleapis.com/token')
        );

        $this->googleOAuth
            ->shouldReceive('androidCallback')
            ->once()
            ->andThrow($guzzleError);

        $response = $this->action->run($record);

        /** @var ErrorResponseData $payload */
        $payload = $response->getContent();

        $this->assertSame('GOOGLE_CALLBACK_ERROR', $payload->errorCode);
    }

    public function test_handle_returns_callback_error_when_guzzle_exception_wrapped_in_runtime(): void
    {
        $record = $this->makeRecord();

        $guzzleError = new ConnectException(
            'Connection refused',
            new Request('POST', 'https://oauth2.googleapis.com/token')
        );

        $wrapped = new RuntimeException('Wrapped', 0, $guzzleError);

        $this->googleOAuth
            ->shouldReceive('androidCallback')
            ->once()
            ->andThrow($wrapped);

        $response = $this->action->run($record);

        /** @var ErrorResponseData $payload */
        $payload = $response->getContent();

        $this->assertSame('GOOGLE_CALLBACK_ERROR', $payload->errorCode);
        $this->assertSame('Wrapped', $payload->message);
    }

    public function test_handle_returns_unexpected_error_for_plain_runtime_exception(): void
    {
        $record = $this->makeRecord();

        $this->googleOAuth
            ->shouldReceive('androidCallback')
            ->once()
            ->andThrow(new RuntimeException('Something broke'));

        $response = $this->action->run($record);

        /** @var ErrorResponseData $payload */
        $payload = $response->getContent();

        $this->assertSame('GOOGLE_CALLBACK_UNEXPECTED_ERROR', $payload->errorCode);
        $this->assertSame('Something broke', $payload->message);
    }

    public function test_handle_returns_unexpected_error_for_arbitrary_exception(): void
    {
        $record = $this->makeRecord();

        $this->googleOAuth
            ->shouldReceive('androidCallback')
            ->once()
            ->andThrow(new \LogicException('Unexpected path'));

        $response = $this->action->run($record);

        /** @var ErrorResponseData $payload */
        $payload = $response->getContent();

        $this->assertSame('GOOGLE_CALLBACK_UNEXPECTED_ERROR', $payload->errorCode);
    }

    // ==================== HTTP STATUS CODES ====================

    public function test_handle_uses_correct_http_status_for_provider_error(): void
    {
        $record = $this->makeRecord();

        $this->googleOAuth
            ->shouldReceive('androidCallback')
            ->once()
            ->andThrow(GoogleOAuthException::providerError('Denied'));

        $response = $this->action->run($record);

        $this->assertSame(400, $response->getStatus());
    }

    public function test_handle_uses_correct_http_status_for_callback_error(): void
    {
        $record = $this->makeRecord();

        $this->googleOAuth
            ->shouldReceive('androidCallback')
            ->once()
            ->andThrow(GoogleOAuthException::stateNotFound());

        $response = $this->action->run($record);

        $this->assertSame(500, $response->getStatus());
    }

    public function test_handle_uses_correct_http_status_for_unexpected_error(): void
    {
        $record = $this->makeRecord();

        $this->googleOAuth
            ->shouldReceive('androidCallback')
            ->once()
            ->andThrow(new RuntimeException('boom'));

        $response = $this->action->run($record);

        $this->assertSame(500, $response->getStatus());
    }
}
