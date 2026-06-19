<?php

declare(strict_types=1);

namespace AndyDefer\Nemesis\Tests\Fixtures\Models;

use AndyDefer\Nemesis\Contracts\MustNemesis;
use AndyDefer\Nemesis\Tests\Fixtures\Datas\TestCustomFormatUserData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Test model for users with custom nemesisFormat implementation.
 *
 * This model demonstrates a custom format for API responses,
 * different from the standard TestUser format.
 */
final class TestCustomFormatUser extends Model implements MustNemesis
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
        'password',
        'remember_token',
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
     * Define custom format for authenticated API responses.
     * This format excludes email and adds custom fields.
     * Returns a Record, not an array.
     */
    public function nemesisFormat(): TestCustomFormatUserData
    {
        return TestCustomFormatUserData::from([
            'user_id' => $this->id,
            'full_name' => $this->getFullName(),
            'is_verified' => $this->isEmailVerified(),
            'custom_field' => 'only_for_api',
            'type' => 'custom_user',
        ]);
    }
}
