<?php

// src/Mail/Actions/EmailRegisterAction.php

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
use AndyDefer\AuthenticationKit\Mail\Datas\AuthRegisteredData;
use AndyDefer\AuthenticationKit\Mail\Records\EmailRegisterAuthRecord;
use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\DomainStructures\Utils\DataObject;
use AndyDefer\DomainStructures\Utils\EmptyRecord;
use Exception;
use Illuminate\Validation\ValidationException;

/**
 * Handles user registration via email authentication.
 *
 * This action creates a new user account, optionally generates an authentication
 * token, and logs the registration attempt.
 */
final class EmailRegisterAction extends AbstractAction
{
    private mixed $modelClass;

    private ?int $authId = null;

    private bool $withToken = false;

    private ?string $ip = null;

    private ?string $userAgent = null;

    private ?string $errorMessage = null;

    private ?ErrorType $errorType = null;

    public function __construct(
        private readonly LogRepositoryInterface $logRepository,
        private readonly AgentInterface $agent,
        private readonly AuthenticationKitConfigInterface $config,
    ) {}

    /**
     * Prepares the action by extracting record data.
     */
    protected function before(AbstractRecord $record): void
    {
        if (! $record instanceof EmailRegisterAuthRecord) {
            throw new \InvalidArgumentException('Invalid record type');
        }

        $this->modelClass = $record->model_type;
        $this->withToken = $record->with_token;
        $this->ip = $record->ip;
        $this->userAgent = $record->user_agent;
    }

    /**
     * Processes the registration request.
     */
    protected function handle(AbstractRecord $record): ResponseFactory
    {
        if (! $record instanceof EmailRegisterAuthRecord) {
            return ResponseFactory::json(
                ErrorCode::INVALID_RECORD_TYPE->toResponseData(),
                ErrorCode::INVALID_RECORD_TYPE->getHttpStatusCode()->value,
            );
        }

        $modelClass = $record->model_type;

        if (! class_exists($modelClass)) {
            return ResponseFactory::json(
                ErrorCode::MODEL_NOT_FOUND->toResponseData(),
                ErrorCode::MODEL_NOT_FOUND->getHttpStatusCode()->value,
            );
        }

        if (! in_array(MailAuthenticatable::class, class_implements($modelClass) ?: [], true)) {
            return ResponseFactory::json(
                ErrorCode::INVALID_MODEL->toResponseData(),
                ErrorCode::INVALID_MODEL->getHttpStatusCode()->value,
            );
        }

        try {
            /** @var MailAuthenticatable $modelClass */
            $service = $modelClass::getMailAuthService();

            $result = $service->register($record);

            $auth = $result['user'];
            $plainToken = $result['plain_token'];

            $this->authId = $auth->getKey();

            return ResponseFactory::json(
                new AuthRegisteredData(
                    message: $record->with_token
                        ? 'User registered successfully with token'
                        : 'User registered successfully without token',
                    auth: DataObject::from($auth->nemesisFormat()),
                    token: $plainToken,
                ),
                201,
            );

        } catch (ValidationException $e) {
            $this->errorMessage = $e->getMessage();
            $this->errorType = ErrorType::VALIDATION_ERROR;

            return ResponseFactory::json(
                ErrorCode::VALIDATION_ERROR->toResponseData(errors: $e->errors()),
                ErrorCode::VALIDATION_ERROR->getHttpStatusCode()->value,
            );
        } catch (Exception $e) {
            $this->errorMessage = $e->getMessage();
            $this->errorType = ErrorType::VALIDATION_ERROR;

            return ResponseFactory::json(
                ErrorCode::REGISTRATION_ERROR->toResponseData(),
                ErrorCode::REGISTRATION_ERROR->getHttpStatusCode()->value,
            );
        }
    }

    /**
     * Logs the registration attempt result.
     */
    protected function after(bool $success, ?Exception $error = null, AbstractRecord $record = new EmptyRecord): void
    {
        if ($this->authId !== null) {
            $this->logRepository->logRegistrationSuccess(
                authId: $this->authId,
                modelClass: $this->modelClass,
                withToken: $this->withToken,
            );

            return;
        }

        $errorType = $this->errorType ?? ErrorType::VALIDATION_ERROR;
        $errorMessage = $this->errorMessage ?? ($error !== null ? $error->getMessage() : 'Unknown error');

        $this->logRepository->logRegistrationFailure(
            modelClass: $this->modelClass ?? 'unknown',
            error: $errorMessage,
            errorType: $errorType,
        );
    }
}
