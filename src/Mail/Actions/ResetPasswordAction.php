<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Mail\Actions;

use AndyDefer\Actions\Actions\AbstractAction;
use AndyDefer\Actions\Http\ResponseFactory;
use AndyDefer\AuthenticationKit\Enums\ErrorCode;
use AndyDefer\AuthenticationKit\Enums\ErrorType;
use AndyDefer\AuthenticationKit\Mail\Contracts\MailAuthenticationInterface;
use AndyDefer\AuthenticationKit\Mail\Contracts\Repositories\LogRepositoryInterface;
use AndyDefer\AuthenticationKit\Mail\Datas\ErrorResponseData;
use AndyDefer\AuthenticationKit\Mail\Datas\PasswordResetSuccessData;
use AndyDefer\AuthenticationKit\Mail\Records\ResetPasswordRecord;
use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\DomainStructures\Utils\DataObject;
use AndyDefer\DomainStructures\Utils\EmptyRecord;
use Exception;
use Illuminate\Support\Facades\Validator;

/**
 * Handles password reset using an OTP verification code.
 *
 * This action validates the password confirmation, verifies the OTP,
 * and updates the user's password.
 */
final class ResetPasswordAction extends AbstractAction
{
    private ?string $email = null;

    private bool $success = false;

    private ?string $errorMessage = null;

    private ?ErrorType $errorType = null;

    public function __construct(
        private readonly MailAuthenticationInterface $authService,
        private readonly LogRepositoryInterface $logRepository,
    ) {}

    /**
     * Processes the password reset request.
     *
     * @param  AbstractRecord  $record  The reset password request record
     * @return ResponseFactory The HTTP response
     */
    protected function handle(AbstractRecord $record): ResponseFactory
    {
        if (! $record instanceof ResetPasswordRecord) {
            return ResponseFactory::json(
                new ErrorResponseData(
                    message: ErrorCode::INVALID_RECORD_TYPE->message(),
                    status: ErrorCode::INVALID_RECORD_TYPE->getHttpStatusCode(),
                    errorCode: ErrorCode::INVALID_RECORD_TYPE->value
                ),
                ErrorCode::INVALID_RECORD_TYPE->getHttpStatusCode()
            );
        }

        // ✅ Valider le mot de passe avec les règles personnalisables
        $rules = $this->authService::getPasswordValidationRules();
        $validator = Validator::make(
            [
                'password' => $record->password,
                'password_confirmation' => $record->password_confirmation,
            ],
            $rules
        );

        if ($validator->fails()) {
            return ResponseFactory::json(
                new ErrorResponseData(
                    message: 'Password validation failed',
                    status: 422,
                    errorCode: 'PASSWORD_VALIDATION_FAILED',
                    errors: DataObject::from($validator->errors()->toArray())
                ),
                422
            );
        }

        $this->email = $record->email;

        try {
            $reset = $this->authService->resetPassword(
                email: $record->email,
                code: $record->token,
                password: $record->password
            );

            if (! $reset) {
                $this->success = false;
                $this->errorMessage = ErrorCode::INVALID_RESET_OTP->message();
                $this->errorType = ErrorType::INVALID_OTP;

                return ResponseFactory::json(
                    new ErrorResponseData(
                        message: ErrorCode::INVALID_RESET_OTP->message(),
                        status: ErrorCode::INVALID_RESET_OTP->getHttpStatusCode(),
                        errorCode: ErrorCode::INVALID_RESET_OTP->value
                    ),
                    ErrorCode::INVALID_RESET_OTP->getHttpStatusCode()
                );
            }

            $this->success = true;

            return ResponseFactory::json(
                new PasswordResetSuccessData(
                    message: 'Password reset successfully',
                    email: $record->email,
                    resetAt: now()->toIso8601String(),
                ),
                200
            );

        } catch (Exception $e) {
            $this->success = false;
            $this->errorMessage = $e->getMessage();
            $this->errorType = ErrorType::VALIDATION_ERROR;

            return ResponseFactory::json(
                new ErrorResponseData(
                    message: ErrorCode::RESET_PASSWORD_ERROR->message(),
                    status: ErrorCode::RESET_PASSWORD_ERROR->getHttpStatusCode(),
                    errorCode: ErrorCode::RESET_PASSWORD_ERROR->value
                ),
                ErrorCode::RESET_PASSWORD_ERROR->getHttpStatusCode()
            );
        }
    }

    /**
     * Logs the password reset attempt result.
     *
     * @param  bool  $success  Whether the operation succeeded
     * @param  Exception|null  $error  The exception if one occurred
     * @param  AbstractRecord  $record  The original request record
     */
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
}
