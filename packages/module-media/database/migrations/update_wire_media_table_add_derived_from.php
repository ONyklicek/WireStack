<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Guarded, because this may be applied twice: the workbench keeps its
        // own copies of these migrations *and* `workbench:build` publishes the
        // package's, so both run against one database. A column addition that
        // cannot be applied twice turns that into a build that cannot finish.
        if (Schema::hasColumn('wire_media', 'derived_from_id')) {
            return;
        }

        Schema::table('wire_media', function (Blueprint $table) {
            // Where a crop came from.
            //
            // `nullOnDelete`, not `cascadeOnDelete`: deleting an original must
            // not delete a crop of it. The derivative is a file in its own right
            // — it has its own URL, its own alt text and quite possibly its own
            // uses — and losing it because somebody tidied up the photograph it
            // was cut from is exactly the silent damage this module exists to
            // avoid. See ADR 0035.
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
