<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Mail\Actions;

use AndyDefer\Actions\Actions\AbstractAction;
use AndyDefer\Actions\Http\ResponseFactory;
use AndyDefer\AuthenticationKit\Mail\Contracts\MailAuthenticationInterface;
use AndyDefer\AuthenticationKit\Mail\Contracts\Repositories\LogRepositoryInterface;
use AndyDefer\AuthenticationKit\Mail\Data\PasswordResetLinkSentData;
use AndyDefer\AuthenticationKit\Mail\Datas\ErrorResponseData;
use AndyDefer\AuthenticationKit\Mail\Records\SendPasswordResetLinkRecord;
use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\DomainStructures\Utils\EmptyRecord;
use Exception;

final class SendPasswordResetLinkAction extends AbstractAction
{
    private ?string $email = null;

    private bool $success = false;

    private ?string $errorMessage = null;

    private ?string $errorClass = null;

    public function __construct(
        private readonly MailAuthenticationInterface $authService,
        private readonly LogRepositoryInterface $logRepository,
    ) {}

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

        try {
            // ✅ Envoyer l'OTP de réinitialisation de mot de passe
            $sent = $this->authService->sendPasswordResetOtp($record->email);

            if (! $sent) {
                $this->success = false;
                $this->errorMessage = 'Failed to send password reset OTP';

                return ResponseFactory::json(
                    new ErrorResponseData(
                        message: 'Failed to send password reset OTP',
                        status: 500,
                        errorCode: 'RESET_OTP_SEND_FAILED'
                    ),
                    500
                );
            }

            $this->success = true;

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

    protected function after(bool $success, ?Exception $error = null, AbstractRecord $record = new EmptyRecord): void
    {
        if ($this->email === null) {
            return;
        }

        if ($this->success) {
            $this->logRepository->logPasswordResetLinkSent(
                email: $this->email,
                success: true,
            );

            return;
        }

        $this->logRepository->logPasswordResetLinkSent(
            email: $this->email,
            success: false,
            error: $this->errorMessage ?? 'Unknown error',
            errorClass: $this->errorClass ?? 'UnknownException',
        );
    }
}
