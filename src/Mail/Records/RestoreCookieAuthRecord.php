<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Mail\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;

/**
 * Record carrying the intent to restore a missing authentication cookie.
 *
 * The client supplies the plain token it still holds (typically from
 * localStorage or native storage) and the FQCN of the model it belongs to.
 * The plain token is verified against the stored hash before the cookie is
 * re-emitted — no new token is created.
 */
final class RestoreCookieAuthRecord extends AbstractRecord
{
    public function __construct(
        public readonly string $model_type,
        public readonly string $token,
        public readonly bool $remember_me = false,
        public readonly ?string $ip = null,
        public readonly ?string $user_agent = null,
    ) {}
}
