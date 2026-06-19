<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Contracts;

use AndyDefer\AuthenticationKit\Records\AuthIdentifierRecord;
use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\Nemesis\Contracts\MustNemesis;

interface Authenticatable extends MustNemesis
{
    public static function getAuthIdentifier(): AuthIdentifierRecord;

    public function getFillableRecord(): AbstractRecord;
}
