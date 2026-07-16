<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Mail\Actions;

use AndyDefer\Actions\Actions\AbstractAction;
use AndyDefer\Actions\Http\ResponseFactory;
use AndyDefer\AuthenticationKit\Mail\Contracts\MailAuthenticatable;
use AndyDefer\AuthenticationKit\Mail\Contracts\MailAuthenticationInterface;
use AndyDefer\AuthenticationKit\Mail\Contracts\Repositories\LogRepositoryInterface;
use AndyDefer\AuthenticationKit\Mail\Datas\EmailVerifiedData;
use AndyDefer\AuthenticationKit\Mail\Datas\ErrorResponseData;
use AndyDefer\AuthenticationKit\Mail\Records\VerifyEmailRecord;
use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\DomainStructures\Utils\EmptyRecord;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

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

    private ?string $modelClass = null;

    private bool $success = false;

    private bool $alreadyVerified = false;

    private ?string $errorMessage = null;

    private ?string $errorClass = null;

    public function __construct(
        private readonly MailAuthenticationInterface $authService,
        private readonly LogRepositoryInterface $logRepository,
    ) {}

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
                    message: 'Invalid record type',
                    status: 500,
                    errorCode: 'INVALID_RECORD_TYPE'
                ),
                500
            );
        }

        $this->originalEmail = $record->email;
        $this->email = trim($record->email);
        $this->modelClass = $record->model_type;

        try {
            $normalizedEmail = strtolower(trim($record->email));

            /** @var MailAuthenticatable&Model|null $authenticatable */
            $authenticatable = $this->modelClass::withTrashed()
                ->where('email', $normalizedEmail)
                ->first();

            if ($authenticatable === null) {
                $this->success = false;
                $this->errorMessage = 'User not found';
                $this->errorClass = 'UserNotFoundException';

                return ResponseFactory::json(
                    new ErrorResponseData(
                        message: 'An error occurred while verifying email',
                        status: 500,
                        errorCode: 'VERIFY_EMAIL_ERROR'
                    ),
                    500
                );
            }

            $usesSoftDeletes = in_array(SoftDeletes::class, class_uses($authenticatable), true);

            if ($usesSoftDeletes && $authenticatable->trashed()) {
                $this->success = false;
                $this->errorMessage = 'User not found';
                $this->errorClass = 'UserNotFoundException';

                return ResponseFactory::json(
                    new ErrorResponseData(
                        message: 'An error occurred while verifying email',
                        status: 500,
                        errorCode: 'VERIFY_EMAIL_ERROR'
                    ),
                    500
                );
            }

            $emailVerifiedAt = $authenticatable->getEmailVerifiedAt();

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
                email: $normalizedEmail,
                code: $record->token
            );

            if (! $verified) {
                $this->success = false;
                $this->errorMessage = 'Invalid or expired verification OTP';
                $this->errorClass = 'InvalidVerificationOtpException';

                return ResponseFactory::json(
                    new ErrorResponseData(
                        message: 'Invalid or expired verification OTP',
                        status: 400,
                        errorCode: 'INVALID_VERIFICATION_OTP'
                    ),
                    400
                );
            }

            $this->success = true;
            $this->alreadyVerified = false;

            $authenticatable->refresh();

            $verifiedAt = $authenticatable->getEmailVerifiedAt();

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
            $this->errorClass = get_class($e);

            $this->logRepository->logVerificationFailure(
                email: $this->email,
                modelClass: $this->modelClass,
                error: $this->errorMessage,
                errorClass: $this->errorClass,
            );

            return ResponseFactory::json(
                new ErrorResponseData(
                    message: 'An error occurred while verifying email',
                    status: 500,
                    errorCode: 'VERIFY_EMAIL_ERROR'
                ),
                500
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
                modelClass: $this->modelClass,
                alreadyVerified: $this->alreadyVerified,
            );

            return;
        }

        $this->logRepository->logVerificationFailure(
            email: $this->email,
            modelClass: $this->modelClass,
            error: $this->errorMessage ?? 'Unknown error',
            errorClass: $this->errorClass ?? 'UnknownException',
        );
    }
}
