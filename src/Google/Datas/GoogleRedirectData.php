<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Google\Datas;

use AndyDefer\DomainStructures\Abstracts\AbstractData;

final class GoogleRedirectData extends AbstractData
{
    public function __construct(
        public readonly string $url,
    ) {}
}
