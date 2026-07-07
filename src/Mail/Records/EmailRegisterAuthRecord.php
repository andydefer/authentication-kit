<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Mail\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\DomainStructures\Utils\StrictDataObject;

final class EmailRegisterAuthRecord extends AbstractRecord
{
    public function __construct(
        public readonly string $model_type,
        public readonly bool $with_token,
        public readonly StrictDataObject $data,
        public readonly ?string $ip = null,
        public readonly ?string $user_agent = null,
    ) {}
}
