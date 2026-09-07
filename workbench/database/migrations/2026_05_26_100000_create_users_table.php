<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('role')->default('viewer');
            $table->text('bio')->nullable();
            $table->boolean('is_active')->default(true);

            // What `wire-module-users.avatar` looks for: the module shows the
            // upload where the column exists and initials where it does not,
            // and the workbench is the installation that has one.
            $table->string('avatar_path')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['role', 'bio', 'is_active', 'avatar_path']);
        });
    }
};
