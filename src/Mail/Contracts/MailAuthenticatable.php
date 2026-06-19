<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Mail\Contracts;

use AndyDefer\AuthenticationKit\Contracts\Authenticatable;
use AndyDefer\AuthenticationKit\Mail\Records\MailAuthIdentifierRecord;
use Illuminate\Validation\Validator;

interface MailAuthenticatable extends Authenticatable
{
    public static function getMailAuthIdentifier(): MailAuthIdentifierRecord;

    public static function getValidationRules(): array;

    public static function createUser(Validator $validator): Authenticatable;
}
