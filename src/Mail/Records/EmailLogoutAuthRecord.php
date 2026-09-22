<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Mail\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\DomainStructures\Utils\StrictAssociative;

/**
 * Record for email logout request.
 *
 * Contains the model type, authentication token, and optional
 * IP address and user agent for device tracking.
 *
 * Every request field not mapped to a first-class property is collected
 * into the `data` bag so the host application can attach contextual
 * information without extending the record.
 */
final class EmailLogoutAuthRecord extends AbstractRecord
{
    public function __construct(
        public readonly string $model_type,
        public readonly string $token,
        public readonly ?string $ip = null,
        public readonly ?string $user_agent = null,
        public readonly ?StrictAssociative $data = null,
    ) {}
}
