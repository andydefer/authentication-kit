<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Mail\Contracts;

use AndyDefer\AuthenticationKit\Contracts\Authenticatable;
use AndyDefer\AuthenticationKit\Mail\Records\MailAuthIdentifierRecord;

interface MailAuthenticatable extends Authenticatable
{
    public function getMailAuthIdentifier(): MailAuthIdentifierRecord;
}
