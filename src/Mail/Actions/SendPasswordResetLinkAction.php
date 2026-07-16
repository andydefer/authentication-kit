<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Mail\Actions;

use AndyDefer\Actions\Actions\AbstractAction;
use AndyDefer\Actions\Http\ResponseFactory;
use AndyDefer\AuthenticationKit\Mail\Contracts\MailAuthenticationInterface;
use AndyDefer\AuthenticationKit\Mail\Contracts\Repositories\LogRepositoryInterface;
use AndyDefer\AuthenticationKit\Mail\Datas\ErrorResponseData;
use AndyDefer\AuthenticationKit\Mail\Datas\PasswordResetLinkSentData;
use AndyDefer\AuthenticationKit\Mail\Records\SendPasswordResetLinkRecord;
use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\DomainStructures\Utils\EmptyRecord;
use Exception;

/**
 * Handles sending a password reset link (OTP) to a user.
 *
 * This action sends a password reset OTP to the user's email address.
 * For security reasons, it always returns a 200 response regardless of
 * whether the user exists or the OTP was sent successfully.
 */
final class SendPasswordResetLinkAction extends AbstractAction
{
    private ?string $email = null;

    private bool $userFound = false;

    private bool $success = false;

    private ?string $errorMessage = null;

    private ?string $errorClass = null;

    public function __construct(
        private readonly MailAuthenticationInterface $authService,
        private readonly LogRepositoryInterface $logRepository,
    ) {}

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
                    message: 'Invalid record type',
                    status: 500,
                    errorCode: 'INVALID_RECORD_TYPE'
                ),
                500
            );
        }

        $this->email = $record->email;
        $this->userFound = $this->authService->userExists($record->email);

        try {
            $this->success = $this->authService->sendPasswordResetOtp($record->email);

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
            $this->errorClass = get_class($e);

            return ResponseFactory::json(
                new ErrorResponseData(
                    message: 'An error occurred while sending the reset OTP',
                    status: 500,
                    errorCode: 'RESET_LINK_ERROR'
                ),
                500
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

        $this->logRepository->logPasswordResetLinkSent(
            email: $this->email,
            success: $this->success,
            error: $this->success ? null : ($this->errorMessage ?? 'Unknown error'),
            errorClass: $this->success ? null : ($this->errorClass ?? 'UnknownException'),
        );
    }
}
