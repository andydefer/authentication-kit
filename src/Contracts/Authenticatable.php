<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Contracts;

use AndyDefer\AuthenticationKit\Records\AuthIdentifierRecord;

interface Authenticatable
{
    public function getAuthIdentifier(): AuthIdentifierRecord;
}
