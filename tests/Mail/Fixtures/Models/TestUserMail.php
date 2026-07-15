<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Tests\Mail\Fixtures\Models;

use AndyDefer\AuthenticationKit\Mail\Contracts\MailAuthenticatable;
use AndyDefer\AuthenticationKit\Mail\Contracts\MailAuthenticationInterface;
use AndyDefer\AuthenticationKit\Tests\Mail\Fixtures\Data\TestUserMailData;
use AndyDefer\AuthenticationKit\Tests\Mail\Fixtures\Services\TestUserMailAuthenticationService;
use AndyDefer\DomainStructures\Abstracts\AbstractData;
use AndyDefer\LaravelNotification\Channels\DatabaseChannel;
use AndyDefer\LaravelNotification\Channels\MailChannel;
use AndyDefer\LaravelNotification\Collections\NotificationRouteCollection;
use AndyDefer\LaravelNotification\Contracts\NotifiableInterface;
use AndyDefer\LaravelNotification\ValueObjects\NotificationRouteVO;
use AndyDefer\PhpVo\ValueObjects\DateTimeVO;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

final class TestUserMail extends Model implements MailAuthenticatable, NotifiableInterface
{
    use SoftDeletes;

    protected $table = 'test_users';

    protected $fillable = [
        'name',
        'email',
        'password',
        'email_verified_at',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'deleted_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    public static function getMailAuthService(): MailAuthenticationInterface
    {
        return app()->make(TestUserMailAuthenticationService::class);
    }

    public function nemesisFormat(): AbstractData
    {
        return new TestUserMailData(
            id: $this->id,
            name: $this->name,
            email: $this->email,
            emailVerifiedAt: $this->email_verified_at?->toIso8601String(),
            createdAt: $this->created_at?->toIso8601String(),
            updatedAt: $this->updated_at?->toIso8601String(),
            deletedAt: $this->deleted_at?->toIso8601String(),
        );
    }

    /**
     * {@inheritDoc}
     */
    public function getNotificationChannels(): NotificationRouteCollection
    {
        $collection = new NotificationRouteCollection;

        if ($this->email) {
            $collection->add(
                new NotificationRouteVO(
                    channelClass: MailChannel::class,
                    destination: $this->email,
                )
            );
        }

        $collection->add(
            new NotificationRouteVO(
                channelClass: DatabaseChannel::class,
                destination: 'database',
            )
        );

        return $collection;
    }

    public function getMorphClass(): string
    {
        return self::class;
    }

    public function getKey(): int
    {
        return $this->id;
    }

    /**
     * {@inheritDoc}
     */
    public function getEmailVerifiedAt(): ?DateTimeVO
    {
        if ($this->email_verified_at === null) {
            return null;
        }

        return new DateTimeVO($this->email_verified_at->toIso8601String());
    }

    /**
     * Check if the model is soft deleted.
     */
    public function isTrashed(): bool
    {
        return $this->trashed();
    }
}
