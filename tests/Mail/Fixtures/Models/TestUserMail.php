<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Tests\Mail\Fixtures\Models;

use AndyDefer\AuthenticationKit\Mail\Contracts\MailAuthenticatable;
use AndyDefer\AuthenticationKit\Mail\Contracts\MailAuthenticationServiceInterface;
use AndyDefer\AuthenticationKit\Tests\Mail\Fixtures\Data\TestUserMailData;
use AndyDefer\AuthenticationKit\Tests\Mail\Fixtures\Services\TestUserMailAuthenticationService;
use AndyDefer\DomainStructures\Abstracts\AbstractData;
use Illuminate\Database\Eloquent\Model;

final class TestUserMail extends Model implements MailAuthenticatable
{
    protected $table = 'test_users';

    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    public static function getMailAuthService(): MailAuthenticationServiceInterface
    {
        return new TestUserMailAuthenticationService;
    }

    public function nemesisFormat(): AbstractData
    {
        return new TestUserMailData(
            id: $this->id,
            name: $this->name,
            email: $this->email,
            createdAt: $this->created_at?->toIso8601String(),
        );
    }
}
