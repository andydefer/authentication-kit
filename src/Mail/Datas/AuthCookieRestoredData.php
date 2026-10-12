<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Mail\Datas;

use AndyDefer\DomainStructures\Abstracts\AbstractData;
use AndyDefer\DomainStructures\Utils\DataObject;

/**
 * Response data for a successful auth cookie restoration.
 *
 * Contains the success message and the authenticated user data exposed
 * through `nemesisFormat()`. No new token is issued — the cookie is
 * restored from the plain token supplied by the client.
 */
final class AuthCookieRestoredData extends AbstractData
{
    public function __construct(
        public readonly string $message,
        public readonly DataObject $auth,
    ) {}
}
