<?php

declare(strict_types=1);

namespace NyonCode\WireModuleUsers\Tests\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionServiceProvider;

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

    /**
     * An application with teams: its own teams tables, and the permission tables
     * made by Spatie's real migration with `permission.teams` on — so the team
     * column is where the package puts it, not where a test guessed.
     */
    public static function teamsWithRoles(): void
    {
        // Eloquent remembers which columns a model may mass-assign, per class and
        // for the whole process. A test without teams made the roles table with
        // no team column, and would leave `team_id` silently dropped from every
        // Role created after this.
        (new \ReflectionProperty(\Illuminate\Database\Eloquent\Model::class, 'guardableColumns'))->setValue(null, []);

        self::users();

        Schema::create('teams', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('team_user', function (Blueprint $table): void {
            $table->unsignedBigInteger('team_id');
            $table->unsignedBigInteger('user_id');
            $table->primary(['team_id', 'user_id']);
        });

        $provider = (new \ReflectionClass(PermissionServiceProvider::class))->getFileName();

        (include dirname((string) $provider).'/../database/migrations/create_permission_tables.php.stub')->up();
    }
}
