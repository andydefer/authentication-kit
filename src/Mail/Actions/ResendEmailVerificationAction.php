<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Mail\Actions;

use AndyDefer\Actions\Actions\AbstractAction;
use AndyDefer\Actions\Http\ResponseFactory;
use AndyDefer\AuthenticationKit\Mail\Contracts\MailAuthenticationInterface;
use AndyDefer\AuthenticationKit\Mail\Contracts\Repositories\LogRepositoryInterface;
use AndyDefer\AuthenticationKit\Mail\Datas\EmailVerificationResentData;
use AndyDefer\AuthenticationKit\Mail\Datas\ErrorResponseData;
use AndyDefer\AuthenticationKit\Mail\Records\ResendEmailVerificationRecord;
use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\DomainStructures\Utils\EmptyRecord;
use Exception;
use Illuminate\Database\Eloquent\Model;

/**
 * Handles resending email verification OTP to a user.
 *
 * This action checks if the user is already verified, and if not,
 * resends a new verification OTP via email.
 */
final class ResendEmailVerificationAction extends AbstractAction
{
    private ?string $email = null;

    private ?string $modelType = null;

    private bool $success = false;

    private ?string $errorMessage = null;

    private ?string $errorClass = null;

    public function __construct(
        private readonly MailAuthenticationInterface $authService,
        private readonly LogRepositoryInterface $logRepository,
    ) {}

    /**
     * Prepares the action by extracting record data.
     *
     * @param  AbstractRecord  $record  The resend verification request record
     *
     * @throws \InvalidArgumentException When the record type is invalid
     */
    protected function before(AbstractRecord $record): void
    {
        if (! $record instanceof ResendEmailVerificationRecord) {
            throw new \InvalidArgumentException('Invalid record type');
        }

        $this->modelType = $record->model_type;
    }

    /**
     * Processes the resend verification request.
     *
     * @param  AbstractRecord  $record  The resend verification request record
     * @return ResponseFactory The HTTP response
     */
    protected function handle(AbstractRecord $record): ResponseFactory
    {
        if (! $record instanceof ResendEmailVerificationRecord) {
            return ResponseFactory::json(
                new ErrorResponseData(
                    message: 'Invalid record type',
                    status: 500,
                    errorCode: 'INVALID_RECORD_TYPE'
                ),
                500
            );
        }

        try {
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

            /** @var Model $authenticatable */
            $authenticatable = $modelClass::find($record->auth_id);

            if ($authenticatable === null) {
                return ResponseFactory::json(
                    new ErrorResponseData(
                        message: 'Authenticatable not found',
                        status: 404,
                        errorCode: 'AUTHENTICATABLE_NOT_FOUND'
                    ),
                    404
                );
            }

            $this->email = $authenticatable->email ?? null;

            if ($this->authService->isEmailVerified($authenticatable)) {
                $this->success = true;

                return ResponseFactory::json(
                    new EmailVerificationResentData(
                        message: 'Email already verified',
                        email: $this->email ?? 'unknown',
                        sentAt: now()->toIso8601String(),
                        alreadyVerified: true,
                    ),
                    200
                );
            }

            $sent = $this->authService->resendEmailVerificationOtp($authenticatable);

            if (! $sent) {
                $this->success = false;
                $this->errorMessage = 'Failed to resend verification OTP';

                return ResponseFactory::json(
                    new ErrorResponseData(
                        message: 'Failed to resend verification OTP',
                        status: 500,
                        errorCode: 'VERIFICATION_OTP_RESEND_FAILED'
                    ),
                    500
                );
            }

            $this->success = true;

            return ResponseFactory::json(
                new EmailVerificationResentData(
                    message: 'Verification OTP resent successfully',
                    email: $this->email ?? 'unknown',
                    sentAt: now()->toIso8601String(),
                ),
                200
            );

        } catch (Exception $e) {
            $this->success = false;
            $this->errorMessage = $e->getMessage();
            $this->errorClass = get_class($e);

            return ResponseFactory::json(
                new ErrorResponseData(
                    message: 'An error occurred while resending verification OTP',
                    status: 500,
                    errorCode: 'VERIFICATION_EMAIL_RESEND_ERROR'
                ),
                500
            );
        }
    }

    /**
     * Logs the resend verification attempt result.
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
            $this->logRepository->logVerificationSuccess(
                email: $this->email,
                modelClass: $this->modelType,
                alreadyVerified: $this->wasAlreadyVerified($record),
            );

            return;
        }

        $this->logRepository->logVerificationFailure(
            email: $this->email,
            modelClass: $this->modelType,
            error: $this->errorMessage ?? 'Unknown error',
            errorClass: $this->errorClass ?? 'UnknownException',
        );
    }

    /**
     * Determines if the user was already verified before this request.
     *
     * @param  AbstractRecord  $record  The request record
     * @return bool True if the user was already verified
     */
    private function wasAlreadyVerified(AbstractRecord $record): bool
    {
        if (! $record instanceof ResendEmailVerificationRecord) {
            return false;
        }

        $modelClass = $record->model_type;

        if (! class_exists($modelClass)) {
            return false;
        }

        $authenticatable = $modelClass::find($record->auth_id);

        return $authenticatable !== null && $this->authService->isEmailVerified($authenticatable);
    }
}
