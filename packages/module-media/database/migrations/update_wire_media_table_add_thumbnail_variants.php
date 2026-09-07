<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Guarded for the same reason as the migration beside it: this may be
        // applied twice against one database.
        if (Schema::hasColumn('wire_media', 'thumb_variants')) {
            return;
        }

        Schema::table('wire_media', function (Blueprint $table) {
            // The other scaled copies, by name. `thumb_path` stays and stays the
            // tile, so a library that upgrades renders exactly as it did before
            // this ran — the variants are a gain, never a requirement.
            $table->json('thumb_variants')->nullable()->after('thumb_path');

            // The picture's average colour, as `#rrggbb`.
            //
            // Taken as a one-pixel downscale while the thumbnails are being
            // made, which is free where the resize is already happening. The
            // grid paints it before any image has arrived, so a page of forty
            // tiles has its final shape and its rough colours immediately
            // instead of reflowing forty times as they land.
            $table->string('placeholder', 7)->nullable()->after('thumb_variants');
        });
    }

    public function down(): void
    {
        Schema::table('wire_media', function (Blueprint $table) {
            $table->dropColumn(['thumb_variants', 'placeholder']);
        });
    }
};
