<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** The workbench's copy of the media module's thumbnail-variant columns. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wire_media', function (Blueprint $table) {
            $table->json('thumb_variants')->nullable()->after('thumb_path');
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
