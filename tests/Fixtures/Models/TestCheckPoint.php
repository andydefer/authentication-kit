<?php

declare(strict_types=1);

namespace AndyDefer\Nemesis\Tests\Fixtures\Models;

use AndyDefer\Nemesis\Contracts\MustNemesis;
use AndyDefer\Nemesis\Tests\Fixtures\Datas\TestCheckPointData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Test model for checkpoints (billeterie) that can authenticate with tokens.
 *
 * This model represents a physical checkpoint (turnstile, gate, etc.)
 * that needs to authenticate with Nemesis tokens for ticket validation.
 */
final class TestCheckPoint extends Model implements MustNemesis
{
    use SoftDeletes;

    /**
     * The table associated with the model.
     */
    protected $table = 'test_checkpoints';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'location',
        'is_active',
        'last_ping_at',
    ];

    /**
     * The attributes that should be cast to native types.
     */
    protected $casts = [
        'is_active' => 'boolean',
        'last_ping_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Indicates if the model should be timestamped.
     */
    public $timestamps = true;

    /**
     * Get the checkpoint name (getter explicite).
     */
    public function getName(): string
    {
        return $this->name ?? '';
    }

    /**
     * Get the checkpoint location.
     */
    public function getLocation(): string
    {
        return $this->location ?? '';
    }

    /**
     * Check if checkpoint is active.
     */
    public function isActive(): bool
    {
        return $this->is_active === true;
    }

    /**
     * Get the status as string.
     */
    public function getStatus(): string
    {
        return $this->isActive() ? 'active' : 'inactive';
    }

    /**
     * Get last ping time.
     */
    public function getLastSeen(): ?string
    {
        return $this->last_ping_at?->toIso8601String();
    }

    /**
     * Define the format for authenticated API responses.
     * Returns a Record, not an array.
     */
    public function nemesisFormat(): TestCheckPointData
    {
        return TestCheckPointData::from([
            'id' => $this->id,
            'name' => $this->getName(),
            'location' => $this->getLocation(),
            'status' => $this->getStatus(),
            'last_seen' => $this->getLastSeen(),
            'type' => 'checkpoint',
        ]);
    }
}
