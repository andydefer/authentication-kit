<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Mail\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\DomainStructures\Utils\StrictAssociative;

/**
 * Record for confirming an email address update.
 *
 * Holds the target model class, the new email address and the OTP
 * code received for confirmation.
 *
 * Every request field not mapped to a first-class property is collected
 * into the `data` bag so the host application can attach contextual
 * information without extending the record.
 */
final class UpdateEmailRecord extends AbstractRecord
{
    public function __construct(
        public readonly string $model_type,
        public readonly string $new_email,
        public readonly string $code,
        public readonly ?StrictAssociative $data = null,
    ) {}
}
