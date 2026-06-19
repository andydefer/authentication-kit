<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Contracts\Repositories;

use AndyDefer\AuthenticationKit\Contracts\Authenticatable;
use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use Illuminate\Validation\Validator;

interface AuthenticationRepositoryInterface
{
    public function create(Validator $validator): Authenticatable;

    public function login(AbstractRecord $credentials): ?Authenticatable;

    public function resetPassword(AbstractRecord $credentials): bool;

    public function updatePassword(Authenticatable $user, string $password): bool;

    public function logout(Authenticatable $user): bool;
}
