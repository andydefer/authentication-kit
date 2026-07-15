<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Mail\Actions;

use AndyDefer\Actions\Actions\AbstractAction;
use AndyDefer\Actions\Http\ResponseFactory;
use AndyDefer\AuthenticationKit\Mail\Contracts\MailAuthenticationInterface;
use AndyDefer\AuthenticationKit\Mail\Contracts\Repositories\LogRepositoryInterface;
use AndyDefer\AuthenticationKit\Mail\Datas\EmailVerificationSentData;
use AndyDefer\AuthenticationKit\Mail\Datas\ErrorResponseData;
use AndyDefer\AuthenticationKit\Mail\Records\SendEmailVerificationRecord;
use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\DomainStructures\Utils\EmptyRecord;
use Exception;
use Illuminate\Database\Eloquent\Model;

final class SendEmailVerificationAction extends AbstractAction
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

    protected function before(AbstractRecord $record): void
    {
        if (! $record instanceof SendEmailVerificationRecord) {
            throw new \InvalidArgumentException('Invalid record type');
        }

        $this->modelType = $record->modelType;

    }

    protected function handle(AbstractRecord $record): ResponseFactory
    {

        if (! $record instanceof SendEmailVerificationRecord) {

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
            $modelClass = $record->modelType;

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
            $authenticatable = $modelClass::find($record->authId);

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

            // ✅ Vérifier si déjà vérifié
            $isVerified = $this->authService->isEmailVerified($authenticatable);

            if ($isVerified) {
                $this->success = true;

                return ResponseFactory::json(
                    new EmailVerificationSentData(
                        message: 'Email already verified',
                        email: $this->email ?? 'unknown',
                        sentAt: now()->toIso8601String(),
                        alreadyVerified: true,
                    ),
                    200
                );
            }

            // ✅ Envoyer l'OTP de vérification d'email

            $sent = $this->authService->sendEmailVerificationOtp($authenticatable);

            if (! $sent) {
                $this->success = false;
                $this->errorMessage = 'Failed to send verification OTP';

                return ResponseFactory::json(
                    new ErrorResponseData(
                        message: 'Failed to send verification OTP',
                        status: 500,
                        errorCode: 'VERIFICATION_OTP_SEND_FAILED'
                    ),
                    500
                );
            }

            $this->success = true;

            return ResponseFactory::json(
                new EmailVerificationSentData(
                    message: 'Verification OTP sent successfully',
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
                    message: 'An error occurred while sending verification OTP',
                    status: 500,
                    errorCode: 'VERIFICATION_EMAIL_ERROR'
                ),
                500
            );
        }
    }

    protected function after(bool $success, ?Exception $error = null, AbstractRecord $record = new EmptyRecord): void
    {

        if ($this->email === null) {

            return;
        }

        if ($this->success) {
            $alreadyVerified = $this->wasAlreadyVerified($record);

            $this->logRepository->logVerificationSuccess(
                email: $this->email,
                modelClass: $this->modelType,
                alreadyVerified: $alreadyVerified,
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

    private function wasAlreadyVerified(AbstractRecord $record): bool
    {

        if (! $record instanceof SendEmailVerificationRecord) {

            return false;
        }

        $modelClass = $record->modelType;

        if (! class_exists($modelClass)) {

            return false;
        }

        $authenticatable = $modelClass::find($record->authId);

        if ($authenticatable === null) {

            return false;
        }

        $isVerified = $this->authService->isEmailVerified($authenticatable);

        return $isVerified;
    }
}
