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
            // ✅ Tenter d'envoyer l'OTP
            $this->success = $this->authService->sendPasswordResetOtp($record->email);

            // ✅ On retourne toujours 200 pour des raisons de sécurité
            return ResponseFactory::json(
                new PasswordResetLinkSentData(
                    message: 'Password reset OTP sent successfully',
                    email: $record->email,
                    sentAt: now()->toIso8601String(),
                ),
                200
            );

        } catch (Exception $e) {
            // ✅ Erreur système → 500
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

        // ✅ Sécurité : on ne log pas les tentatives sur des emails inexistants
        if (! $this->userFound) {
            return;
        }

        // ✅ Si l'utilisateur existe mais l'envoi a échoué (rate limit, email non envoyé)
        // ✅ Ou si succès → on log
        $this->logRepository->logPasswordResetLinkSent(
            email: $this->email,
            success: $this->success,
            error: $this->success ? null : ($this->errorMessage ?? 'Unknown error'),
            errorClass: $this->success ? null : ($this->errorClass ?? 'UnknownException'),
        );
    }
}
