<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Backs \NyonCode\WireTable\Preferences\Drivers\DatabasePreferenceDriver:
 * one row per (user_id, table_key) holding a JSON bag of table preferences
 * (today the hidden-column set). Publish with
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
            $table->json('preferences');
            $table->timestamps();

            $table->unique(['user_id', 'table_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_preferences');
    }
};
