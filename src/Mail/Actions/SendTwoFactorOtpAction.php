<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Mail\Actions;

use AndyDefer\Actions\Actions\AbstractAction;
use AndyDefer\Actions\Http\ResponseFactory;
use AndyDefer\AuthenticationKit\Mail\Datas\ErrorResponseData;
use AndyDefer\AuthenticationKit\Mail\Datas\SuccessResponseData;
use AndyDefer\AuthenticationKit\Mail\Records\SendTwoFactorOtpAuthRecord;
use AndyDefer\AuthenticationKit\Mail\Services\MailAuthenticationService;
use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\Nemesis\Contracts\MustNemesis;
use AndyDefer\Nemesis\Helpers\NemesisHelper;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * Action to send a two-factor authentication OTP.
 *
 * The authenticated user requests a code to confirm a sensitive action
 * (e.g. change email, delete account, change password). The OTP is sent
 * by email via MailAuthenticationService::sendTwoFactorOtp().
 */
final class SendTwoFactorOtpAction extends AbstractAction
{
    public function __construct(
        private readonly NemesisHelper $helper,
    ) {}

    protected function handle(AbstractRecord $record): ResponseFactory
    {
        /** @var SendTwoFactorOtpAuthRecord $record */
        $authenticatable = $this->helper->getCurrentAuthenticatable();

        if ($authenticatable === null) {
            return $this->unauthenticated();
        }

        if ($authenticatable::class !== $record->model_type) {
            return $this->modelTypeMismatch();
        }

        if (! $authenticatable instanceof MustNemesis) {
            return $this->userFormatError();
        }

        try {
            /** @var Model $authenticatable */
            $service = MailAuthenticationService::for($authenticatable::class);

            $sent = $service->sendTwoFactorOtp(
                authenticatable: $authenticatable,
                purpose: $record->two_factor_purpose,
            );

            if (! $sent) {
                return ResponseFactory::json(
                    new ErrorResponseData(
                        message: 'Unable to send two-factor code',
                        status: 429,
                        errorCode: 'TWO_FACTOR_SEND_FAILED',
                    ),
                    429
                );
            }

            return ResponseFactory::json(
                new SuccessResponseData(
                    message: 'Two-factor code sent',
                    status: 200,
                ),
                200
            );
        } catch (Throwable $e) {
            return ResponseFactory::json(
                new ErrorResponseData(
                    message: $e->getMessage(),
                    status: 422,
                    errorCode: 'TWO_FACTOR_SEND_ERROR',
                ),
                422
            );
        }
    }

    private function unauthenticated(): ResponseFactory
    {
        return ResponseFactory::json(
            new ErrorResponseData(
                message: 'Unauthenticated',
                status: 401,
                errorCode: 'UNAUTHENTICATED',
            ),
            401
        );
    }

    private function modelTypeMismatch(): ResponseFactory
    {
        return ResponseFactory::json(
            new ErrorResponseData(
                message: 'Model type mismatch',
                status: 422,
                errorCode: 'MODEL_TYPE_MISMATCH',
            ),
            422
        );
    }

    private function userFormatError(): ResponseFactory
    {
        return ResponseFactory::json(
            new ErrorResponseData(
                message: 'User data format not available',
                status: 422,
                errorCode: 'USER_FORMAT_ERROR',
            ),
            422
        );
    }
}
