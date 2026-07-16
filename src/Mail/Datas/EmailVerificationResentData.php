<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Mail\Datas;

use AndyDefer\DomainStructures\Abstracts\AbstractData;

/**
 * Response data for resent email verification.
 *
 * Contains the resend message, the email address, the timestamp,
 * and a flag indicating if the email was already verified.
 */
final class EmailVerificationResentData extends AbstractData
{
    public function __construct(
        public readonly string $message,
        public readonly string $email,
        public readonly string $sentAt,
        public readonly bool $alreadyVerified = false,
    ) {}
}
