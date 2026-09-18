<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Mail\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;

/**
 * Record for confirming an email address update.
 *
 * Holds the target model class, the new email address and the OTP
 * code received for confirmation.
 */
final class UpdateEmailAuthRecord extends AbstractRecord
{
    public function __construct(
        public readonly string $model_type,
        public readonly string $new_email,
        public readonly string $code,
    ) {}
}
