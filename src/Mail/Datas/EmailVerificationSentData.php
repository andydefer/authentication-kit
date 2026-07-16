<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Mail\Datas;

use AndyDefer\DomainStructures\Abstracts\AbstractData;

/**
 * Response data for sent email verification.
 *
 * Contains the verification message, the email address,
 * the timestamp of the sent request, and a flag indicating
 * if the email was already verified.
 */
final class EmailVerificationSentData extends AbstractData
{
    public function __construct(
        public readonly string $message,
        public readonly string $email,
        public readonly string $sentAt,
        public readonly bool $alreadyVerified = false,
    ) {}
}
