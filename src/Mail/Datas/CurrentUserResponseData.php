<?php

// src/Mail/Datas/CurrentUserResponseData.php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Mail\Datas;

use AndyDefer\DomainStructures\Abstracts\AbstractData;
use AndyDefer\DomainStructures\Utils\StrictAssociative;

final class CurrentUserResponseData extends AbstractData
{
    public function __construct(
        public readonly StrictAssociative $user,
        public readonly string $modelType,
    ) {}
}
