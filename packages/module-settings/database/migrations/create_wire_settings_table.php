<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The model reads its table from this key, so the migration must too.
        // It used to write `wire_settings` whatever the key said: an application
        // that renamed the table migrated one name and queried another, every
        // read answered the defaults without a word, and the first write threw.
        $name = (string) config('wire-module-settings.table', 'wire_settings');

        // Guarded, because a name read from config is one the installer cannot
        // see: it recognises a published migration by its file name, and falls
        // back to reading the table name off the source — a literal, which this
        // no longer is. An application that pruned its migrations into a schema
        // dump can therefore be handed this again, and it must be a no-op there
        // rather than a `migrate` that fails on a table it already has.
        if (Schema::hasTable($name)) {
            return;
        }

        Schema::create($name, function (Blueprint $table) {
            $table->id();
            // The group is part of the identity, not a label: two groups may both
            // want a `title`, and a settings page edits one group at a time.
            $table->string('group')->default('general');
            $table->string('key');
            // JSON rather than a string column, so a boolean stays a boolean and
            // an array survives the round trip — a settings table that stringifies
            // everything makes every reader cast by hand and disagree about how.
            $table->json('value')->nullable();
            $table->timestamps();

            $table->unique(['group', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists((string) config('wire-module-settings.table', 'wire_settings'));
    }
};
