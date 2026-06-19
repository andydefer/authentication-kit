<?php

declare(strict_types=1);

namespace AndyDefer\Nemesis\Tests\Fixtures\Models;

use AndyDefer\Nemesis\Contracts\MustNemesis;
use AndyDefer\Nemesis\Tests\Fixtures\Datas\TestApiClientData;
use AndyDefer\Nemesis\Traits\HasNemesisTokens;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Test model for API clients that can authenticate with tokens.
 *
 * This model is used exclusively for testing the Nemesis package.
 * It implements the MustNemesis contract and uses the HasNemesisTokens trait
 * to provide full token management capabilities for API client testing.
 */
final class TestApiClient extends Model implements MustNemesis
{
    use SoftDeletes;

    /**
     * The table associated with the model.
     */
    protected $table = 'test_api_clients';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'api_key',
    ];

    /**
     * The attributes that should be cast to native types.
     */
    protected $casts = [
        'deleted_at' => 'datetime',
    ];

    /**
     * The attributes that should be hidden for serialization.
     */
    protected $hidden = [
        'api_key',
    ];

    /**
     * Indicates if the model should be timestamped.
     */
    public $timestamps = true;

    /**
     * Get the client name (getter explicite).
     */
    public function getName(): string
    {
        return $this->name ?? '';
    }

    /**
     * Get the API key (getter explicite).
     */
    public function getApiKey(): string
    {
        return $this->api_key ?? '';
    }

    /**
     * Define the format for authenticated API responses.
     * Returns a Record, not an array.
     */
    public function nemesisFormat(): TestApiClientData
    {
        return TestApiClientData::from([
            'id' => $this->id,
            'name' => $this->getName(),
            'type' => 'api_client',
        ]);
    }
}
