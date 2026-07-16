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
use AndyDefer\AuthenticationKit\Mail\Datas\AuthRegisteredData;
use AndyDefer\AuthenticationKit\Mail\Datas\ErrorResponseData;
use AndyDefer\AuthenticationKit\Mail\Records\EmailRegisterAuthRecord;
use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\DomainStructures\Utils\DataObject;
use AndyDefer\DomainStructures\Utils\EmptyRecord;
use AndyDefer\DomainStructures\Utils\StrictDataObject;
use AndyDefer\Nemesis\Contracts\Services\NemesisInterface;
use AndyDefer\Nemesis\Records\NemesisTokenRecord;
use Exception;

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

    public function __construct(
        private readonly NemesisInterface $nemesis,
        private readonly LogRepositoryInterface $logRepository,
        private readonly AgentInterface $agent,
        private readonly AuthenticationKitConfigInterface $config,
    ) {}

    /**
     * Prepares the action by extracting record data.
     *
     * @param  AbstractRecord  $record  The registration request record
     *
     * @throws \InvalidArgumentException When the record type is invalid
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
     *
     * @param  AbstractRecord  $record  The registration request record
     * @return ResponseFactory The HTTP response
     */
    protected function handle(AbstractRecord $record): ResponseFactory
    {
        if (! $record instanceof EmailRegisterAuthRecord) {
            return ResponseFactory::json(
                new ErrorResponseData(
                    message: 'Invalid record type',
                    status: 500,
                    errorCode: 'INVALID_RECORD_TYPE'
                ),
                500
            );
        }

        $modelClass = $record->model_type;

        if (! class_exists($modelClass)) {
            return ResponseFactory::json(
                new ErrorResponseData(
                    message: "Model {$modelClass} does not exist",
                    status: 500,
                    errorCode: 'MODEL_NOT_FOUND'
                ),
                500
            );
        }

        if (! in_array(MailAuthenticatable::class, class_implements($modelClass) ?: [], true)) {
            return ResponseFactory::json(
                new ErrorResponseData(
                    message: "Model {$modelClass} must implement ".MailAuthenticatable::class,
                    status: 500,
                    errorCode: 'INVALID_MODEL'
                ),
                500
            );
        }

        /** @var MailAuthenticatable $modelClass */
        $service = $modelClass::getMailAuthService();

        $auth = $service->register($record);

        $this->authId = $auth->getKey();

        $token = null;

        if ($record->with_token) {
            [$tokenModel, $plainToken] = $this->nemesis->createWithPlainToken(
                new NemesisTokenRecord(
                    name: $this->config->getTokenName(),
                    source: TokenSource::REGISTER->value,
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

            $token = $plainToken;
        }

        return ResponseFactory::json(
            new AuthRegisteredData(
                message: 'Registration successful',
                auth: DataObject::from($auth->nemesisFormat()),
                token: $token,
            ),
            201
        );
    }

    /**
     * Logs the registration attempt result.
     *
     * @param  bool  $success  Whether the operation succeeded
     * @param  Exception|null  $error  The exception if one occurred
     * @param  AbstractRecord  $record  The original request record
     */
    protected function after(bool $success, ?Exception $error = null, AbstractRecord $record = new EmptyRecord): void
    {
        if ($success && $this->authId !== null) {
            $this->logRepository->logRegistrationSuccess(
                authId: $this->authId,
                modelClass: $this->modelClass,
                withToken: $this->withToken,
            );
        }

        if (! $success && $error !== null) {
            $this->logRepository->logRegistrationFailure(
                modelClass: $this->modelClass ?? 'unknown',
                error: $error->getMessage(),
                errorClass: get_class($error),
            );
        }
    }
}
