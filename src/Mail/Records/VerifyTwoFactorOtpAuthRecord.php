<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Mail\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;

/**
 * Record for verifying a two-factor authentication OTP.
 *
 * Holds the target model class, the purpose of the sensitive action,
 * and the OTP code to verify.
 */
final class VerifyTwoFactorOtpAuthRecord extends AbstractRecord
{
    public function __construct(
        public readonly string $model_type,
        public readonly string $two_factor_purpose,
        public readonly string $code,
    ) {}
}
