<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Mail\Actions;

use AndyDefer\Actions\Actions\AbstractAction;
use AndyDefer\Actions\Http\ResponseFactory;
use AndyDefer\AuthenticationKit\Contracts\Configs\AuthenticationKitConfigInterface;
use AndyDefer\AuthenticationKit\Contracts\Services\AgentInterface;
use AndyDefer\AuthenticationKit\Enums\TokenSource;
use AndyDefer\AuthenticationKit\Mail\Contracts\MailAuthenticatable;
use AndyDefer\AuthenticationKit\Mail\Contracts\Repositories\LogRepositoryInterface;
use AndyDefer\AuthenticationKit\Mail\Datas\AuthLoginData;
use AndyDefer\AuthenticationKit\Mail\Datas\ErrorResponseData;
use AndyDefer\AuthenticationKit\Mail\Records\EmailLoginAuthRecord;
use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\DomainStructures\Utils\DataObject;
use AndyDefer\DomainStructures\Utils\EmptyRecord;
use AndyDefer\DomainStructures\Utils\StrictDataObject;
use AndyDefer\Nemesis\Contracts\Services\NemesisInterface;
use AndyDefer\Nemesis\Records\NemesisTokenRecord;
use Exception;
use Illuminate\Database\Eloquent\Model;

final class EmailLoginAction extends AbstractAction
{
    private mixed $modelClass;

    private ?string $email = null;

    private ?int $authId = null;

    private bool $success = false;

    private ?string $ip = null;

    private ?string $userAgent = null;

    public function __construct(
        private readonly NemesisInterface $nemesis,
        private readonly LogRepositoryInterface $logRepository,
        private readonly AgentInterface $agent,
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
            throw new \InvalidArgumentException('Invalid record type');
        }

        /** @var MailAuthenticatable&Model $modelClass */
        $modelClass = $this->modelClass;

        $email = $record->data->get('email');
        $password = $record->data->get('password');

        if ($email === null || $password === null) {
            $this->success = false;
            $this->email = $email ?? 'unknown';

            return ResponseFactory::json(
                new ErrorResponseData(
                    message: 'Email and password are required',
                    status: 400,
                    errorCode: 'MISSING_CREDENTIALS'
                ),
                400
            );
        }

        $this->email = $email;

        $service = $modelClass::getMailAuthService();

        $tokenRecord = $service->login($email, $password);

        if ($tokenRecord === null) {
            $this->success = false;

            return ResponseFactory::json(
                new ErrorResponseData(
                    message: 'Invalid credentials',
                    status: 401,
                    errorCode: 'INVALID_CREDENTIALS'
                ),
                401
            );
        }

        $auth = $modelClass::where('email', $email)->first();

        if ($auth === null) {
            $this->success = false;

            return ResponseFactory::json(
                new ErrorResponseData(
                    message: 'Authenticatable not found',
                    status: 404,
                    errorCode: 'AUTHENTICATABLE_NOT_FOUND'
                ),
                404
            );
        }

        $this->authId = $auth->getKey();
        $this->success = true;

        // ✅ Utilisation de l'ip et user_agent du record
        [$tokenModel, $plainToken] = $this->nemesis->createWithPlainToken(
            new NemesisTokenRecord(
                name: $this->config->getTokenName(),
                source: TokenSource::LOGIN->value,
                metadata: new StrictDataObject([
                    'device_type' => $this->agent->deviceType(),
                    'platform' => $this->agent->platform(),
                    'browser' => $this->agent->browser(),
                    'ip' => $this->ip,
                    'user_agent' => $this->userAgent,
                ]),
            ),
            $auth
        );

        return ResponseFactory::json(
            new AuthLoginData(
                message: 'Login successful',
                auth: DataObject::from($auth->nemesisFormat()),
                token: $plainToken,
            ),
            200
        );
    }

    protected function after(bool $success, ?Exception $error = null, AbstractRecord $record = new EmptyRecord): void
    {
        if ($this->success && $this->authId !== null) {
            $this->logRepository->logLoginSuccess(
                authId: $this->authId,
                modelClass: $this->modelClass,
                email: $this->email ?? 'unknown',
            );
        }

        if (! $this->success && $error !== null) {
            $this->logRepository->logLoginFailure(
                modelClass: $this->modelClass ?? 'unknown',
                email: $this->email ?? 'unknown',
                error: $error->getMessage(),
                errorClass: get_class($error),
            );
        }
    }
}
