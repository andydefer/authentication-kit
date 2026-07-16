<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Mail\Datas;

use AndyDefer\DomainStructures\Abstracts\AbstractData;

/**
 * Response data for successful password reset.
 *
 * Contains the reset success message, the email address,
 * and the timestamp of the reset.
 */
final class PasswordResetSuccessData extends AbstractData
{
    public function __construct(
        public readonly string $message,
        public readonly string $email,
        public readonly string $resetAt,
    ) {}
}
