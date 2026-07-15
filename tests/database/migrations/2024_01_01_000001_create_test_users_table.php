<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration to create test database tables for Nemesis package testing.
 *
 * Creates test_user and test_api_client tables that support the MustNemesis
 * interface for testing multi-model token authentication.
 * Modified to support additional fields for TestCustomFormatUser.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->createTestUsersTable();
        $this->createTestApiClientsTable();
    }

    /**
     * Create the test_users table for testing user authentication.
     */
    private function createTestUsersTable(): void
    {
        Schema::create('test_users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password')->nullable();
            $table->string('remember_token')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamps();
            $table->softDeletes(); // ✅ Ajoute deleted_at
        });
    }

    /**
     * Create the test_api_clients table for testing API client authentication.
     */
    private function createTestApiClientsTable(): void
    {
        Schema::create('test_api_clients', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('api_key')->unique();
            $table->timestamps();
            $table->softDeletes(); // ✅ Ajoute deleted_at
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('test_api_clients');
        Schema::dropIfExists('test_users');
    }
};
