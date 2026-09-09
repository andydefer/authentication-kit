<?php

// src/Mail/Actions/VerifyEmailAction.php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Mail\Actions;

use AndyDefer\Actions\Actions\AbstractAction;
use AndyDefer\Actions\Http\ResponseFactory;
use AndyDefer\AuthenticationKit\Enums\ErrorCode;
use AndyDefer\AuthenticationKit\Enums\ErrorType;
use AndyDefer\AuthenticationKit\Mail\Contracts\MailAuthenticatable;
use AndyDefer\AuthenticationKit\Mail\Contracts\Repositories\LogRepositoryInterface;
use AndyDefer\AuthenticationKit\Mail\Datas\EmailVerifiedData;
use AndyDefer\AuthenticationKit\Mail\Datas\ErrorResponseData;
use AndyDefer\AuthenticationKit\Mail\Records\VerifyEmailRecord;
use AndyDefer\AuthenticationKit\Mail\Services\MailAuthenticationService;
use AndyDefer\AuthenticationKit\Mail\Utils\AuthenticationResolver;
use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\DomainStructures\Utils\EmptyRecord;
use Exception;
use Illuminate\Database\Eloquent\Model;

/**
 * Handles email verification using an OTP.
 *
 * This action validates the OTP, marks the user's email as verified,
 * and logs the verification attempt.
 */
final class VerifyEmailAction extends AbstractAction
{
    private ?string $email = null;

    private string $originalEmail = '';

    private ?string $modelType = null;

    private bool $success = false;

    private bool $alreadyVerified = false;

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
     * @param  AbstractRecord  $record  The verify email request record
     *
     * @throws \InvalidArgumentException When the record type is invalid
     */
    protected function before(AbstractRecord $record): void
    {
        if (! $record instanceof VerifyEmailRecord) {
            throw new \InvalidArgumentException('Invalid record type');
        }

        $this->modelType = $record->model_type;
        $this->email = trim($record->email);
        $this->originalEmail = $record->email;

        $includeTrashed = AuthenticationResolver::usesSoftDeletes($this->modelType);
        $result = AuthenticationResolver::resolveByEmail($this->modelType, $this->email, $includeTrashed);
        $this->authService = $result['service'];
        $this->authenticatable = $result['authenticatable'];
    }

    /**
     * Processes the verify email request.
     *
     * @param  AbstractRecord  $record  The verify email request record
     * @return ResponseFactory The HTTP response
     */
    protected function handle(AbstractRecord $record): ResponseFactory
    {
        if (! $record instanceof VerifyEmailRecord) {
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
            // ✅ Vérifier que le service est disponible
            if ($this->authService === null || $this->modelType === null) {
                $this->success = false;
                $this->errorMessage = ErrorCode::INVALID_MODEL->message();
                $this->errorType = ErrorType::INVALID_MODEL;

                return ResponseFactory::json(
                    new ErrorResponseData(
                        message: ErrorCode::INVALID_MODEL->message(),
                        status: ErrorCode::INVALID_MODEL->getHttpStatusCode(),
                        errorCode: ErrorCode::INVALID_MODEL->value
                    ),
                    ErrorCode::INVALID_MODEL->getHttpStatusCode()
                );
            }

            // ✅ Vérifier que l'utilisateur existe
            if ($this->authenticatable === null) {
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

            // ✅ Utiliser la méthode de l'interface MailAuthenticatable directement
            $emailVerifiedAt = $this->authenticatable->getEmailVerifiedAt();

            if ($emailVerifiedAt !== null) {
                $this->success = true;
                $this->alreadyVerified = true;

                return ResponseFactory::json(
                    new EmailVerifiedData(
                        message: 'Email already verified',
                        email: $this->originalEmail,
                        verifiedAt: $emailVerifiedAt->getValue(),
                        alreadyVerified: true,
                    ),
                    200
                );
            }

            $verified = $this->authService->verifyEmail(
                email: $this->email,
                code: $record->token
            );

            if (! $verified) {
                $this->success = false;
                $this->errorMessage = 'Invalid or expired verification OTP';
                $this->errorType = ErrorType::INVALID_OTP;

                return ResponseFactory::json(
                    new ErrorResponseData(
                        message: ErrorCode::INVALID_VERIFICATION_OTP->message(),
                        status: ErrorCode::INVALID_VERIFICATION_OTP->getHttpStatusCode(),
                        errorCode: ErrorCode::INVALID_VERIFICATION_OTP->value
                    ),
                    ErrorCode::INVALID_VERIFICATION_OTP->getHttpStatusCode()
                );
            }

            $this->success = true;
            $this->alreadyVerified = false;

            $this->authenticatable->refresh();

            // ✅ Utiliser la méthode de l'interface MailAuthenticatable directement
            $verifiedAt = $this->authenticatable->getEmailVerifiedAt();

            return ResponseFactory::json(
                new EmailVerifiedData(
                    message: 'Email verified successfully',
                    email: $this->originalEmail,
                    verifiedAt: $verifiedAt?->getValue() ?? now()->toIso8601String(),
                    alreadyVerified: false,
                ),
                200
            );

        } catch (Exception $e) {
            $this->success = false;
            $this->errorMessage = $e->getMessage();
            $this->errorType = ErrorType::VALIDATION_ERROR;

            return ResponseFactory::json(
                new ErrorResponseData(
                    message: ErrorCode::VERIFY_EMAIL_ERROR->message(),
                    status: ErrorCode::VERIFY_EMAIL_ERROR->getHttpStatusCode(),
                    errorCode: ErrorCode::VERIFY_EMAIL_ERROR->value
                ),
                ErrorCode::VERIFY_EMAIL_ERROR->getHttpStatusCode()
            );
        }
    }

    /**
     * Logs the verify email attempt result.
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
                alreadyVerified: $this->alreadyVerified,
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
}
