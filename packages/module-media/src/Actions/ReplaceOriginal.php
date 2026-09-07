<?php

declare(strict_types=1);

namespace NyonCode\WireModuleMedia\Actions;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\FilesystemException;
use NyonCode\WireModuleMedia\Models\Media;
use NyonCode\WireModuleMedia\Support\StoredFileFacts;

/**
 * New bytes under an existing row, at the path it already has.
 *
 * The library's oldest refusal was to do this at all, and the danger it named is
 * real: every use of a file changes at once, including the ones nobody can see.
 * What changed is that the owner weighed that against the expectation everyone
 * arriving from a CMS carries — that a crooked photograph can be straightened
 * where it lives — and chose to take it, behind a warning that names the records
 * it will break. That warning is only true because the library now knows where a
 * file is used (ADR 0034); this action is the other half (ADR 0035).
 *
 * **The path does not change, deliberately.** Writing the new bytes somewhere
 * else would change the URL of a file a published page already links to — the
 * one kind of use the library admits it cannot see — so keeping the path is what
 * makes a replacement safe for the uses it cannot warn about. `Media::url()`
 * carries the row's `updated_at` as a version for exactly this reason: same
 * address, new content, and a cache that would otherwise serve the old picture.
 *
 * **Everything the row says about the file is re-read afterwards.** A row whose
 * size, dimensions and checksum still describe the file that used to be there is
 * a row that lies, and it lies to duplicate detection first.
 */
final readonly class ReplaceOriginal
{
    /**
     * Write these bytes over that file, and make the row true again.
     *
     * False when the disk would not take the bytes, and in that case nothing has
     * changed: the old file is still there and the row still describes it. A
     * failed replacement must not be a lost original.
     */
    public function __invoke(Media $media, UploadedFile $upload): bool
    {
        $storage = Storage::disk((string) $media->disk);
        $path = (string) $media->path;

        $source = $upload->getRealPath();

        // False for an upload whose temporary file is already gone, which is
        // what a second submit of the same request looks like. Answering false
        // here leaves the original exactly as it was.
        if (! is_string($source) || ! is_file($source)) {
            return false;
        }

        $stream = @fopen($source, 'rb');

        if ($stream === false) {
            return false;
        }

        // Streamed rather than read into a string: this is a photograph, and
        // the reason the editor exists at all is that they are large.
        try {
            $written = $storage->put($path, $stream);
        } catch (FilesystemException) {
            $written = false;
        } finally {
            fclose($stream);
        }

        if ($written === false) {
            return false;
        }

        [$width, $height] = StoredFileFacts::dimensions($storage->path($path));

        $media->update([
            // The name and the folder are what a person filed this under and are
            // none of this action's business. What changes is the file.
            'mime_type' => $storage->mimeType($path) ?: $media->mime_type,
            'size' => $storage->size($path),
            'width' => $width,
            'height' => $height,
            'checksum' => StoredFileFacts::checksum($storage->path($path)),
        ]);

        // Forced, because there is already a thumbnail and it is of the old
        // picture — which is what the whole library would keep showing.
        app(MakeThumbnail::class)($media, force: true);

        return true;
    }
}
