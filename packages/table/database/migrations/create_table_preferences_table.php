<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Backs \NyonCode\WireTable\Preferences\Drivers\DatabasePreferenceDriver:
 * one row per (user_id, table_key, view) holding a JSON bag of table preferences
 * (the hidden-column set and the sub-row expansion baseline). `view` is the
 * saved view's name, and an empty string is the unnamed current layout. Publish
 * with
 *   php artisan vendor:publish --tag="wire-table::migrations"
 * only if you set config('wire-table.preferences.default') to 'database'.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('table_preferences', function (Blueprint $table) {
            $table->id();
            // No FK constraint: the app's user key type/table is unknown, and the
            // driver also stores a null user_id for shared/guest rows.
            $table->string('user_id')->nullable()->index();
            $table->string('table_key');
            // The saved view's name. Empty string, never NULL, for the unnamed
            // current layout: MySQL and SQLite treat two NULLs as distinct in a
            // unique index, so a nullable column here would let a second row for
            // the same triple through and the layout would silently fork.
            $table->string('view')->default('');
            $table->json('preferences');
            $table->timestamps();

            $table->unique(['user_id', 'table_key', 'view']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_preferences');
    }
};
