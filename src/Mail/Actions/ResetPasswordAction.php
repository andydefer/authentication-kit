<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Mail\Actions;

use AndyDefer\Actions\Actions\AbstractAction;
use AndyDefer\Actions\Http\ResponseFactory;
use AndyDefer\AuthenticationKit\Mail\Contracts\MailAuthenticationInterface;
use AndyDefer\AuthenticationKit\Mail\Contracts\Repositories\LogRepositoryInterface;
use AndyDefer\AuthenticationKit\Mail\Datas\ErrorResponseData;
use AndyDefer\AuthenticationKit\Mail\Datas\PasswordResetSuccessData;
use AndyDefer\AuthenticationKit\Mail\Records\ResetPasswordRecord;
use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\DomainStructures\Utils\EmptyRecord;
use Exception;

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

    private ?string $errorClass = null;

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
                    message: 'Invalid record type',
                    status: 500,
                    errorCode: 'INVALID_RECORD_TYPE'
                ),
                500
            );
        }

        if ($record->password !== $record->password_confirmation) {
            return ResponseFactory::json(
                new ErrorResponseData(
                    message: 'Password confirmation does not match',
                    status: 422,
                    errorCode: 'PASSWORD_CONFIRMATION_MISMATCH'
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
                $this->errorMessage = 'Invalid or expired reset OTP';
                $this->errorClass = 'InvalidResetOtpException';

                return ResponseFactory::json(
                    new ErrorResponseData(
                        message: 'Invalid or expired reset OTP',
                        status: 400,
                        errorCode: 'INVALID_RESET_OTP'
                    ),
                    400
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
            $this->errorClass = get_class($e);

            return ResponseFactory::json(
                new ErrorResponseData(
                    message: 'An error occurred while resetting the password',
                    status: 500,
                    errorCode: 'RESET_PASSWORD_ERROR'
                ),
                500
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

        $this->logRepository->logPasswordResetFailure(
            email: $this->email,
            error: $this->errorMessage ?? 'Unknown error',
            errorClass: $this->errorClass ?? 'UnknownException',
        );
    }
}
