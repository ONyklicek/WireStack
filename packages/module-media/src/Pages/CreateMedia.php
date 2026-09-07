<?php

declare(strict_types=1);

namespace NyonCode\WireModuleMedia\Pages;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WireModuleMedia\Models\Media;
use NyonCode\WireModuleMedia\Resources\MediaResource;
use NyonCode\WirePanels\Resources\Pages\CreatePage;

/**
 * An upload becomes a row.
 *
 * The file is already on the disk by the time this runs — that is `FileUpload`'s
 * job and this page does not repeat it. What is added is what the disk cannot
 * answer: the original name, the mime type, the size and who uploaded it.
 *
 * **Written through `using()` rather than in `mutateDataBeforeSave()`**, and the
 * difference is the order of the save pipeline: mutation runs at step 2 and the
 * file fields dehydrate at step 2.5, so a mutation reads the *temporary* upload
 * and its path is not the stored one yet. `using()` is the persistence step, so
 * by then the value is the path the file actually has.
 */
class CreateMedia extends CreatePage
{
    protected static ?string $resource = MediaResource::class;

    public function form(Form $form): Form
    {
        return parent::form($form)->using(static function (array $data): Media {
            $disk = (string) config('wire-module-media.disk', 'public');

            // A file field dehydrates to a list even when it takes one file.
            $stored = $data['path'] ?? null;
            $path = is_array($stored) ? (string) (reset($stored) ?: '') : (string) $stored;

            $storage = Storage::disk($disk);
            $exists = $path !== '' && $storage->exists($path);

            return Media::create([
                'disk' => $disk,
                'path' => $path,
                'name' => ($data['name'] ?? '') !== '' ? $data['name'] : basename($path),
                // Read from the disk rather than from the upload: what a browser
                // reported about a file is a claim, not a fact.
                'mime_type' => $exists ? ($storage->mimeType($path) ?: null) : null,
                'size' => $exists ? $storage->size($path) : 0,
                'uploaded_by' => Auth::id() === null ? null : (string) Auth::id(),
            ]);
        });
    }
}
