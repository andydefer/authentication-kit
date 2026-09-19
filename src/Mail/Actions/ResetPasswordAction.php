<?php

// src/Mail/Actions/ResetPasswordAction.php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Mail\Actions;

use AndyDefer\Actions\Actions\AbstractAction;
use AndyDefer\Actions\Http\ResponseFactory;
use AndyDefer\AuthenticationKit\Enums\ErrorCode;
use AndyDefer\AuthenticationKit\Enums\ErrorType;
use AndyDefer\AuthenticationKit\Mail\Contracts\Repositories\LogRepositoryInterface;
use AndyDefer\AuthenticationKit\Mail\Datas\PasswordResetSuccessData;
use AndyDefer\AuthenticationKit\Mail\Records\ResetPasswordRecord;
use AndyDefer\AuthenticationKit\Mail\Services\MailAuthenticationService;
use AndyDefer\AuthenticationKit\Mail\Utils\AuthenticationResolver;
use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\DomainStructures\Utils\EmptyRecord;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;

/**
 * Handles password reset using an OTP verification code.
 */
final class ResetPasswordAction extends AbstractAction
{
    private ?string $email = null;

    private bool $success = false;

    private ?string $errorMessage = null;

    private ?ErrorType $errorType = null;

    private ?MailAuthenticationService $authService = null;

    private ?Model $authenticatable = null;

    private ?string $modelType = null;

    public function __construct(
        private readonly LogRepositoryInterface $logRepository,
    ) {}

    protected function before(AbstractRecord $recordRequest): void
    {
        if (! $recordRequest instanceof ResetPasswordRecord) {
            throw new \InvalidArgumentException('Invalid record type');
        }

        $this->modelType = $recordRequest->model_type;
        $this->email = $recordRequest->email;

        $result = AuthenticationResolver::resolveByEmail($this->modelType, $this->email);
        $this->authService = $result['service'];
        $this->authenticatable = $result['authenticatable'];
    }

    protected function handle(AbstractRecord $record): ResponseFactory
    {
        if (! $record instanceof ResetPasswordRecord) {
            return $this->error(ErrorCode::INVALID_RECORD_TYPE);
        }

        if ($this->authService === null) {
            $this->success = false;
            $this->errorMessage = ErrorCode::INVALID_MODEL->getMessage();
            $this->errorType = ErrorType::INVALID_MODEL;

            return $this->error(ErrorCode::INVALID_MODEL);
        }

        $rules = $this->authService::getPasswordValidationRules();
        $validator = Validator::make(
            [
                'password' => $record->password,
                'password_confirmation' => $record->password_confirmation,
            ],
            $rules,
        );

        if ($validator->fails()) {
            $this->success = false;
            $this->errorMessage = ErrorCode::PASSWORD_VALIDATION_FAILED->getMessage();
            $this->errorType = ErrorType::VALIDATION_ERROR;

            return ResponseFactory::json(
                ErrorCode::PASSWORD_VALIDATION_FAILED->toResponseData(
                    errors: $validator->errors()->toArray(),
                ),
                ErrorCode::PASSWORD_VALIDATION_FAILED->getHttpStatusCode()->value,
            );
        }

        $this->email = $record->email;

        try {
            $reset = $this->authService->resetPassword(
                email: $record->email,
                code: $record->token,
                password: $record->password,
            );

            if (! $reset) {
                $this->success = false;
                $this->errorMessage = ErrorCode::INVALID_RESET_OTP->getMessage();
                $this->errorType = ErrorType::INVALID_OTP;

                return $this->error(ErrorCode::INVALID_RESET_OTP);
            }

            $this->success = true;

            return ResponseFactory::json(
                new PasswordResetSuccessData(
                    message: 'Password reset successfully',
                    email: $record->email,
                    resetAt: now()->toIso8601String(),
                ),
                200,
            );

        } catch (Exception $e) {
            $this->success = false;
            $this->errorMessage = $e->getMessage();
            $this->errorType = ErrorType::VALIDATION_ERROR;

            return $this->error(ErrorCode::RESET_PASSWORD_ERROR);
        }
    }

    protected function after(bool $success, ?Exception $error = null, AbstractRecord $record = new EmptyRecord): void
    {
        if ($this->email === null) {
            return;
        }

        if ($this->success) {
            $this->logRepository->logPasswordResetSuccess(
                email: $this->email,
            );

            return;
        }

        $errorType = $this->errorType ?? ErrorType::INVALID_OTP;
        $errorMessage = $this->errorMessage ?? ($error !== null ? $error->getMessage() : 'Unknown error');

        $this->logRepository->logPasswordResetFailure(
            email: $this->email,
            error: $errorMessage,
            errorType: $errorType,
        );
    }

    /**
     * Builds a standardized error response from an ErrorCode case.
     */
    private function error(ErrorCode $code, ?string $overrideMessage = null): ResponseFactory
    {
        return ResponseFactory::json(
            $code->toResponseData(message: $overrideMessage),
            $code->getHttpStatusCode()->value,
        );
    }
}
