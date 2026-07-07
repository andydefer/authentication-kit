<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Tests\Mail\Actions;

use AndyDefer\AuthenticationKit\Enums\EventType;
use AndyDefer\AuthenticationKit\Mail\Actions\EmailLoginAction;
use AndyDefer\AuthenticationKit\Mail\Requests\EmailLoginRequest;
use AndyDefer\AuthenticationKit\Tests\IntegrationTestCase;
use AndyDefer\AuthenticationKit\Tests\Mail\Fixtures\Models\TestUserMail;
use AndyDefer\Logger\Contracts\LoggerInterface;
use AndyDefer\Logger\Records\LogDataRecord;
use Mockery;

final class EmailLoginActionTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app['router']->middleware(['validate.mail.authenticatable'])->post('/api/login', action_route(
            EmailLoginRequest::class,
            EmailLoginAction::class
        ));
    }

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

        $response = $this->postJson('/api/login', $payload);

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

        $response = $this->postJson('/api/login', $payload);

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

        $response = $this->postJson('/api/login', $payload);

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

        $response = $this->postJson('/api/login', $payload);

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

        $response = $this->postJson('/api/login', $payload);

        $response->assertStatus(401);
        $response->assertJson([
            'message' => 'Invalid credentials',
            'status' => 401,
            'errorCode' => 'INVALID_CREDENTIALS',
        ]);
    }

    public function test_login_logs_successful_login(): void
    {
        TestUserMail::create([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => bcrypt('Password123!'),
        ]);

        $logger = Mockery::mock(LoggerInterface::class);
        $this->app->instance(LoggerInterface::class, $logger);

        $logger->shouldReceive('info')
            ->once()
            ->with(Mockery::on(function (LogDataRecord $log) {
                return $log->type === 'auth'
                    && $log->payload->event === EventType::USER_LOGIN_SUCCESS->value
                    && isset($log->payload->auth_id)
                    && isset($log->payload->email)
                    && isset($log->payload->platform)
                    && isset($log->payload->browser)
                    && isset($log->payload->device_type)
                    && isset($log->payload->ip)
                    && isset($log->payload->user_agent)
                    && $log->payload->model_type === TestUserMail::class;
            }));

        $payload = [
            'model_type' => TestUserMail::class,
            'email' => 'jane@example.com',
            'password' => 'Password123!',
        ];

        $response = $this->postJson('/api/login', $payload);

        $response->assertStatus(200);
    }

    public function test_login_returns_error_when_model_type_does_not_exist(): void
    {
        $payload = [
            'model_type' => 'NonExistentClass',
            'email' => 'john@example.com',
            'password' => 'Password123!',
        ];

        $response = $this->postJson('/api/login', $payload);

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

        $response = $this->postJson('/api/login', $payload);

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

        $response = $this->postJson('/api/login', $payload);

        $response->assertStatus(400);
        $response->assertJson([
            'message' => 'model_type is required',
            'status' => 400,
            'errorCode' => 'MODEL_TYPE_REQUIRED',
        ]);
    }
}
