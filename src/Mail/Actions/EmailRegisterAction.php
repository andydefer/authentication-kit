<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Mail\Actions;

use AndyDefer\Actions\Actions\AbstractAction;
use AndyDefer\Actions\Http\ResponseFactory;
use AndyDefer\AuthenticationKit\Configs\AuthenticationKitConfig;
use AndyDefer\AuthenticationKit\Enums\TokenSource;
use AndyDefer\AuthenticationKit\Mail\Contracts\MailAuthenticatable;
use AndyDefer\AuthenticationKit\Mail\Data\UserRegisteredData;
use AndyDefer\AuthenticationKit\Mail\Records\EmailRegisterUserRecord;
use AndyDefer\AuthenticationKit\Mail\Repositories\LogRepository;
use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\DomainStructures\Utils\DataObject;
use AndyDefer\DomainStructures\Utils\EmptyRecord;
use AndyDefer\DomainStructures\Utils\StrictDataObject;
use AndyDefer\Nemesis\Records\NemesisTokenRecord;
use AndyDefer\Nemesis\Services\NemesisService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator as ValidatorInstance;
use Jenssegers\Agent\Agent;

final class EmailRegisterAction extends AbstractAction
{
    private string $modelClass;

    private ValidatorInstance $validator;

    private ?int $userId = null;

    private bool $withToken = false;

    public function __construct(
        private readonly NemesisService $nemesis,
        private readonly LogRepository $logRepository,
        private readonly Agent $agent,
        private readonly Request $request,
        private readonly AuthenticationKitConfig $config,
    ) {}

    protected function before(AbstractRecord $record): void
    {
        if (! $record instanceof EmailRegisterUserRecord) {
            throw new \InvalidArgumentException('Invalid record type');
        }

        $this->modelClass = $record->model_type;

        if (! class_exists($this->modelClass)) {
            throw new \InvalidArgumentException("Model {$this->modelClass} does not exist");
        }

        if (! in_array(MailAuthenticatable::class, class_implements($this->modelClass) ?: [], true)) {
            throw new \InvalidArgumentException("Model {$this->modelClass} must implement ".MailAuthenticatable::class);
        }

        $rules = $this->modelClass::getValidationRules();

        $this->validator = Validator::make($record->data->toArray(), $rules);

        if ($this->validator->fails()) {
            throw new ValidationException($this->validator);
        }

        $this->withToken = $record->with_token;
    }

    protected function handle(AbstractRecord $record): ResponseFactory
    {
        if (! $record instanceof EmailRegisterUserRecord) {
            throw new \InvalidArgumentException('Invalid record type');
        }

        $user = $this->modelClass::createUser($this->validator);

        $this->userId = $user->getKey();

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
                        'ip' => $this->request->ip(),
                        'user_agent' => $this->request->userAgent(),
                    ]),
                ),
                $user
            );

            $token = $plainToken;
        }

        return ResponseFactory::json(
            new UserRegisteredData(
                message: 'User registered successfully',
                user: DataObject::from($user->nemesisFormat()),
                token: $token,
            ),
            201
        );
    }

    protected function after(bool $success, ?Exception $error = null, AbstractRecord $record = new EmptyRecord): void
    {
        if ($success && $this->userId !== null) {
            $this->logRepository->logRegistrationSuccess(
                userId: $this->userId,
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
