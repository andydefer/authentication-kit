<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Contracts;

use AndyDefer\Nemesis\Contracts\MustNemesis;

interface Authenticatable extends MustNemesis
{
    public function getKey();
}
