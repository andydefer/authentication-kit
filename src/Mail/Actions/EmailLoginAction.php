<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Mail\Actions;

use AndyDefer\Actions\Actions\AbstractAction;
use AndyDefer\Actions\Http\ResponseFactory;
use AndyDefer\AuthenticationKit\Contracts\Configs\AuthenticationKitConfigInterface;
use AndyDefer\AuthenticationKit\Contracts\Services\AgentInterface;
use AndyDefer\AuthenticationKit\Enums\ErrorCode;
use AndyDefer\AuthenticationKit\Enums\ErrorType;
use AndyDefer\AuthenticationKit\Mail\Contracts\MailAuthenticatable;
use AndyDefer\AuthenticationKit\Mail\Contracts\Repositories\LogRepositoryInterface;
use AndyDefer\AuthenticationKit\Mail\Datas\AuthLoginData;
use AndyDefer\AuthenticationKit\Mail\Records\EmailLoginAuthRecord;
use AndyDefer\AuthenticationKit\Mail\Records\LoginResultRecord;
use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\DomainStructures\Utils\DataObject;
use AndyDefer\DomainStructures\Utils\EmptyRecord;
use AndyDefer\Nemesis\Contracts\Services\NemesisInterface;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * Handles email-based user login authentication.
 *
 * This action validates user credentials, creates an authentication token
 * upon successful login, and logs the authentication attempt.
 */
final class EmailLoginAction extends AbstractAction
{
    private mixed $modelClass;

    private ?string $email = null;

    private ?int $authId = null;

    private bool $success = false;

    private ?string $ip = null;

    private ?string $userAgent = null;

    private ?string $errorMessage = null;

    private ?ErrorType $errorType = null;

    public function __construct(
        private readonly NemesisInterface $nemesis,
        private readonly LogRepositoryInterface $logRepository,
        private readonly AgentInterface $agent,
        private readonly AuthenticationKitConfigInterface $config,
    ) {}

    /**
     * Prepares the action by extracting record data.
     */
    protected function before(AbstractRecord $record): void
    {
        if (! $record instanceof EmailLoginAuthRecord) {
            throw new \InvalidArgumentException('Invalid record type');
        }

        $this->modelClass = $record->model_type;
        $this->ip = $record->ip;
        $this->userAgent = $record->user_agent;
    }

    /**
     * Processes the login request.
     */
    protected function handle(AbstractRecord $record): ResponseFactory
    {
        if (! $record instanceof EmailLoginAuthRecord) {
            return ResponseFactory::json(
                ErrorCode::INVALID_RECORD_TYPE->toResponseData(),
                ErrorCode::INVALID_RECORD_TYPE->getHttpStatusCode()->value,
            );
        }

        try {
            /** @var MailAuthenticatable&Model $modelClass */
            $modelClass = $this->modelClass;

            $email = $record->data->get('email');
            $password = $record->data->get('password');

            if ($email === null || $password === null) {
                $this->success = false;
                $this->email = $email ?? 'unknown';
                $this->errorMessage = ErrorCode::MISSING_CREDENTIALS->getMessage();
                $this->errorType = ErrorType::MISSING_CREDENTIALS;

                $errors = [];
                if ($email === null) {
                    $errors['email'] = ['The email field is required.'];
                }
                if ($password === null) {
                    $errors['password'] = ['The password field is required.'];
                }

                return ResponseFactory::json(
                    ErrorCode::MISSING_CREDENTIALS->toResponseData(errors: $errors),
                    ErrorCode::MISSING_CREDENTIALS->getHttpStatusCode()->value,
                );
            }

            $this->email = $email;

            $service = $modelClass::getMailAuthService();

            /** @var LoginResultRecord|null $loginResult */
            $loginResult = $service->login($email, $password);

            if ($loginResult === null) {
                $this->success = false;
                $this->errorMessage = ErrorCode::INVALID_CREDENTIALS->getMessage();
                $this->errorType = ErrorType::INVALID_CREDENTIALS;

                return ResponseFactory::json(
                    ErrorCode::INVALID_CREDENTIALS->toResponseData(),
                    ErrorCode::INVALID_CREDENTIALS->getHttpStatusCode()->value,
                );
            }

            $auth = $modelClass::where('email', $email)->first();

            if ($auth === null) {
                $this->success = false;
                $this->errorMessage = ErrorCode::AUTHENTICATABLE_NOT_FOUND->getMessage();
                $this->errorType = ErrorType::USER_NOT_FOUND;

                return ResponseFactory::json(
                    ErrorCode::AUTHENTICATABLE_NOT_FOUND->toResponseData(),
                    ErrorCode::AUTHENTICATABLE_NOT_FOUND->getHttpStatusCode()->value,
                );
            }

            $this->authId = $auth->getKey();
            $this->success = true;

            return ResponseFactory::json(
                new AuthLoginData(
                    message: 'Login successful',
                    auth: DataObject::from($auth->nemesisFormat()),
                    token: $loginResult->plain_token,
                ),
                200,
            );

        } catch (ValidationException $e) {
            $this->success = false;
            $this->errorMessage = $e->getMessage();
            $this->errorType = ErrorType::VALIDATION_ERROR;

            return ResponseFactory::json(
                ErrorCode::VALIDATION_ERROR->toResponseData(errors: $e->errors()),
                ErrorCode::VALIDATION_ERROR->getHttpStatusCode()->value,
            );
        } catch (Exception $e) {
            $this->success = false;
            $this->errorMessage = $e->getMessage();
            $this->errorType = ErrorType::VALIDATION_ERROR;

            return ResponseFactory::json(
                ErrorCode::LOGIN_ERROR->toResponseData(),
                ErrorCode::LOGIN_ERROR->getHttpStatusCode()->value,
            );
        }
    }

    /**
     * Logs the login attempt result.
     */
    protected function after(bool $success, ?Exception $error = null, AbstractRecord $record = new EmptyRecord): void
    {
        if ($this->success && $this->authId !== null) {
            $this->logRepository->loginSuccess(
                authId: $this->authId,
                modelClass: $this->modelClass,
                email: $this->email ?? 'unknown',
            );

            return;
        }

        if (! $this->success) {
            $errorType = $this->errorType ?? ErrorType::INVALID_CREDENTIALS;
            $errorMessage = $this->errorMessage ?? ($error !== null ? $error->getMessage() : 'Unknown error');

            $this->logRepository->loginFailure(
                modelClass: $this->modelClass ?? 'unknown',
                email: $this->email ?? 'unknown',
                error: $errorMessage,
                errorType: $errorType,
            );
        }
    }
}
