<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wire_media', function (Blueprint $table) {
            $table->id();
            // The disk is stored beside the path because it is part of the
            // address: the same path means different files on `public` and `s3`,
            // and an application that adds a disk later must not lose the old
            // rows' meaning.
            // Logical, not physical: a folder is a row a file points at, and
            // moving a file between folders never touches the disk. Moving the
            // bytes would change the URL of a file a published page already
            // links to, which is a broken image in exchange for a tidier bucket.
            $table->foreignId('folder_id')->nullable()->constrained('wire_media_folders')->nullOnDelete();

            $table->string('disk');
            $table->string('path');
            $table->string('name');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->default(0);

            // What a person writes about the file, as opposed to what the file
            // is. Alt text lives here rather than beside each use, because a
            // description of a photograph is true of the photograph — repeating
            // it per insertion is how half the copies end up empty.
            $table->string('alt')->nullable();
            $table->string('title')->nullable();

            // Read once, on upload. A grid that measured every image would do it
            // per render, and a picker needs them to say what it is offering.
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();

            // Where the scaled copy lives, on the same disk as the original.
            // Null is a real answer and the common one: a PDF has no thumbnail,
            // an SVG needs none, and a server built without GD makes none — all
            // three fall back to the original, scaled by the browser.
            $table->string('thumb_path')->nullable();

            // sha-256 of the bytes. The library refuses a second copy of a file
            // it already holds and hands back the one it has — the same photo
            // uploaded from three screens should be one row with one URL, or
            // every use of it drifts apart.
            $table->string('checksum', 64)->nullable();
            // String, not a foreign key: an uploader may use a non-integer key,
            // and a media row must survive the person who uploaded it.
            $table->string('uploaded_by')->nullable();
            $table->timestamps();

            $table->index('folder_id');
            $table->index('mime_type');
            $table->index('checksum');
            $table->index('uploaded_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wire_media');
    }
};
