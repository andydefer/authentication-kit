<?php

// src/Mail/Actions/VerifyEmailAction.php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Mail\Actions;

use AndyDefer\Actions\Actions\AbstractAction;
use AndyDefer\Actions\Http\ResponseFactory;
use AndyDefer\AuthenticationKit\Enums\ErrorCode;
use AndyDefer\AuthenticationKit\Enums\ErrorType;
use AndyDefer\AuthenticationKit\Mail\Contracts\Repositories\LogRepositoryInterface;
use AndyDefer\AuthenticationKit\Mail\Datas\EmailVerifiedData;
use AndyDefer\AuthenticationKit\Mail\Records\VerifyEmailRecord;
use AndyDefer\AuthenticationKit\Mail\Services\MailAuthenticationService;
use AndyDefer\AuthenticationKit\Mail\Utils\AuthenticationResolver;
use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\DomainStructures\Utils\EmptyRecord;
use Exception;
use Illuminate\Database\Eloquent\Model;

/**
 * Handles email verification using an OTP.
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

    protected function handle(AbstractRecord $record): ResponseFactory
    {
        if (! $record instanceof VerifyEmailRecord) {
            return ErrorCode::INVALID_RECORD_TYPE->toJsonResponseFactory();
        }

        try {
            if ($this->authService === null || $this->modelType === null) {
                $this->success = false;
                $this->errorMessage = ErrorCode::INVALID_MODEL->getMessage();
                $this->errorType = ErrorType::INVALID_MODEL;

                return ErrorCode::INVALID_MODEL->toJsonResponseFactory();
            }

            if ($this->authenticatable === null) {
                $this->success = false;
                $this->errorMessage = ErrorCode::AUTHENTICATABLE_NOT_FOUND->getMessage();
                $this->errorType = ErrorType::USER_NOT_FOUND;

                return ErrorCode::AUTHENTICATABLE_NOT_FOUND->toJsonResponseFactory();
            }

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
                    200,
                );
            }

            $verified = $this->authService->verifyEmail(
                email: $this->email,
                code: $record->token,
            );

            if (! $verified) {
                $this->success = false;
                $this->errorMessage = 'Invalid or expired verification OTP';
                $this->errorType = ErrorType::INVALID_OTP;

                return ErrorCode::INVALID_VERIFICATION_OTP->toJsonResponseFactory();
            }

            $this->success = true;
            $this->alreadyVerified = false;

            $this->authenticatable->refresh();

            $verifiedAt = $this->authenticatable->getEmailVerifiedAt();

            return ResponseFactory::json(
                new EmailVerifiedData(
                    message: 'Email verified successfully',
                    email: $this->originalEmail,
                    verifiedAt: $verifiedAt?->getValue() ?? now()->toIso8601String(),
                    alreadyVerified: false,
                ),
                200,
            );

        } catch (Exception $e) {
            $this->success = false;
            $this->errorMessage = $e->getMessage();
            $this->errorType = ErrorType::VALIDATION_ERROR;

            return ErrorCode::VERIFY_EMAIL_ERROR->toJsonResponseFactory();
        }
    }

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
