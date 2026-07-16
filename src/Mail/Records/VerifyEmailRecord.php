<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Mail\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;

/**
 * Record for email verification request.
 *
 * Contains the email address, verification token, and model type.
 */
final class VerifyEmailRecord extends AbstractRecord
{
    public function __construct(
        public readonly string $email,
        public readonly string $token,
        public readonly string $model_type,
    ) {}
}
