<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Mail\Actions;

use AndyDefer\Actions\Actions\AbstractAction;
use AndyDefer\Actions\Http\ResponseFactory;
use AndyDefer\AuthenticationKit\Enums\ErrorCode;
use AndyDefer\AuthenticationKit\Mail\Datas\ErrorResponseData;
use AndyDefer\AuthenticationKit\Mail\Datas\SuccessResponseData;
use AndyDefer\AuthenticationKit\Mail\Records\VerifyTwoFactorOtpAuthRecord;
use AndyDefer\AuthenticationKit\Mail\Services\MailAuthenticationService;
use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\Nemesis\Contracts\MustNemesis;
use AndyDefer\Nemesis\Helpers\NemesisHelper;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * Action to verify a two-factor authentication OTP.
 *
 * The authenticated user submits the code received on their email to
 * confirm a sensitive action. Delegates to
 * MailAuthenticationService::verifyTwoFactorOtp().
 */
final class VerifyTwoFactorOtpAction extends AbstractAction
{
    public function __construct(
        private readonly NemesisHelper $helper,
    ) {}

    protected function handle(AbstractRecord $record): ResponseFactory
    {
        /** @var VerifyTwoFactorOtpAuthRecord $record */
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

            $valid = $service->verifyTwoFactorOtp(
                authenticatable: $authenticatable,
                purpose: $record->two_factor_purpose,
                code: $record->code,
            );

            if (! $valid) {
                return $this->error(ErrorCode::TWO_FACTOR_VERIFY_FAILED);
            }

            return ResponseFactory::json(
                new SuccessResponseData(
                    message: 'Two-factor verification successful',
                    status: 200,
                ),
                200
            );
        } catch (Throwable $e) {
            return $this->error(ErrorCode::TWO_FACTOR_VERIFY_ERROR, $e->getMessage());
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
