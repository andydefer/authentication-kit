<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Contracts;

use AndyDefer\LaravelNotification\Contracts\NotifiableInterface;
use AndyDefer\Nemesis\Contracts\MustNemesis;

interface Authenticatable extends MustNemesis, NotifiableInterface
{
    public function getKey();
}
