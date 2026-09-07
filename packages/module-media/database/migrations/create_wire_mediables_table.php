<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wire_mediables', function (Blueprint $table) {
            $table->id();

            // Cascades, unlike everything else in this module: this row is the
            // *link*, not the file. Deleting a media row should take its links
            // with it, because a link to a file that no longer exists is a
            // broken image on a page nobody remembers publishing.
            $table->foreignId('media_id')->constrained('wire_media')->cascadeOnDelete();

            $table->morphs('mediable');

            // Which set of files this is: 'gallery', 'attachments', 'cover'.
            // Named rather than implied by the field, so one record can carry
            // several sets and a form can ask for one of them.
            $table->string('collection')->default('default');

            // The order a person put them in. A gallery whose order is the order
            // rows were inserted is a gallery nobody can arrange.
            $table->unsignedInteger('sort')->default(0);

            $table->timestamps();

            // One file appears at most once in one collection of one record.
            // Attaching twice is a mistake every picker makes eventually, and it
            // renders as the same photo twice with nothing to say which to remove.
            $table->unique(['media_id', 'mediable_type', 'mediable_id', 'collection'], 'wire_mediables_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wire_mediables');
    }
};
