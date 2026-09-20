<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Mail\Actions;

use AndyDefer\Actions\Actions\AbstractAction;
use AndyDefer\Actions\Http\ResponseFactory;
use AndyDefer\AuthenticationKit\Enums\ErrorCode;
use AndyDefer\AuthenticationKit\Mail\Datas\SuccessResponseData;
use AndyDefer\AuthenticationKit\Mail\Records\SendTwoFactorOtpAuthRecord;
use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\Nemesis\Contracts\MustNemesis;
use AndyDefer\Nemesis\Helpers\NemesisHelper;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * Action to send a two-factor authentication OTP.
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
            return ErrorCode::UNAUTHENTICATED->toJsonResponseFactory();
        }

        if ($authenticatable::class !== $record->model_type) {
            return ErrorCode::MODEL_TYPE_MISMATCH->toJsonResponseFactory();
        }

        if (! $authenticatable instanceof MustNemesis) {
            return ErrorCode::USER_FORMAT_ERROR->toJsonResponseFactory();
        }

        try {
            /** @var Model $authenticatable */
            $service = $authenticatable::getMailAuthService();

            $sent = $service->sendTwoFactorOtp(
                authenticatable: $authenticatable,
                purpose: $record->two_factor_purpose,
            );

            if (! $sent) {
                return ErrorCode::TWO_FACTOR_SEND_FAILED->toJsonResponseFactory();
            }

            return ResponseFactory::json(
                new SuccessResponseData(
                    message: 'Two-factor code sent',
                    status: 200,
                ),
                200,
            );
        } catch (Throwable $e) {
            return ErrorCode::TWO_FACTOR_SEND_ERROR->toJsonResponseFactory(
                message: $e->getMessage(),
            );
        }
    }
}
