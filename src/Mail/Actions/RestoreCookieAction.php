<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Mail\Actions;

use AndyDefer\Actions\Actions\AbstractAction;
use AndyDefer\Actions\Http\ResponseFactory;
use AndyDefer\AuthenticationKit\Enums\ErrorCode;
use AndyDefer\AuthenticationKit\Enums\ErrorType;
use AndyDefer\AuthenticationKit\Mail\Contracts\MailAuthenticatable;
use AndyDefer\AuthenticationKit\Mail\Contracts\Repositories\LogRepositoryInterface;
use AndyDefer\AuthenticationKit\Mail\Datas\AuthCookieRestoredData;
use AndyDefer\AuthenticationKit\Mail\Records\RestoreCookieAuthRecord;
use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\DomainStructures\Utils\DataObject;
use AndyDefer\DomainStructures\Utils\EmptyRecord;
use Exception;
use Illuminate\Database\Eloquent\Model;

/**
 * Restores a missing authentication cookie from a plain token still held by
 * the client.
 *
 * The route is public: the cookie may have been dropped by the browser,
 * but the client still holds the plain token (typically in localStorage).
 * The action delegates to the model's authentication service, which
 * verifies the token against its stored hash before writing the cookie.
 */
final class RestoreCookieAction extends AbstractAction
{
    private mixed $modelClass;

    private int|string|null $authId = null;

    private bool $success = false;

    private ?string $errorMessage = null;

    private ?ErrorType $errorType = null;

    public function __construct(
        private readonly LogRepositoryInterface $logRepository,
    ) {}

    protected function before(AbstractRecord $record): void
    {
        if (! $record instanceof RestoreCookieAuthRecord) {
            throw new \InvalidArgumentException('Invalid record type');
        }

        $this->modelClass = $record->model_type;
    }

    protected function handle(AbstractRecord $record): ResponseFactory
    {
        if (! $record instanceof RestoreCookieAuthRecord) {
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
            /** @var MailAuthenticatable&Model $modelClass */
            $service = $modelClass::getMailAuthService();

            $result = $service->restoreCookie($record);

            if ($result === null) {
                $this->success = false;
                $this->errorMessage = ErrorCode::INVALID_TOKEN->getMessage();
                $this->errorType = ErrorType::INVALID_CREDENTIALS;

                return ErrorCode::INVALID_TOKEN->toJsonResponseFactory();
            }

            /** @var Model&MailAuthenticatable $auth */
            $auth = $result;

            $this->authId = $auth->getKey();
            $this->success = true;

            return ResponseFactory::json(
                new AuthCookieRestoredData(
                    message: 'Cookie restored successfully',
                    auth: DataObject::from($auth->nemesisFormat()),
                ),
                200,
            );
        } catch (Exception $e) {
            $this->success = false;
            $this->errorMessage = $e->getMessage();
            $this->errorType = ErrorType::VALIDATION_ERROR;

            return ErrorCode::LOGIN_ERROR->toJsonResponseFactory(
                message: $e->getMessage(),
            );
        }
    }

    protected function after(bool $success, ?Exception $error = null, AbstractRecord $record = new EmptyRecord): void
    {
        if ($this->success && $this->authId !== null) {
            $this->logRepository->loginSuccess(
                authId: $this->authId,
                modelClass: $this->modelClass,
                email: 'cookie-restore',
            );

            return;
        }

        $errorType = $this->errorType ?? ErrorType::INVALID_CREDENTIALS;
        $errorMessage = $this->errorMessage ?? ($error !== null ? $error->getMessage() : 'Unknown error');

        $this->logRepository->loginFailure(
            modelClass: $this->modelClass ?? 'unknown',
            email: 'cookie-restore',
            error: $errorMessage,
            errorType: $errorType,
        );
    }
}
