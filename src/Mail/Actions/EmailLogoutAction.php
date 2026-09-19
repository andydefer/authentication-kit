<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Mail\Actions;

use AndyDefer\Actions\Actions\AbstractAction;
use AndyDefer\Actions\Http\ResponseFactory;
use AndyDefer\AuthenticationKit\Enums\ErrorCode;
use AndyDefer\AuthenticationKit\Enums\ErrorType;
use AndyDefer\AuthenticationKit\Mail\Contracts\MailAuthenticatable;
use AndyDefer\AuthenticationKit\Mail\Contracts\Repositories\LogRepositoryInterface;
use AndyDefer\AuthenticationKit\Mail\Records\EmailLogoutAuthRecord;
use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\DomainStructures\Utils\EmptyData;
use AndyDefer\DomainStructures\Utils\EmptyRecord;
use AndyDefer\Nemesis\Contracts\Services\NemesisInterface;
use Exception;
use Illuminate\Database\Eloquent\Model;

/**
 * Handles user logout by invalidating the authentication token.
 *
 * This action validates the provided token, finds the associated user,
 * and performs the logout operation through the authentication service.
 */
final class EmailLogoutAction extends AbstractAction
{
    private mixed $modelClass;

    private ?int $authId = null;

    private ?string $email = null;

    private bool $success = false;

    private ?string $errorMessage = null;

    private ?ErrorType $errorType = null;

    public function __construct(
        private readonly NemesisInterface $nemesis,
        private readonly LogRepositoryInterface $logRepository,
    ) {}

    /**
     * Prepares the action by validating the record and model class.
     */
    protected function before(AbstractRecord $record): void
    {
        if (! $record instanceof EmailLogoutAuthRecord) {
            throw new \InvalidArgumentException('Invalid record type');
        }

        $this->modelClass = $record->model_type;

        if (! class_exists($this->modelClass)) {
            throw new \InvalidArgumentException("Model {$this->modelClass} does not exist");
        }

        if (! in_array(MailAuthenticatable::class, class_implements($this->modelClass) ?: [], true)) {
            throw new \InvalidArgumentException(
                "Model {$this->modelClass} must implement ".MailAuthenticatable::class
            );
        }
    }

    /**
     * Processes the logout request.
     */
    protected function handle(AbstractRecord $record): ResponseFactory
    {
        if (! $record instanceof EmailLogoutAuthRecord) {
            $this->success = false;
            $this->errorMessage = ErrorCode::INVALID_RECORD_TYPE->getMessage();
            $this->errorType = ErrorType::INVALID_RECORD_TYPE;

            return ResponseFactory::json(
                ErrorCode::INVALID_RECORD_TYPE->toResponseData(),
                ErrorCode::INVALID_RECORD_TYPE->getHttpStatusCode()->value,
            );
        }

        /** @var MailAuthenticatable&Model $modelClass */
        $modelClass = $this->modelClass;

        $plainToken = $record->token;

        $tokenModel = $this->nemesis->findByHash(
            hash('sha256', $plainToken)
        );

        if ($tokenModel === null) {
            $this->success = false;
            $this->errorMessage = ErrorCode::INVALID_TOKEN->getMessage();
            $this->errorType = ErrorType::INVALID_TOKEN;

            return ResponseFactory::json(
                ErrorCode::INVALID_TOKEN->toResponseData(),
                ErrorCode::INVALID_TOKEN->getHttpStatusCode()->value,
            );
        }

        if ($tokenModel->isExpired()) {
            $this->success = false;
            $this->errorMessage = ErrorCode::TOKEN_EXPIRED->getMessage();
            $this->errorType = ErrorType::TOKEN_EXPIRED;

            return ResponseFactory::json(
                ErrorCode::TOKEN_EXPIRED->toResponseData(),
                ErrorCode::TOKEN_EXPIRED->getHttpStatusCode()->value,
            );
        }

        $tokenableType = $tokenModel->tokenable_type;
        $tokenableId = $tokenModel->tokenable_id;

        if ($tokenableType === null || $tokenableId === null) {
            $this->success = false;
            $this->errorMessage = ErrorCode::INVALID_TOKEN->getMessage();
            $this->errorType = ErrorType::INVALID_TOKEN;

            return ResponseFactory::json(
                ErrorCode::INVALID_TOKEN->toResponseData(),
                ErrorCode::INVALID_TOKEN->getHttpStatusCode()->value,
            );
        }

        $auth = $tokenableType::find($tokenableId);

        if ($auth === null) {
            $this->success = false;
            $this->errorMessage = ErrorCode::AUTHENTICATABLE_NOT_FOUND->getMessage();
            $this->errorType = ErrorType::USER_NOT_FOUND;

            return ResponseFactory::json(
                ErrorCode::AUTHENTICATABLE_NOT_FOUND->toResponseData(),
                ErrorCode::AUTHENTICATABLE_NOT_FOUND->getHttpStatusCode()->value,
            );
        }

        $service = $modelClass::getMailAuthService();

        try {
            $result = $service->logout($auth, $plainToken);
        } catch (Exception $e) {
            $this->success = false;
            $this->errorMessage = $e->getMessage();
            $this->errorType = ErrorType::TOKEN_REVOKE_FAILED;

            return ResponseFactory::json(
                ErrorCode::LOGOUT_EXCEPTION->toResponseData(
                    message: ErrorCode::LOGOUT_EXCEPTION->getMessage().': '.$e->getMessage(),
                ),
                ErrorCode::LOGOUT_EXCEPTION->getHttpStatusCode()->value,
            );
        }

        if (! $result) {
            $this->success = false;
            $this->errorMessage = ErrorCode::LOGOUT_FAILED->getMessage();
            $this->errorType = ErrorType::TOKEN_REVOKE_FAILED;

            return ResponseFactory::json(
                ErrorCode::LOGOUT_FAILED->toResponseData(),
                ErrorCode::LOGOUT_FAILED->getHttpStatusCode()->value,
            );
        }

        $this->authId = $auth->getKey();
        $this->email = action_normalizer_chain(true)->normalize($auth->email) ?? null;
        $this->success = true;

        return ResponseFactory::json(
            new EmptyData,
            204,
        );
    }

    /**
     * Logs the logout attempt result.
     */
    protected function after(bool $success, ?Exception $error = null, AbstractRecord $record = new EmptyRecord): void
    {
        if ($this->success && $this->authId !== null) {
            $this->logRepository->logoutSuccess(
                authId: $this->authId,
                modelClass: $this->modelClass,
                email: $this->email ?? 'unknown',
            );

            return;
        }

        if (! $this->success) {
            $errorType = $this->errorType ?? ErrorType::TOKEN_REVOKE_FAILED;
            $errorMessage = $this->errorMessage ?? ($error !== null ? $error->getMessage() : 'Unknown error');

            $this->logRepository->logoutFailure(
                modelClass: $this->modelClass ?? 'unknown',
                email: $this->email ?? 'unknown',
                error: $errorMessage,
                errorType: $errorType,
            );
        }
    }
}
