<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wire_settings', function (Blueprint $table) {
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
        Schema::dropIfExists('wire_settings');
    }
};
