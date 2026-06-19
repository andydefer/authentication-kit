<?php

declare(strict_types=1);

namespace AndyDefer\Nemesis\Tests\Fixtures\Models;

use AndyDefer\Nemesis\Contracts\MustNemesis;
use AndyDefer\Nemesis\Tests\Fixtures\Datas\TestUserData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Test model for users that can authenticate with Nemesis tokens.
 *
 * This model represents a typical User model in a Laravel application
 * and demonstrates the correct implementation of the MustNemesis interface.
 * Used for testing token authentication in a realistic context.
 */
final class TestUser extends Model implements MustNemesis
{
    use SoftDeletes;

    /**
     * The table associated with the model.
     */
    protected $table = 'test_users';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'email',
        'email_verified_at',
    ];

    /**
     * The attributes that should be cast to native types.
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * The attributes that should be hidden for serialization.
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Indicates if the model should be timestamped.
     */
    public $timestamps = true;

    /**
     * Get the full name (getter explicite).
     */
    public function getFullName(): string
    {
        return $this->name ?? '';
    }

    /**
     * Check if email is verified.
     */
    public function isEmailVerified(): bool
    {
        return $this->email_verified_at !== null;
    }

    /**
     * Define the format for authenticated API responses.
     * Returns a Record, not an array.
     */
    public function nemesisFormat(): TestUserData
    {
        return TestUserData::from([
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'email_verified_at' => $this->email_verified_at,
        ]);
    }
}
