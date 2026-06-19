<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Tests\Mail\Fixtures\Models;

use AndyDefer\AuthenticationKit\Contracts\Authenticatable;
use AndyDefer\AuthenticationKit\Mail\Contracts\MailAuthenticatable;
use AndyDefer\AuthenticationKit\Mail\Records\MailAuthIdentifierRecord;
use AndyDefer\AuthenticationKit\Records\AuthIdentifierRecord;
use AndyDefer\AuthenticationKit\Tests\Mail\Fixtures\Data\TestUserMailData;
use AndyDefer\AuthenticationKit\Tests\Mail\Fixtures\Records\TestUserMailRecord;
use AndyDefer\DomainStructures\Abstracts\AbstractData;
use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Validator;

final class TestUserMail extends Model implements MailAuthenticatable
{
    protected $table = 'test_users';

    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    public static function getAuthIdentifier(): AuthIdentifierRecord
    {
        return new AuthIdentifierRecord(
            password_field: 'password',
            remember_token_field: 'remember_token',
        );
    }

    public static function getMailAuthIdentifier(): MailAuthIdentifierRecord
    {
        return new MailAuthIdentifierRecord(
            email_field: 'email',
            email_verified_at_field: 'email_verified_at',
        );
    }

    public static function getValidationRules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'email' => ['required', 'email', 'unique:test_users'],
            'password' => ['required', 'min:8', 'confirmed'],
        ];
    }

    public static function createUser(Validator $validator): Authenticatable
    {
        $validated = $validator->validated();

        $user = self::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => bcrypt($validated['password']),
        ]);

        return $user;
    }

    public function nemesisFormat(): AbstractRecord
    {
        return new TestUserMailRecord(
            name: $this->name,
            email: $this->email,
            password: $this->password,
        );
    }

    public function getFillableRecord(): AbstractRecord
    {
        return new TestUserMailRecord(
            name: $this->name,
            email: $this->email,
            password: $this->password,
        );
    }

    public function getOutputData(): AbstractData
    {
        return new TestUserMailData(
            id: $this->id,
            name: $this->name,
            email: $this->email,
            createdAt: $this->created_at?->toIso8601String(),
        );
    }
}
