<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Mail\Repositories;

use AndyDefer\AuthenticationKit\Contracts\Authenticatable;
use AndyDefer\AuthenticationKit\Contracts\Repositories\AuthenticationRepositoryInterface;
use AndyDefer\AuthenticationKit\Mail\Contracts\MailAuthenticatable;
use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use Illuminate\Validation\Validator;

final class EmailAuthenticationRepository implements AuthenticationRepositoryInterface
{
    public function __construct(
        private readonly MailAuthenticatable $authenticatable,
    ) {}

    public function create(Validator $validator): Authenticatable
    {
        return $this->authenticatable->createUser($validator);
    }

    public function login(AbstractRecord $credentials): ?Authenticatable
    {
        return $this->authenticatable;
    }

    public function resetPassword(AbstractRecord $credentials): bool
    {
        // Implementation
        return false;
    }

    public function updatePassword(Authenticatable $user, string $password): bool
    {
        // Implementation
        return true;
    }

    public function logout(Authenticatable $user): bool
    {
        // Implementation
        return true;
    }
}
