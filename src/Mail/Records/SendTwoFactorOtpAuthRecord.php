<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Mail\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\DomainStructures\Utils\StrictAssociative;

/**
 * Record for sending a two-factor authentication OTP.
 *
 * Holds the target model class, the purpose of the sensitive action
 * that requires 2FA, and the destination email to send the code to.
 *
 * Every request field not mapped to a first-class property is collected
 * into the `data` bag so the host application can attach contextual
 * information without extending the record.
 */
final class SendTwoFactorOtpAuthRecord extends AbstractRecord
{
    public function __construct(
        public readonly string $model_type,
        public readonly string $two_factor_purpose,
        public readonly ?StrictAssociative $data = null,
    ) {}
}
