<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Mail\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\DomainStructures\Utils\StrictAssociative;

/**
 * Record for password reset request.
 *
 * Contains the email address, OTP token, new password,
 * and password confirmation.
 *
 * Every request field not mapped to a first-class property is collected
 * into the `data` bag so the host application can attach contextual
 * information without extending the record.
 */
final class ResetPasswordRecord extends AbstractRecord
{
    public function __construct(
        public readonly string $email,
        public readonly string $token,
        public readonly string $password,
        public readonly string $password_confirmation,
        public readonly string $model_type,
        public readonly ?StrictAssociative $data = null,
    ) {}
}
