<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Mail\Entities;

use AndyDefer\AuthenticationKit\Mail\Contracts\MailAuthenticatable;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;

final class EmailAuthenticatableEntity
{
    public function __construct(
        private readonly MailAuthenticatable $authenticatable,
    ) {}

    public function getEmailField(): string
    {
        return $this->authenticatable->getMailAuthIdentifier()->email_field;
    }

    public function getVerifiedAtField(): string
    {
        return $this->authenticatable->getMailAuthIdentifier()->email_verified_at_field;
    }

    public function getPasswordField(): string
    {
        return $this->authenticatable->getAuthIdentifier()->password_field;
    }

    public function getRememberTokenField(): string
    {
        return $this->authenticatable->getAuthIdentifier()->remember_token_field;
    }

    public function getFillableFields(): StringTypedCollection
    {
        return $this->authenticatable->getFillableFields();
    }
}
