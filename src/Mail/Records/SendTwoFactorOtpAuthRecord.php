<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Mail\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;

/**
 * Record for sending a two-factor authentication OTP.
 *
 * Holds the target model class, the purpose of the sensitive action
 * that requires 2FA, and the destination email to send the code to.
 */
final class SendTwoFactorOtpAuthRecord extends AbstractRecord
{
    public function __construct(
        public readonly string $model_type,
        public readonly string $two_factor_purpose,
    ) {}
}
