<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Mail\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;

/**
 * Record for password reset request.
 *
 * Contains the email address, OTP token, new password,
 * and password confirmation.
 */
final class ResetPasswordRecord extends AbstractRecord
{
    public function __construct(
        public readonly string $email,
        public readonly string $token,
        public readonly string $password,
        public readonly string $password_confirmation,
    ) {}
}
