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

    protected function handle(AbstractRecord $record): ResponseFactory
    {
        if (! $record instanceof EmailLogoutAuthRecord) {
            $this->success = false;
            $this->errorMessage = ErrorCode::INVALID_RECORD_TYPE->getMessage();
            $this->errorType = ErrorType::INVALID_RECORD_TYPE;

            return ErrorCode::INVALID_RECORD_TYPE->toJsonResponseFactory();
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

            return ErrorCode::INVALID_TOKEN->toJsonResponseFactory();
        }

        if ($tokenModel->isExpired()) {
            $this->success = false;
            $this->errorMessage = ErrorCode::TOKEN_EXPIRED->getMessage();
            $this->errorType = ErrorType::TOKEN_EXPIRED;

            return ErrorCode::TOKEN_EXPIRED->toJsonResponseFactory();
        }

        $tokenableType = $tokenModel->tokenable_type;
        $tokenableId = $tokenModel->tokenable_id;

        if ($tokenableType === null || $tokenableId === null) {
            $this->success = false;
            $this->errorMessage = ErrorCode::INVALID_TOKEN->getMessage();
            $this->errorType = ErrorType::INVALID_TOKEN;

            return ErrorCode::INVALID_TOKEN->toJsonResponseFactory();
        }

        $auth = $tokenableType::find($tokenableId);

        if ($auth === null) {
            $this->success = false;
            $this->errorMessage = ErrorCode::AUTHENTICATABLE_NOT_FOUND->getMessage();
            $this->errorType = ErrorType::USER_NOT_FOUND;

            return ErrorCode::AUTHENTICATABLE_NOT_FOUND->toJsonResponseFactory();
        }

        $service = $modelClass::getMailAuthService();

        try {
            $result = $service->logout($auth, $plainToken);
        } catch (Exception $e) {
            $this->success = false;
            $this->errorMessage = $e->getMessage();
            $this->errorType = ErrorType::TOKEN_REVOKE_FAILED;

            return ErrorCode::LOGOUT_EXCEPTION->toJsonResponseFactory(
                message: ErrorCode::LOGOUT_EXCEPTION->getMessage().': '.$e->getMessage(),
            );
        }

        if (! $result) {
            $this->success = false;
            $this->errorMessage = ErrorCode::LOGOUT_FAILED->getMessage();
            $this->errorType = ErrorType::TOKEN_REVOKE_FAILED;

            return ErrorCode::LOGOUT_FAILED->toJsonResponseFactory();
        }

        $this->authId = $auth->getKey();
        $this->email = action_normalizer_chain(true)->normalize($auth->email) ?? null;
        $this->success = true;

        return ResponseFactory::json(new EmptyData, 204);
    }

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
