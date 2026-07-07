<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Mail\Contracts;

use AndyDefer\AuthenticationKit\Contracts\Authenticatable;

interface MailAuthenticatable extends Authenticatable
{
    public static function getMailAuthService(): MailAuthenticationServiceInterface;
}
