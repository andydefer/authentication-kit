<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Mail\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;

/**
 * Record for email logout request.
 *
 * Contains the model type, authentication token, and optional
 * IP address and user agent for device tracking.
 */
final class EmailLogoutAuthRecord extends AbstractRecord
{
    public function __construct(
        public readonly string $model_type,
        public readonly string $token,
        public readonly ?string $ip = null,
        public readonly ?string $user_agent = null,
    ) {}
}
