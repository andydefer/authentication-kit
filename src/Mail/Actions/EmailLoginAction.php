<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Mail\Actions;

use AndyDefer\Actions\Actions\AbstractAction;
use AndyDefer\Actions\Http\ResponseFactory;
use AndyDefer\AuthenticationKit\Contracts\Configs\AuthenticationKitConfigInterface;
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
use AndyDefer\Nemesis\Contracts\Services\AgentServiceInterface;
use AndyDefer\Nemesis\Contracts\Services\NemesisInterface;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * Handles email-based user login authentication.
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
        private readonly AgentServiceInterface $agent,
        private readonly AuthenticationKitConfigInterface $config,
    ) {}

    protected function before(AbstractRecord $record): void
    {
        if (! $record instanceof EmailLoginAuthRecord) {
            throw new \InvalidArgumentException('Invalid record type');
        }

        $this->modelClass = $record->model_type;
        $this->ip = $record->ip;
        $this->userAgent = $record->user_agent;
    }

    protected function handle(AbstractRecord $record): ResponseFactory
    {
        if (! $record instanceof EmailLoginAuthRecord) {
            return ErrorCode::INVALID_RECORD_TYPE->toJsonResponseFactory();
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

                return ErrorCode::MISSING_CREDENTIALS->toJsonResponseFactory(errors: $errors);
            }

            $this->email = $email;

            $service = $modelClass::getMailAuthService();

            /** @var LoginResultRecord|null $loginResult */
            $loginResult = $service->login($email, $password);

            if ($loginResult === null) {
                $this->success = false;
                $this->errorMessage = ErrorCode::INVALID_CREDENTIALS->getMessage();
                $this->errorType = ErrorType::INVALID_CREDENTIALS;

                return ErrorCode::INVALID_CREDENTIALS->toJsonResponseFactory();
            }

            $auth = $modelClass::where('email', $email)->first();

            if ($auth === null) {
                $this->success = false;
                $this->errorMessage = ErrorCode::AUTHENTICATABLE_NOT_FOUND->getMessage();
                $this->errorType = ErrorType::USER_NOT_FOUND;

                return ErrorCode::AUTHENTICATABLE_NOT_FOUND->toJsonResponseFactory();
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

            return ErrorCode::VALIDATION_ERROR->toJsonResponseFactory(errors: $e->errors());
        } catch (Exception $e) {
            $this->success = false;
            $this->errorMessage = $e->getMessage();
            $this->errorType = ErrorType::VALIDATION_ERROR;

            return ErrorCode::LOGIN_ERROR->toJsonResponseFactory();
        }
    }

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
