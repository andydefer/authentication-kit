<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Tests\Integration\Google\Fixtures\Models;

use AndyDefer\AuthenticationKit\Google\Contracts\GoogleAuthenticatable;
use AndyDefer\AuthenticationKit\Google\Datas\GoogleIdTokenClaimsData;
use AndyDefer\AuthenticationKit\Mail\Contracts\MailAuthenticatable;
use AndyDefer\AuthenticationKit\Mail\Contracts\MailAuthenticationInterface;
use AndyDefer\AuthenticationKit\Mail\Services\MailAuthenticationService;
use AndyDefer\AuthenticationKit\Tests\Integration\Google\Fixtures\Datas\TestGoogleUserData;
use AndyDefer\DomainStructures\Abstracts\AbstractData;
use AndyDefer\PhpVo\ValueObjects\DateTimeVO;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Hash;

final class TestGoogleUser extends Model implements GoogleAuthenticatable, MailAuthenticatable
{
    use SoftDeletes;

    protected $table = 'test_google_users';

    protected $fillable = [
        'name',
        'email',
        'password',
        'email_verified_at',
        'provider',
    ];

    protected $hidden = ['password'];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public static function getMailAuthService(): MailAuthenticationInterface
    {
        return MailAuthenticationService::for(self::class);
    }

    public function getEmailVerifiedAt(): ?DateTimeVO
    {
        if ($this->email_verified_at === null) {
            return null;
        }

        return new DateTimeVO($this->email_verified_at->toIso8601String());
    }

    public static function generate(array $data): Model&MailAuthenticatable
    {
        return self::create([
            'name' => $data['name'] ?? $data['email'],
            'email' => strtolower($data['email']),
            'password' => isset($data['password']) ? Hash::make($data['password']) : null,
        ]);
    }

    public static function androidGenerate(GoogleIdTokenClaimsData $claims): Model&GoogleAuthenticatable
    {
        return self::create([
            'name' => $claims->name ?? 'Via AndroidGenerate',
            'email' => strtolower($claims->email),
            'email_verified_at' => now(),
            'provider' => 'google-oauth',
        ]);
    }

    public function nemesisFormat(): AbstractData
    {
        return new TestGoogleUserData(
            id: $this->id,
            name: $this->name,
            email: $this->email,
        );
    }
}
