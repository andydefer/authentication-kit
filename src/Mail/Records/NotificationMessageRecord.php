<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Mail\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;

/**
 * Record for notification message.
 *
 * Contains the email address, subject and body of a notification.
 */
final class NotificationMessageRecord extends AbstractRecord
{
    public function __construct(
        public readonly string $email,
        public readonly string $subject,
        public readonly string $body,
    ) {}
}
