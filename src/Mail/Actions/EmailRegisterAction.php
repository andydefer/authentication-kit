<?php

// src/Mail/Actions/EmailRegisterAction.php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Mail\Actions;

use AndyDefer\Actions\Actions\AbstractAction;
use AndyDefer\Actions\Http\ResponseFactory;
use AndyDefer\AuthenticationKit\Contracts\Configs\AuthenticationKitConfigInterface;
use AndyDefer\AuthenticationKit\Enums\ErrorCode;
use AndyDefer\AuthenticationKit\Enums\ErrorType;
use AndyDefer\AuthenticationKit\Mail\Contracts\MailAuthenticatable;
use AndyDefer\AuthenticationKit\Mail\Contracts\Repositories\LogRepositoryInterface;
use AndyDefer\AuthenticationKit\Mail\Datas\AuthRegisteredData;
use AndyDefer\AuthenticationKit\Mail\Records\EmailRegisterAuthRecord;
use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\DomainStructures\Utils\DataObject;
use AndyDefer\DomainStructures\Utils\EmptyRecord;
use AndyDefer\Nemesis\Contracts\Services\AgentServiceInterface;
use Exception;
use Illuminate\Validation\ValidationException;

/**
 * Handles user registration via email authentication.
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
        private readonly AgentServiceInterface $agent,
        private readonly AuthenticationKitConfigInterface $config,
    ) {}

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

    protected function handle(AbstractRecord $record): ResponseFactory
    {
        if (! $record instanceof EmailRegisterAuthRecord) {
            return ErrorCode::INVALID_RECORD_TYPE->toJsonResponseFactory();
        }

        $modelClass = $record->model_type;

        if (! class_exists($modelClass)) {
            return ErrorCode::MODEL_NOT_FOUND->toJsonResponseFactory();
        }

        if (! in_array(MailAuthenticatable::class, class_implements($modelClass) ?: [], true)) {
            return ErrorCode::INVALID_MODEL->toJsonResponseFactory();
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

            return ErrorCode::VALIDATION_ERROR->toJsonResponseFactory(errors: $e->errors());
        } catch (Exception $e) {
            $this->errorMessage = $e->getMessage();
            $this->errorType = ErrorType::VALIDATION_ERROR;

            return ErrorCode::REGISTRATION_ERROR->toJsonResponseFactory();
        }
    }

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
