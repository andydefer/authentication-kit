<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Mail\Data;

use AndyDefer\DomainStructures\Abstracts\AbstractData;
use AndyDefer\DomainStructures\Utils\DataObject;

final class UserRegisteredData extends AbstractData
{
    public function __construct(
        public readonly string $message,
        public readonly DataObject $user,
        public readonly ?string $token = null,
    ) {}
}
