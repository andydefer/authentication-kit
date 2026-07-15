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

        // ✅ Conserver l'email original pour la réponse
        $this->originalEmail = $record->email;
        $this->email = trim($record->email);
        $this->modelClass = $record->model_type;

        try {
            // ✅ Normaliser l'email pour la recherche (lowercase + trim)
            $normalizedEmail = strtolower(trim($record->email));

            // ✅ Vérifier si l'utilisateur existe (avec soft deletes si présent)
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

            // ✅ Vérifier si l'utilisateur est soft deleted
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

            // ✅ Vérifier si déjà vérifié en utilisant le getter
            $emailVerifiedAt = $authenticatable->getEmailVerifiedAt();

            if ($emailVerifiedAt !== null) {
                $this->success = true;
                $this->alreadyVerified = true;

                return ResponseFactory::json(
                    new EmailVerifiedData(
                        message: 'Email already verified',
                        email: $this->originalEmail,  // ✅ Conserver l'original
                        verifiedAt: $emailVerifiedAt->getValue(),
                        alreadyVerified: true,
                    ),
                    200
                );
            }

            // ✅ Vérifier avec l'OTP
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

            // ✅ Rafraîchir l'utilisateur pour avoir la date de vérification
            $authenticatable->refresh();

            // ✅ Récupérer la date de vérification via le getter
            $verifiedAt = $authenticatable->getEmailVerifiedAt();

            return ResponseFactory::json(
                new EmailVerifiedData(
                    message: 'Email verified successfully',
                    email: $this->originalEmail,  // ✅ Conserver l'original
                    verifiedAt: $verifiedAt?->getValue() ?? now()->toIso8601String(),
                    alreadyVerified: false,
                ),
                200
            );

        } catch (Exception $e) {
            $this->success = false;
            $this->errorMessage = $e->getMessage();
            $this->errorClass = get_class($e);

            // ✅ Logger l'erreur ici aussi
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

        // ✅ LOGGER TOUTES LES ERREURS SANS CONDITION
        $this->logRepository->logVerificationFailure(
            email: $this->email,
            modelClass: $this->modelClass,
            error: $this->errorMessage ?? 'Unknown error',
            errorClass: $this->errorClass ?? 'UnknownException',
        );
    }
}
