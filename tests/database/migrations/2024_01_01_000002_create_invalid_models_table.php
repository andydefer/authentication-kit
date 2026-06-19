<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration to create the invalid_models table for testing purposes.
 *
 * This table is used exclusively for testing the Nemesis package's contract
 * validation. Models using this table intentionally do NOT implement the
 * MustNemesis interface to test error handling scenarios.
 */
return new class extends Migration
{
    /**
     * Run the migration.
     */
    public function up(): void
    {
        Schema::create('invalid_models', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        Schema::dropIfExists('invalid_models');
    }
};
