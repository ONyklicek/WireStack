<?php

declare(strict_types=1);

namespace NyonCode\WireModuleMedia\Actions;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use League\Flysystem\FilesystemException;
use NyonCode\WireModuleMedia\Models\Media;
use NyonCode\WireModuleMedia\Models\MediaFolder;
use NyonCode\WireModuleMedia\Support\StoredFileFacts;
use NyonCode\WireModuleMedia\Support\Thumbnails;
use NyonCode\WireModuleMedia\Support\UploadLimits;

/**
 * An upload becomes a row — the one place that decides what that means.
 *
 * Three surfaces put files into the library: the manager's drop zone, the picker
 * modal, and the resource's own create page — the last through {@see record()},
 * since its file is already stored by the time it arrives. They were about to grow three
 * versions of the same eight lines, and the version that drifts first is always
 * the one nobody is looking at.
 *
 * What it decides, in order:
 *
 * 1. **The bytes go to the disk the application configured.** A disk that
 *    refuses answers `false` or raises, depending on how it is configured, and
 *    both mean the same thing here: no row.
 * 2. **The same file is not stored twice.** The sha-256 of the stored bytes is
 *    matched against the library, and an upload of a file that is already there
 *    hands back the existing row and deletes the copy it just made. The same
 *    photo uploaded from three screens should be one row with one URL and one
 *    alt text, or every use of it drifts apart.
 * 3. **What is recorded is read from the disk**, never from the upload. What a
 *    browser says a file is, is a claim; what the stored file is, is a fact.
 *    {@see StoredFileFacts} owns that reading, because replacing an original has
 *    to establish the same facts about the same file.
 */
final readonly class StoreUpload
{
    /**
     * Store one upload, or answer null when the disk would not take it.
     *
     * The `$duplicate` flag says which of the two successes happened, because
     * "already in the library" is worth telling a person and is not an error.
     *
     * @throws ValidationException when the file is not one the library takes
     *                             ({@see UploadLimits}) — refused before a byte is written
     */
    public function __invoke(UploadedFile $upload, ?MediaFolder $folder = null, ?bool &$duplicate = null): ?Media
    {
        $duplicate = false;

        UploadLimits::check($upload);

        $disk = (string) config('wire-module-media.disk', 'public');
        $directory = (string) config('wire-module-media.directory', 'media');

        try {
            $path = $upload->store($directory, $disk);
        } catch (FilesystemException) {
            $path = false;
        }

        if (! is_string($path) || $path === '') {
            return null;
        }

        return $this->record($disk, $path, $upload->getClientOriginalName(), $folder, $duplicate);
    }

    /**
     * Turn a file already on the disk into a row.
     *
     * The half of an upload that does not care how the bytes arrived — the
     * resource's create page has its file stored by `FileUpload` and comes in
     * here, so its rows are hashed, measured, de-duplicated and thumbnailed
     * exactly like the drop zone's.
     */
    public function record(string $disk, string $path, string $name, ?MediaFolder $folder = null, ?bool &$duplicate = null): Media
    {
        $duplicate = false;
        $storage = Storage::disk($disk);

        $checksum = StoredFileFacts::checksum($storage->path($path));

        if ($checksum !== null) {
            $existing = Media::query()->where('checksum', $checksum)->first();

            if ($existing !== null) {
                // The copy just written is redundant the moment the row it would
                // have belonged to turns out to exist. Left behind, every repeat
                // upload would add a file nothing points at.
                $storage->delete($path);

                $duplicate = true;

                return $existing;
            }
        }

        [$width, $height] = StoredFileFacts::dimensions($storage->path($path));

        // Asked rather than assumed: the create page hands in whatever path its
        // field dehydrated to, and a disk that lost the file must not throw here.
        $exists = $path !== '' && $storage->exists($path);

        $media = Media::create([
            'folder_id' => $folder?->id,
            'disk' => $disk,
            'path' => $path,
            'name' => $name !== '' ? $name : basename($path),
            'mime_type' => $exists ? ($storage->mimeType($path) ?: null) : null,
            'size' => $exists ? $storage->size($path) : 0,
            'width' => $width,
            'height' => $height,
            'checksum' => $checksum,
            'uploaded_by' => Auth::id() === null ? null : (string) Auth::id(),
        ]);

        // After the row exists. On a queue where one is being worked, because
        // resizing a large photograph is seconds the person who dropped it spends
        // watching a spinner — and inline otherwise, because a queue nobody works
        // would mean thumbnails that never appear.
        Thumbnails::make($media);

        return $media;
    }
}
