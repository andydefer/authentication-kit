<?php

// src/Mail/Actions/SendEmailVerificationAction.php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Mail\Actions;

use AndyDefer\Actions\Actions\AbstractAction;
use AndyDefer\Actions\Http\ResponseFactory;
use AndyDefer\AuthenticationKit\Enums\ErrorCode;
use AndyDefer\AuthenticationKit\Enums\ErrorType;
use AndyDefer\AuthenticationKit\Mail\Contracts\Repositories\LogRepositoryInterface;
use AndyDefer\AuthenticationKit\Mail\Datas\EmailVerificationSentData;
use AndyDefer\AuthenticationKit\Mail\Records\SendEmailVerificationRecord;
use AndyDefer\AuthenticationKit\Mail\Services\MailAuthenticationService;
use AndyDefer\AuthenticationKit\Mail\Utils\AuthenticationResolver;
use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\DomainStructures\Utils\EmptyRecord;
use Exception;
use Illuminate\Database\Eloquent\Model;

/**
 * Handles sending email verification OTP to a user.
 */
final class SendEmailVerificationAction extends AbstractAction
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

    protected function before(AbstractRecord $record): void
    {
        if (! $record instanceof SendEmailVerificationRecord) {
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

    protected function handle(AbstractRecord $record): ResponseFactory
    {
        if (! $record instanceof SendEmailVerificationRecord) {
            return ResponseFactory::json(
                ErrorCode::INVALID_RECORD_TYPE->toResponseData(),
                ErrorCode::INVALID_RECORD_TYPE->getHttpStatusCode()->value,
            );
        }

        try {
            if ($this->modelType === null || ! class_exists($this->modelType)) {
                $this->success = false;
                $this->errorMessage = ErrorCode::MODEL_NOT_FOUND->getMessage();
                $this->errorType = ErrorType::MODEL_NOT_FOUND;

                return ResponseFactory::json(
                    ErrorCode::MODEL_NOT_FOUND->toResponseData(),
                    ErrorCode::MODEL_NOT_FOUND->getHttpStatusCode()->value,
                );
            }

            if ($this->authenticatable === null || $this->authService === null) {
                $this->success = false;
                $this->errorMessage = ErrorCode::AUTHENTICATABLE_NOT_FOUND->getMessage();
                $this->errorType = ErrorType::USER_NOT_FOUND;

                return ResponseFactory::json(
                    ErrorCode::AUTHENTICATABLE_NOT_FOUND->toResponseData(),
                    ErrorCode::AUTHENTICATABLE_NOT_FOUND->getHttpStatusCode()->value,
                );
            }

            $isVerified = $this->authService->isEmailVerified($this->authenticatable);

            if ($isVerified) {
                $this->success = true;

                return ResponseFactory::json(
                    new EmailVerificationSentData(
                        message: 'Email already verified',
                        email: $this->email ?? 'unknown',
                        sentAt: now()->toIso8601String(),
                        alreadyVerified: true,
                    ),
                    200,
                );
            }

            $sent = $this->authService->sendEmailVerificationOtp($this->authenticatable);

            if (! $sent) {
                $this->success = false;
                $this->errorMessage = 'Failed to send verification OTP';
                $this->errorType = ErrorType::VERIFICATION_OTP_SEND_FAILED;

                return ResponseFactory::json(
                    ErrorCode::VERIFICATION_OTP_RESEND_FAILED->toResponseData(),
                    ErrorCode::VERIFICATION_OTP_RESEND_FAILED->getHttpStatusCode()->value,
                );
            }

            $this->success = true;

            return ResponseFactory::json(
                new EmailVerificationSentData(
                    message: 'Verification OTP sent successfully',
                    email: $this->email ?? 'unknown',
                    sentAt: now()->toIso8601String(),
                ),
                200,
            );

        } catch (Exception $e) {
            $this->success = false;
            $this->errorMessage = $e->getMessage();
            $this->errorType = ErrorType::VERIFICATION_OTP_SEND_FAILED;

            return ResponseFactory::json(
                ErrorCode::VERIFICATION_EMAIL_RESEND_ERROR->toResponseData(),
                ErrorCode::VERIFICATION_EMAIL_RESEND_ERROR->getHttpStatusCode()->value,
            );
        }
    }

    protected function after(bool $success, ?Exception $error = null, AbstractRecord $record = new EmptyRecord): void
    {
        if ($this->email === null) {
            return;
        }

        if ($this->success) {
            $alreadyVerified = $this->wasAlreadyVerified();

            $this->logRepository->logVerificationSuccess(
                email: $this->email,
                modelClass: $this->modelType,
                alreadyVerified: $alreadyVerified,
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

    private function wasAlreadyVerified(): bool
    {
        if ($this->authenticatable === null || $this->authService === null) {
            return false;
        }

        return $this->authService->isEmailVerified($this->authenticatable);
    }
}
