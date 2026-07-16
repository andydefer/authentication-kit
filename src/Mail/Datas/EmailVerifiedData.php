<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Mail\Datas;

use AndyDefer\DomainStructures\Abstracts\AbstractData;

/**
 * Response data for verified email.
 *
 * Contains the verification success message, the email address,
 * the verification timestamp, and a flag indicating if the
 * email was already verified.
 */
final class EmailVerifiedData extends AbstractData
{
    public function __construct(
        public readonly string $message,
        public readonly string $email,
        public readonly string $verifiedAt,
        public readonly bool $alreadyVerified = false,
    ) {}
}
