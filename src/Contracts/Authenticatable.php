<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Contracts;

use AndyDefer\AuthenticationKit\Records\AuthIdentifierRecord;
use AndyDefer\DomainStructures\Abstracts\AbstractData;
use AndyDefer\DomainStructures\Abstracts\AbstractRecord;

interface Authenticatable
{
    public static function getAuthIdentifier(): AuthIdentifierRecord;

    public function getFillableRecord(): AbstractRecord;

    public function getOutputData(): AbstractData;
}
