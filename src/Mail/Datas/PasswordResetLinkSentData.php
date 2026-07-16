<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Mail\Datas;

use AndyDefer\DomainStructures\Abstracts\AbstractData;

/**
 * Response data for sent password reset link.
 *
 * Contains the success message, the email address,
 * and the timestamp of the sent request.
 */
final class PasswordResetLinkSentData extends AbstractData
{
    public function __construct(
        public readonly string $message,
        public readonly string $email,
        public readonly string $sentAt,
    ) {}
}
