<?php

declare(strict_types=1);

namespace NyonCode\WireModuleMedia\Pages;

use NyonCode\WireForms\Forms\Form;
use NyonCode\WireModuleMedia\Actions\StoreUpload;
use NyonCode\WireModuleMedia\Models\Media;
use NyonCode\WireModuleMedia\Resources\MediaResource;
use NyonCode\WirePanels\Resources\Pages\CreatePage;

/**
 * An upload becomes a row.
 *
 * The file is already on the disk by the time this runs — that is `FileUpload`'s
 * job and this page does not repeat it. Making the row is
 * {@see StoreUpload::record()}'s, the same as for every other upload.
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

            // The same row a drop-zone upload makes — hashed, measured,
            // de-duplicated and thumbnailed. This page used to write its own
            // shorter row, so a file uploaded here never got a thumbnail
            // whatever `thumbnails` said.
            return (new StoreUpload)->record($disk, $path, (string) ($data['name'] ?? ''));
        });
    }
}
