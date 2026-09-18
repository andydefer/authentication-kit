<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Mail\Actions;

use AndyDefer\Actions\Actions\AbstractAction;
use AndyDefer\Actions\Http\ResponseFactory;
use AndyDefer\AuthenticationKit\Mail\Datas\ErrorResponseData;
use AndyDefer\AuthenticationKit\Mail\Datas\SuccessResponseData;
use AndyDefer\AuthenticationKit\Mail\Records\SendEmailUpdateOtpAuthRecord;
use AndyDefer\AuthenticationKit\Mail\Services\MailAuthenticationService;
use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\Nemesis\Contracts\MustNemesis;
use AndyDefer\Nemesis\Helpers\NemesisHelper;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * Action to send an OTP to the user's new email address.
 *
 * The authenticated user requests an email change. This action validates
 * that the submitted model_type matches the authenticated entity, then
 * delegates to MailAuthenticationService::sendEmailUpdateOtp().
 */
final class SendEmailUpdateOtpAction extends AbstractAction
{
    public function __construct(
        private readonly NemesisHelper $helper,
    ) {}

    protected function handle(AbstractRecord $record): ResponseFactory
    {
        /** @var SendEmailUpdateOtpAuthRecord $record */
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

            $sent = $service->sendEmailUpdateOtp($authenticatable, $record->new_email);

            if (! $sent) {
                return ResponseFactory::json(
                    new ErrorResponseData(
                        message: 'Unable to send email update code',
                        status: 429,
                        errorCode: 'EMAIL_UPDATE_SEND_FAILED',
                    ),
                    429
                );
            }

            return ResponseFactory::json(
                new SuccessResponseData(
                    message: 'Email update code sent',
                    status: 200,
                ),
                200
            );
        } catch (Throwable $e) {
            return ResponseFactory::json(
                new ErrorResponseData(
                    message: $e->getMessage(),
                    status: 422,
                    errorCode: 'EMAIL_UPDATE_SEND_ERROR',
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
