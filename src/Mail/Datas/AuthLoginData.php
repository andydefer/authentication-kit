<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Mail\Datas;

use AndyDefer\DomainStructures\Abstracts\AbstractData;
use AndyDefer\DomainStructures\Utils\DataObject;

/**
 * Response data for successful user login.
 *
 * Contains the login success message, the authenticated user data,
 * and the authentication token.
 */
final class AuthLoginData extends AbstractData
{
    public function __construct(
        public readonly string $message,
        public readonly DataObject $auth,
        public readonly string $token,
    ) {}
}
