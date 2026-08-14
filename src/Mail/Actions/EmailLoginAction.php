<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Mail\Actions;

use AndyDefer\Actions\Actions\AbstractAction;
use AndyDefer\Actions\Http\ResponseFactory;
use AndyDefer\AuthenticationKit\Contracts\Configs\AuthenticationKitConfigInterface;
use AndyDefer\AuthenticationKit\Contracts\Services\AgentInterface;
use AndyDefer\AuthenticationKit\Enums\ErrorCode;
use AndyDefer\AuthenticationKit\Enums\ErrorType;
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
use Illuminate\Validation\ValidationException;

/**
 * Handles email-based user login authentication.
 *
 * This action validates user credentials, creates an authentication token
 * upon successful login, and logs the authentication attempt.
 */
final class EmailLoginAction extends AbstractAction
{
    private mixed $modelClass;

    private ?string $email = null;

    private ?int $authId = null;

    private bool $success = false;

    private ?string $ip = null;

    private ?string $userAgent = null;

    private ?string $errorMessage = null;

    private ?ErrorType $errorType = null;

    public function __construct(
        private readonly NemesisInterface $nemesis,
        private readonly LogRepositoryInterface $logRepository,
        private readonly AgentInterface $agent,
        private readonly AuthenticationKitConfigInterface $config,

    ) {}

    /**
     * Prepares the action by extracting record data.
     *
     * @param  AbstractRecord  $record  The login request record
     *
     * @throws \InvalidArgumentException When the record type is invalid
     */
    protected function before(AbstractRecord $record): void
    {
        if (! $record instanceof EmailLoginAuthRecord) {
            throw new \InvalidArgumentException('Invalid record type');
        }

        $this->modelClass = $record->model_type;
        $this->ip = $record->ip;
        $this->userAgent = $record->user_agent;
    }

    /**
     * Processes the login request.
     *
     * @param  AbstractRecord  $record  The login request record
     * @return ResponseFactory The HTTP response
     *
     * @throws \InvalidArgumentException When the record type is invalid
     */
    protected function handle(AbstractRecord $record): ResponseFactory
    {
        if (! $record instanceof EmailLoginAuthRecord) {
            return ResponseFactory::json(
                new ErrorResponseData(
                    message: ErrorCode::INVALID_RECORD_TYPE->message(),
                    status: ErrorCode::INVALID_RECORD_TYPE->getHttpStatusCode(),
                    errorCode: ErrorCode::INVALID_RECORD_TYPE->value
                ),
                ErrorCode::INVALID_RECORD_TYPE->getHttpStatusCode()
            );
        }

        try {
            /** @var MailAuthenticatable&Model $modelClass */
            $modelClass = $this->modelClass;

            $email = $record->data->get('email');
            $password = $record->data->get('password');

            if ($email === null || $password === null) {
                $this->success = false;
                $this->email = $email ?? 'unknown';
                $this->errorMessage = ErrorCode::MISSING_CREDENTIALS->message();
                $this->errorType = ErrorType::MISSING_CREDENTIALS;

                $errors = [];
                if ($email === null) {
                    $errors['email'] = ['The email field is required.'];
                }
                if ($password === null) {
                    $errors['password'] = ['The password field is required.'];
                }

                return ResponseFactory::json(
                    new ErrorResponseData(
                        message: ErrorCode::MISSING_CREDENTIALS->message(),
                        status: ErrorCode::MISSING_CREDENTIALS->getHttpStatusCode(),
                        errorCode: ErrorCode::MISSING_CREDENTIALS->value,
                        errors: DataObject::from($errors),
                    ),
                    ErrorCode::MISSING_CREDENTIALS->getHttpStatusCode()
                );
            }

            $this->email = $email;

            $service = $modelClass::getMailAuthService();

            $tokenRecord = $service->login($email, $password);

            if ($tokenRecord === null) {
                $this->success = false;
                $this->errorMessage = ErrorCode::INVALID_CREDENTIALS->message();
                $this->errorType = ErrorType::INVALID_CREDENTIALS;

                return ResponseFactory::json(
                    new ErrorResponseData(
                        message: ErrorCode::INVALID_CREDENTIALS->message(),
                        status: ErrorCode::INVALID_CREDENTIALS->getHttpStatusCode(),
                        errorCode: ErrorCode::INVALID_CREDENTIALS->value
                    ),
                    ErrorCode::INVALID_CREDENTIALS->getHttpStatusCode()
                );
            }

            $auth = $modelClass::where('email', $email)->first();

            if ($auth === null) {
                $this->success = false;
                $this->errorMessage = ErrorCode::AUTHENTICATABLE_NOT_FOUND->message();
                $this->errorType = ErrorType::USER_NOT_FOUND;

                return ResponseFactory::json(
                    new ErrorResponseData(
                        message: ErrorCode::AUTHENTICATABLE_NOT_FOUND->message(),
                        status: ErrorCode::AUTHENTICATABLE_NOT_FOUND->getHttpStatusCode(),
                        errorCode: ErrorCode::AUTHENTICATABLE_NOT_FOUND->value
                    ),
                    ErrorCode::AUTHENTICATABLE_NOT_FOUND->getHttpStatusCode()
                );
            }

            $this->authId = $auth->getKey();
            $this->success = true;

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

        } catch (ValidationException $e) {
            $this->success = false;
            $this->errorMessage = $e->getMessage();
            $this->errorType = ErrorType::VALIDATION_ERROR;

            return ResponseFactory::json(
                new ErrorResponseData(
                    message: ErrorCode::VALIDATION_ERROR->message(),
                    status: ErrorCode::VALIDATION_ERROR->getHttpStatusCode(),
                    errorCode: ErrorCode::VALIDATION_ERROR->value,
                    errors: DataObject::from($e->errors()),
                ),
                ErrorCode::VALIDATION_ERROR->getHttpStatusCode()
            );
        } catch (Exception $e) {
            $this->success = false;
            $this->errorMessage = $e->getMessage();
            $this->errorType = ErrorType::VALIDATION_ERROR;

            return ResponseFactory::json(
                new ErrorResponseData(
                    message: ErrorCode::LOGIN_ERROR->message(),
                    status: ErrorCode::LOGIN_ERROR->getHttpStatusCode(),
                    errorCode: ErrorCode::LOGIN_ERROR->value
                ),
                ErrorCode::LOGIN_ERROR->getHttpStatusCode()
            );
        }
    }

    /**
     * Logs the login attempt result.
     *
     * @param  bool  $success  Whether the operation succeeded
     * @param  Exception|null  $error  The exception if one occurred
     * @param  AbstractRecord  $record  The original request record
     */
    protected function after(bool $success, ?Exception $error = null, AbstractRecord $record = new EmptyRecord): void
    {
        if ($this->success && $this->authId !== null) {
            $this->logRepository->loginSuccess(
                authId: $this->authId,
                modelClass: $this->modelClass,
                email: $this->email ?? 'unknown',
            );

            return;
        }

        if (! $this->success) {
            $errorType = $this->errorType ?? ErrorType::INVALID_CREDENTIALS;
            $errorMessage = $this->errorMessage ?? ($error !== null ? $error->getMessage() : 'Unknown error');

            $this->logRepository->loginFailure(
                modelClass: $this->modelClass ?? 'unknown',
                email: $this->email ?? 'unknown',
                error: $errorMessage,
                errorType: $errorType,
            );
        }
    }
}
