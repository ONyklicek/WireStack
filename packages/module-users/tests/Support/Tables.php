<?php

declare(strict_types=1);

namespace NyonCode\WireModuleUsers\Tests\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The tables an account needs, in the shape the models expect.
 *
 * A class rather than functions in a test file, for the reason `Pest.php` gives:
 * the root `Pest.php` is the one Pest loads, so a helper declared beside one
 * test is undefined the moment another file is run on its own — which is what
 * `--filter` does.
 */
final class Tables
{
    /**
     * The application's own users table, as a fresh Laravel writes it.
     */
    public static function users(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->timestamps();
        });
    }

    /**
     * Spatie's own migration, in the shape its models expect.
     *
     * The permission package extends the behaviour on the user model and leaves
     * these tables where they are, so these are still the rows it reads.
     */
    public static function roles(): void
    {
        Schema::create('roles', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
            $table->unique(['name', 'guard_name']);
        });

        Schema::create('permissions', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
            $table->unique(['name', 'guard_name']);
        });

        Schema::create('model_has_roles', function (Blueprint $table): void {
            $table->unsignedBigInteger('role_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->primary(['role_id', 'model_id', 'model_type']);
        });

        Schema::create('model_has_permissions', function (Blueprint $table): void {
            $table->unsignedBigInteger('permission_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->primary(['permission_id', 'model_id', 'model_type']);
        });

        Schema::create('role_has_permissions', function (Blueprint $table): void {
            $table->unsignedBigInteger('permission_id');
            $table->unsignedBigInteger('role_id');
            $table->primary(['permission_id', 'role_id']);
        });
    }
}
