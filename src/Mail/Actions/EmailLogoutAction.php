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

        // ✅ Utiliser un algorithme de hachage valide
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

        // 🔥 Vérifier si le token est expiré
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

        // Récupérer l'authenticatable depuis le token
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

        // Récupérer le service d'authentification
        $service = $modelClass::getMailAuthService();

        // Effectuer la déconnexion via le service
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

    protected function after(bool $success, ?Exception $error = null, AbstractRecord $record = new EmptyRecord): void
    {
        if ($this->success && $this->authId !== null) {
            $this->logRepository->logoutSuccess(
                authId: $this->authId,
                modelClass: $this->modelClass,
                email: $this->email ?? 'unknown',
            );
        }

        // 🔥 MODIFICATION : Logger l'échec même si aucune exception n'a été levée
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
