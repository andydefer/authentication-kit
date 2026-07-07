<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Mail\Contracts;

use AndyDefer\AuthenticationKit\Contracts\Authenticatable;
use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\Nemesis\Records\NemesisTokenRecord;
use Illuminate\Database\Eloquent\Model;

interface MailAuthenticationServiceInterface
{
    public function register(AbstractRecord $record): Model&Authenticatable;

    public function login(string $email, string $password): ?NemesisTokenRecord;
}
