<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Mail\Actions;

use AndyDefer\Actions\Actions\AbstractAction;
use AndyDefer\Actions\Http\ResponseFactory;
use AndyDefer\AuthenticationKit\Mail\Contracts\MailAuthenticatable;
use AndyDefer\AuthenticationKit\Mail\Contracts\Repositories\LogRepositoryInterface;
use AndyDefer\AuthenticationKit\Mail\Datas\ErrorResponseData;
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

    public function __construct(
        private readonly NemesisInterface $nemesis,
        private readonly LogRepositoryInterface $logRepository,
    ) {}

    /**
     * Prepares the action by validating the record and model class.
     *
     * @param  AbstractRecord  $record  The logout request record
     *
     * @throws \InvalidArgumentException When the record type is invalid or model doesn't exist
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
     *
     * @param  AbstractRecord  $record  The logout request record
     * @return ResponseFactory The HTTP response
     */
    protected function handle(AbstractRecord $record): ResponseFactory
    {
        if (! $record instanceof EmailLogoutAuthRecord) {
            $this->success = false;
            $this->errorMessage = 'Invalid record type';

            return ResponseFactory::json(
                new ErrorResponseData(
                    message: 'Invalid record type',
                    status: 500,
                    errorCode: 'INVALID_RECORD_TYPE'
                ),
                500
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
            $this->errorMessage = 'Invalid token';

            return ResponseFactory::json(
                new ErrorResponseData(
                    message: 'Invalid token',
                    status: 401,
                    errorCode: 'INVALID_TOKEN'
                ),
                401
            );
        }

        if ($tokenModel->isExpired()) {
            $this->success = false;
            $this->errorMessage = 'Token expired';

            return ResponseFactory::json(
                new ErrorResponseData(
                    message: 'Token expired',
                    status: 401,
                    errorCode: 'TOKEN_EXPIRED'
                ),
                401
            );
        }

        $tokenableType = $tokenModel->tokenable_type;
        $tokenableId = $tokenModel->tokenable_id;

        if ($tokenableType === null || $tokenableId === null) {
            $this->success = false;
            $this->errorMessage = 'Invalid token';

            return ResponseFactory::json(
                new ErrorResponseData(
                    message: 'Invalid token',
                    status: 401,
                    errorCode: 'INVALID_TOKEN'
                ),
                401
            );
        }

        $auth = $tokenableType::find($tokenableId);

        if ($auth === null) {
            $this->success = false;
            $this->errorMessage = 'Authenticatable not found';

            return ResponseFactory::json(
                new ErrorResponseData(
                    message: 'Authenticatable not found',
                    status: 404,
                    errorCode: 'AUTHENTICATABLE_NOT_FOUND'
                ),
                404
            );
        }

        $service = $modelClass::getMailAuthService();

        try {
            $result = $service->logout($auth, $plainToken);
        } catch (Exception $e) {
            $this->success = false;
            $this->errorMessage = $e->getMessage();

            return ResponseFactory::json(
                new ErrorResponseData(
                    message: 'Logout failed: '.$e->getMessage(),
                    status: 500,
                    errorCode: 'LOGOUT_EXCEPTION'
                ),
                500
            );
        }

        if (! $result) {
            $this->success = false;
            $this->errorMessage = 'Logout failed';

            return ResponseFactory::json(
                new ErrorResponseData(
                    message: 'Logout failed',
                    status: 500,
                    errorCode: 'LOGOUT_FAILED'
                ),
                500
            );
        }

        $this->authId = $auth->getKey();
        $this->email = $auth->email ?? null;
        $this->success = true;

        return ResponseFactory::json(
            new EmptyData,
            204
        );
    }

    /**
     * Logs the logout attempt result.
     *
     * @param  bool  $success  Whether the operation succeeded
     * @param  Exception|null  $error  The exception if one occurred
     * @param  AbstractRecord  $record  The original request record
     */
    protected function after(bool $success, ?Exception $error = null, AbstractRecord $record = new EmptyRecord): void
    {
        if ($this->success && $this->authId !== null) {
            $this->logRepository->logoutSuccess(
                authId: $this->authId,
                modelClass: $this->modelClass,
                email: $this->email ?? 'unknown',
            );
        }

        if (! $this->success) {
            $errorMessage = $this->errorMessage ?? ($error !== null ? $error->getMessage() : 'Unknown error');
            $errorClass = $error !== null ? get_class($error) : 'NoException';

            $this->logRepository->logoutFailure(
                modelClass: $this->modelClass ?? 'unknown',
                email: $this->email ?? 'unknown',
                error: $errorMessage,
                errorClass: $errorClass,
            );
        }
    }
}
