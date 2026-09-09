<?php

// src/Mail/Actions/SendPasswordResetLinkAction.php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Mail\Actions;

use AndyDefer\Actions\Actions\AbstractAction;
use AndyDefer\Actions\Http\ResponseFactory;
use AndyDefer\AuthenticationKit\Enums\ErrorCode;
use AndyDefer\AuthenticationKit\Enums\ErrorType;
use AndyDefer\AuthenticationKit\Mail\Contracts\Repositories\LogRepositoryInterface;
use AndyDefer\AuthenticationKit\Mail\Datas\ErrorResponseData;
use AndyDefer\AuthenticationKit\Mail\Datas\PasswordResetLinkSentData;
use AndyDefer\AuthenticationKit\Mail\Records\SendPasswordResetLinkRecord;
use AndyDefer\AuthenticationKit\Mail\Services\MailAuthenticationService;
use AndyDefer\AuthenticationKit\Mail\Utils\AuthenticationResolver;
use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\DomainStructures\Utils\EmptyRecord;
use Exception;

/**
 * Handles sending a password reset link (OTP) to a user.
 *
 * This action sends a password reset OTP to the user's email address.
 * For security reasons, it returns a generic error message regardless of
 * whether the user exists or the OTP failed to send.
 */
final class SendPasswordResetLinkAction extends AbstractAction
{
    private ?string $email = null;

    private bool $userFound = false;

    private bool $success = false;

    private ?string $errorMessage = null;

    private ?ErrorType $errorType = null;

    private ?MailAuthenticationService $authService = null;

    private ?string $modelType = null;

    public function __construct(
        private readonly LogRepositoryInterface $logRepository,
    ) {}

    /**
     * Prepares the action by extracting record data.
     *
     * @param  AbstractRecord  $record  The send password reset link request record
     *
     * @throws \InvalidArgumentException When the record type is invalid
     */
    protected function before(AbstractRecord $record): void
    {
        if (! $record instanceof SendPasswordResetLinkRecord) {
            throw new \InvalidArgumentException('Invalid record type');
        }

        $this->modelType = $record->model_type;
        $this->email = $record->email;

        $this->authService = AuthenticationResolver::resolveService($this->modelType);
    }

    /**
     * Processes the send password reset link request.
     *
     * @param  AbstractRecord  $record  The send password reset link request record
     * @return ResponseFactory The HTTP response
     */
    protected function handle(AbstractRecord $record): ResponseFactory
    {
        if (! $record instanceof SendPasswordResetLinkRecord) {
            return ResponseFactory::json(
                new ErrorResponseData(
                    message: ErrorCode::INVALID_RECORD_TYPE->message(),
                    status: ErrorCode::INVALID_RECORD_TYPE->getHttpStatusCode(),
                    errorCode: ErrorCode::INVALID_RECORD_TYPE->value
                ),
                ErrorCode::INVALID_RECORD_TYPE->getHttpStatusCode()
            );
        }

        // ✅ Vérifier que le service est disponible
        if ($this->authService === null) {
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

        $this->email = $record->email;
        $this->userFound = $this->authService->userExists($record->email);

        // ✅ Vérifier si l'utilisateur existe AVANT d'envoyer l'OTP
        if (! $this->userFound) {
            $this->success = false;
            $this->errorMessage = 'User not found';
            $this->errorType = ErrorType::USER_NOT_FOUND;

            // ✅ On retourne une erreur générique pour ne pas révéler l'existence de l'utilisateur
            return ResponseFactory::json(
                new ErrorResponseData(
                    message: 'We were unable to process your request. Please try again.',
                    status: 400,
                    errorCode: 'RESET_LINK_FAILED'
                ),
                400
            );
        }

        try {
            $this->success = $this->authService->sendPasswordResetOtp($record->email);

            // ✅ Succès : On retourne une 200
            return ResponseFactory::json(
                new PasswordResetLinkSentData(
                    message: 'Password reset OTP sent successfully',
                    email: $record->email,
                    sentAt: now()->toIso8601String(),
                ),
                200
            );

        } catch (Exception $e) {
            $this->success = false;
            $this->errorMessage = $e->getMessage();
            $this->errorType = ErrorType::RATE_LIMIT_EXCEEDED;

            // ✅ Erreur technique : On retourne une erreur générique
            return ResponseFactory::json(
                new ErrorResponseData(
                    message: 'We were unable to send the reset link. Please try again.',
                    status: ErrorCode::RESET_LINK_ERROR->getHttpStatusCode(),
                    errorCode: ErrorCode::RESET_LINK_ERROR->value
                ),
                ErrorCode::RESET_LINK_ERROR->getHttpStatusCode()
            );
        }
    }

    /**
     * Logs the send password reset link attempt result.
     *
     * For security reasons, logs are only created if the user exists.
     *
     * @param  bool  $success  Whether the operation succeeded
     * @param  Exception|null  $error  The exception if one occurred
     * @param  AbstractRecord  $record  The original request record
     */
    protected function after(bool $success, ?Exception $error = null, AbstractRecord $record = new EmptyRecord): void
    {
        if ($this->email === null || ! $this->userFound) {
            return;
        }

        $errorType = $this->errorType ?? null;
        $errorMessage = $this->success ? null : ($this->errorMessage ?? 'Unknown error');

        $this->logRepository->logPasswordResetLinkSent(
            email: $this->email,
            success: $this->success,
            error: $errorMessage,
            errorType: $errorType,
        );
    }
}
