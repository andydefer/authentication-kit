<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Mail\Datas;

use AndyDefer\DomainStructures\Abstracts\AbstractData;
use AndyDefer\DomainStructures\Utils\DataObject;

/**
 * Response data for successful user registration.
 *
 * Contains the registration success message, the authenticated user data,
 * and an optional authentication token.
 */
final class AuthRegisteredData extends AbstractData
{
    public function __construct(
        public readonly string $message,
        public readonly DataObject $auth,
        public readonly ?string $token = null,
    ) {}
}
