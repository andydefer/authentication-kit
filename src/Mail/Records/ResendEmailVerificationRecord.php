<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Mail\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;

/**
 * Record for resending email verification request.
 *
 * Contains the model type and the authenticatable ID.
 */
final class ResendEmailVerificationRecord extends AbstractRecord
{
    public function __construct(
        public readonly string $model_type,
        public readonly int $auth_id,
    ) {}
}
