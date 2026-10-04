<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The models read their table names from config, so the migrations must
        // too. Guarded, because a name read from config is one the installer
        // cannot see in the source, and this may be handed to a database that
        // already has the table.
        $folders = (string) config('wire-module-media.folders_table', 'wire_media_folders');

        if (Schema::hasTable($folders)) {
            return;
        }

        Schema::create($folders, function (Blueprint $table) use ($folders) {
            $table->id();

            // Self-referencing, and nullable at the root. `cascadeOnDelete` is
            // deliberately absent: deleting a folder that still holds anything is
            // refused in the model, because a cascade here would take files with
            // it silently — and a file is not the folder's to destroy.
            $table->foreignId('parent_id')->nullable()->constrained($folders)->nullOnDelete();

            $table->string('name');

            // The full path, stored rather than walked. A tree of any depth is
            // otherwise one query per level to render a breadcrumb, and the
            // library draws one on every page. Rewritten for the whole subtree
            // when a folder is renamed or moved, which is the trade this makes.
            $table->string('path')->unique();

            $table->timestamps();

            $table->index('parent_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists((string) config('wire-module-media.folders_table', 'wire_media_folders'));
    }
};
