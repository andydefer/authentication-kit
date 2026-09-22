<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Mail\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\DomainStructures\Utils\StrictAssociative;

/**
 * Record for resending email verification request.
 *
 * Contains the model type and the authenticatable ID.
 *
 * Every request field not mapped to a first-class property is collected
 * into the `data` bag so the host application can attach contextual
 * information without extending the record.
 */
final class ResendEmailVerificationRecord extends AbstractRecord
{
    public function __construct(
        public readonly string $model_type,
        public readonly string $auth_id,
        public readonly ?StrictAssociative $data = null,
    ) {}
}
