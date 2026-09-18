<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Mail\Actions;

use AndyDefer\Actions\Actions\AbstractAction;
use AndyDefer\Actions\Http\ResponseFactory;
use AndyDefer\AuthenticationKit\Mail\Datas\ErrorResponseData;
use AndyDefer\AuthenticationKit\Mail\Datas\SuccessResponseData;
use AndyDefer\AuthenticationKit\Mail\Records\UpdateEmailAuthRecord;
use AndyDefer\AuthenticationKit\Mail\Services\MailAuthenticationService;
use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\Nemesis\Contracts\MustNemesis;
use AndyDefer\Nemesis\Helpers\NemesisHelper;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * Action to confirm an email address update.
 *
 * The authenticated user submits the OTP received on their new email
 * address. This action validates that the submitted model_type matches
 * the authenticated entity, then delegates to
 * MailAuthenticationService::updateEmail().
 */
final class UpdateEmailAction extends AbstractAction
{
    public function __construct(
        private readonly NemesisHelper $helper,
    ) {}

    protected function handle(AbstractRecord $record): ResponseFactory
    {
        /** @var UpdateEmailAuthRecord $record */
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

            $updated = $service->updateEmail(
                authenticatable: $authenticatable,
                newEmail: $record->new_email,
                code: $record->code,
            );

            if (! $updated) {
                return ResponseFactory::json(
                    new ErrorResponseData(
                        message: 'Invalid or expired code, or email already taken',
                        status: 400,
                        errorCode: 'EMAIL_UPDATE_FAILED',
                    ),
                    400
                );
            }

            return ResponseFactory::json(
                new SuccessResponseData(
                    message: 'Email updated successfully. Please verify your new email.',
                    status: 200,
                ),
                200
            );
        } catch (Throwable $e) {
            return ResponseFactory::json(
                new ErrorResponseData(
                    message: $e->getMessage(),
                    status: 422,
                    errorCode: 'EMAIL_UPDATE_ERROR',
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
