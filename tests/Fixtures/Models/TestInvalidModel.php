<?php

declare(strict_types=1);

namespace AndyDefer\Nemesis\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Test model that does NOT implement MustNemesis interface.
 *
 * This model is specifically designed for testing the middleware's
 * contract validation. It intentionally omits the MustNemesis interface
 * to verify that the middleware correctly rejects models that don't
 * implement the required contract.
 */
final class TestInvalidModel extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'invalid_models';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
    ];

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = true;
}
