<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Mail\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;

/**
 * Record for sending an email update OTP.
 *
 * Holds the target model class and the new email address the user
 * wants to migrate to.
 */
final class SendEmailUpdateOtpAuthRecord extends AbstractRecord
{
    public function __construct(
        public readonly string $model_type,
        public readonly string $new_email,
    ) {}
}
