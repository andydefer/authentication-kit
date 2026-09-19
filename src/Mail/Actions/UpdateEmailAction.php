<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Mail\Actions;

use AndyDefer\Actions\Actions\AbstractAction;
use AndyDefer\Actions\Http\ResponseFactory;
use AndyDefer\AuthenticationKit\Enums\ErrorCode;
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
            return $this->error(ErrorCode::UNAUTHENTICATED);
        }

        if ($authenticatable::class !== $record->model_type) {
            return $this->error(ErrorCode::MODEL_TYPE_MISMATCH);
        }

        if (! $authenticatable instanceof MustNemesis) {
            return $this->error(ErrorCode::USER_FORMAT_ERROR);
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
                return $this->error(ErrorCode::EMAIL_UPDATE_FAILED);
            }

            return ResponseFactory::json(
                new SuccessResponseData(
                    message: 'Email updated successfully. Please verify your new email.',
                    status: 200,
                ),
                200
            );
        } catch (Throwable $e) {
            return $this->error(ErrorCode::EMAIL_UPDATE_ERROR, $e->getMessage());
        }
    }

    /**
     * Builds a standardized error response from an ErrorCode case.
     */
    private function error(ErrorCode $code, ?string $overrideMessage = null): ResponseFactory
    {
        return ResponseFactory::json(
            new ErrorResponseData(
                message: $overrideMessage ?? $code->message(),
                status: $code->getHttpStatusCode(),
                errorCode: $code->value,
            ),
            $code->getHttpStatusCode()
        );
    }
}
