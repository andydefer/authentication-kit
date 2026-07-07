<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Mail\Datas;

use AndyDefer\DomainStructures\Abstracts\AbstractData;
use AndyDefer\DomainStructures\Utils\DataObject;

final class AuthRegisteredData extends AbstractData
{
    public function __construct(
        public readonly string $message,
        public readonly DataObject $auth,
        public readonly ?string $token = null,
    ) {}
}
