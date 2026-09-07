<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wire_media_folders', function (Blueprint $table) {
            $table->id();

            // Self-referencing, and nullable at the root. `cascadeOnDelete` is
            // deliberately absent: deleting a folder that still holds anything is
            // refused in the model, because a cascade here would take files with
            // it silently — and a file is not the folder's to destroy.
            $table->foreignId('parent_id')->nullable()->constrained('wire_media_folders')->nullOnDelete();

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
        Schema::dropIfExists('wire_media_folders');
    }
};
