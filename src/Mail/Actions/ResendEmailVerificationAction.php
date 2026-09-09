<?php

// src/Mail/Actions/ResendEmailVerificationAction.php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Mail\Actions;

use AndyDefer\Actions\Actions\AbstractAction;
use AndyDefer\Actions\Http\ResponseFactory;
use AndyDefer\AuthenticationKit\Enums\ErrorCode;
use AndyDefer\AuthenticationKit\Enums\ErrorType;
use AndyDefer\AuthenticationKit\Mail\Contracts\Repositories\LogRepositoryInterface;
use AndyDefer\AuthenticationKit\Mail\Datas\EmailVerificationResentData;
use AndyDefer\AuthenticationKit\Mail\Datas\ErrorResponseData;
use AndyDefer\AuthenticationKit\Mail\Records\ResendEmailVerificationRecord;
use AndyDefer\AuthenticationKit\Mail\Services\MailAuthenticationService;
use AndyDefer\AuthenticationKit\Mail\Utils\AuthenticationResolver;
use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\DomainStructures\Utils\DataObject;
use AndyDefer\DomainStructures\Utils\EmptyRecord;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

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

    private ?ErrorType $errorType = null;

    private ?MailAuthenticationService $authService = null;

    private ?Model $authenticatable = null;

    public function __construct(
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

        $result = AuthenticationResolver::resolve($this->modelType, $record->auth_id);
        $this->authService = $result['service'];
        $this->authenticatable = $result['authenticatable'];

        if ($this->authenticatable !== null) {
            $this->email = action_normalizer_chain()->normalize($this->authenticatable->email) ?? null;
        }
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
                    message: ErrorCode::INVALID_RECORD_TYPE->message(),
                    status: ErrorCode::INVALID_RECORD_TYPE->getHttpStatusCode(),
                    errorCode: ErrorCode::INVALID_RECORD_TYPE->value
                ),
                ErrorCode::INVALID_RECORD_TYPE->getHttpStatusCode()
            );
        }

        try {
            // ✅ Vérifier si l'utilisateur existe
            if ($this->authenticatable === null || $this->authService === null) {
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

            if ($this->authService->isEmailVerified($this->authenticatable)) {
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

            $sent = $this->authService->resendEmailVerificationOtp($this->authenticatable);

            if (! $sent) {
                $this->success = false;
                $this->errorMessage = 'Failed to resend verification OTP';
                $this->errorType = ErrorType::VERIFICATION_OTP_SEND_FAILED;

                return ResponseFactory::json(
                    new ErrorResponseData(
                        message: ErrorCode::VERIFICATION_OTP_RESEND_FAILED->message(),
                        status: ErrorCode::VERIFICATION_OTP_RESEND_FAILED->getHttpStatusCode(),
                        errorCode: ErrorCode::VERIFICATION_OTP_RESEND_FAILED->value
                    ),
                    ErrorCode::VERIFICATION_OTP_RESEND_FAILED->getHttpStatusCode()
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
            $this->errorType = ErrorType::VERIFICATION_OTP_SEND_FAILED;

            return ResponseFactory::json(
                new ErrorResponseData(
                    message: ErrorCode::VERIFICATION_EMAIL_RESEND_ERROR->message(),
                    status: ErrorCode::VERIFICATION_EMAIL_RESEND_ERROR->getHttpStatusCode(),
                    errorCode: ErrorCode::VERIFICATION_EMAIL_RESEND_ERROR->value
                ),
                ErrorCode::VERIFICATION_EMAIL_RESEND_ERROR->getHttpStatusCode()
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
                alreadyVerified: $this->wasAlreadyVerified(),
            );

            return;
        }

        $errorType = $this->errorType ?? ErrorType::INVALID_OTP;
        $errorMessage = $this->errorMessage ?? ($error !== null ? $error->getMessage() : 'Unknown error');

        $this->logRepository->logVerificationFailure(
            email: $this->email,
            modelClass: $this->modelType,
            error: $errorMessage,
            errorType: $errorType,
        );
    }

    /**
     * Determines if the user was already verified before this request.
     *
     * @return bool True if the user was already verified
     */
    private function wasAlreadyVerified(): bool
    {
        if ($this->authenticatable === null || $this->authService === null) {
            return false;
        }

        return $this->authService->isEmailVerified($this->authenticatable);
    }
}
