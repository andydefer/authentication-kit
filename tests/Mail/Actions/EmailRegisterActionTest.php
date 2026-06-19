<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Tests\Mail\Actions;

use AndyDefer\AuthenticationKit\Enums\EventType;
use AndyDefer\AuthenticationKit\Mail\Actions\EmailRegisterAction;
use AndyDefer\AuthenticationKit\Mail\Requests\EmailRegisterRequest;
use AndyDefer\AuthenticationKit\Tests\IntegrationTestCase;
use AndyDefer\AuthenticationKit\Tests\Mail\Fixtures\Models\TestUserMail;
use AndyDefer\Logger\Contracts\LoggerInterface;
use AndyDefer\Logger\Records\LogDataRecord;
use Mockery;

final class EmailRegisterActionTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app['router']->post('/api/register', action_route(
            EmailRegisterRequest::class,
            EmailRegisterAction::class
        ));
    }

    public function test_register_user_successfully_without_token(): void
    {
        $payload = [
            'model_type' => TestUserMail::class,
            'with_token' => false,
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ];

        $response = $this->postJson('/api/register', $payload);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'message',
            'user' => [
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

    public function test_register_user_successfully_with_token(): void
    {
        $payload = [
            'model_type' => TestUserMail::class,
            'with_token' => true,
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ];

        $response = $this->postJson('/api/register', $payload);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'message',
            'user' => [
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
        $logger = Mockery::mock(LoggerInterface::class);
        $this->app->instance(LoggerInterface::class, $logger);

        $logger->shouldReceive('info')
            ->once()
            ->with(Mockery::on(function (LogDataRecord $log) {
                return $log->type === 'auth'
                    && $log->payload->event === EventType::USER_REGISTRATION_SUCCESS->value
                    && isset($log->payload->user_id)
                    && isset($log->payload->platform)
                    && isset($log->payload->browser)
                    && isset($log->payload->device_type)
                    && isset($log->payload->ip)
                    && isset($log->payload->user_agent)
                    && $log->payload->model_type === TestUserMail::class;
            }));

        $payload = [
            'model_type' => TestUserMail::class,
            'with_token' => true,
            'name' => 'Log Test',
            'email' => 'log@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ];

        $response = $this->postJson('/api/register', $payload);

        $response->assertStatus(201);
    }

    public function test_register_logs_failed_registration(): void
    {
        $logger = Mockery::mock(LoggerInterface::class);
        $this->app->instance(LoggerInterface::class, $logger);

        $logger->shouldReceive('info')
            ->once()
            ->with(Mockery::on(function (LogDataRecord $log) {
                return $log->type === 'auth'
                    && $log->payload->event === 'user_registration_failed'
                    && $log->payload->model_type === TestUserMail::class
                    && str_contains($log->payload->error, 'email')
                    && isset($log->payload->platform)
                    && isset($log->payload->browser)
                    && isset($log->payload->device_type)
                    && isset($log->payload->ip)
                    && isset($log->payload->user_agent);
            }));

        $payload = [
            'model_type' => TestUserMail::class,
            'name' => 'John Doe',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ];

        $response = $this->postJson('/api/register', $payload);

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

        $response = $this->postJson('/api/register', $payload);

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

        $response = $this->postJson('/api/register', $payload);

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

        $response = $this->postJson('/api/register', $payload);

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

        $response = $this->postJson('/api/register', $payload);

        $response->assertStatus(500);
        $response->assertSee('Server Error');
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

        $this->postJson('/api/register', $payload);

        $response = $this->postJson('/api/register', $payload);

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

        $response = $this->postJson('/api/register', $payload);

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

        $response = $this->postJson('/api/register', $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['model_type']);
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

        $response = $this->postJson('/api/register', $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['model_type']);
    }
}
