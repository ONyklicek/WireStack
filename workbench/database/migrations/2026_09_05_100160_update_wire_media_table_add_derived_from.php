<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The workbench's copy of the media module's `derived_from_id` column.
 *
 * The preview app keeps its own copies of the package migrations it needs, in
 * the order it needs them, rather than loading the packages' own — see the three
 * `wire_media*` migrations beside this one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wire_media', function (Blueprint $table) {
            // nullOnDelete: deleting an original must not delete a crop of it.
            $table->foreignId('derived_from_id')
                ->nullable()
                ->after('folder_id')
                ->constrained('wire_media')
                ->nullOnDelete();

            $table->index('derived_from_id');
        });
    }

    public function down(): void
    {
        Schema::table('wire_media', function (Blueprint $table) {
            $table->dropConstrainedForeignId('derived_from_id');
        });
    }
};
