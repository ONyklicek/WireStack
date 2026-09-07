<?php

declare(strict_types=1);

namespace NyonCode\WireModuleMedia\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use NyonCode\WireCore\Foundation\Enums\FileKind;

/**
 * One stored file.
 *
 * The row is the record and the file is the payload, and the two are kept
 * together on purpose: deleting the row deletes the file, because a library that
 * leaves orphans behind fills a disk nobody is looking at.
 */
class Media extends Model
{
    protected $guarded = [];

    public function getTable(): string
    {
        return (string) config('wire-module-media.table', 'wire_media');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'thumb_variants' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::deleted(static function (self $media): void {
            // A crop of this file is a file in its own right — its own URL, its
            // own alt text, quite possibly its own uses — so it survives its
            // original and only loses the pointer back to it.
            //
            // The column says `nullOnDelete` as well, and this is not redundant:
            // SQLite enforces foreign keys only when the pragma is on, and an
            // application running on it would otherwise be left with a
            // derivative pointing at a row that is gone.
            self::query()->where('derived_from_id', $media->getKey())->update(['derived_from_id' => null]);

            // Deliberately after the row is gone rather than before: a delete
            // that removes the file and then fails on the row leaves a record
            // pointing at nothing, which is the worse of the two half-states.
            $disk = Storage::disk($media->disk);

            $disk->delete($media->path);

            // The scaled copy goes too. It is not a file anybody uploaded and
            // nothing else points at it, so leaving it behind is litter that
            // only grows.
            if ($media->thumb_path !== null) {
                $disk->delete($media->thumb_path);
            }

            // And every other size of it. They are not files anybody uploaded
            // and nothing else points at them, so leaving them behind is litter
            // that only grows.
            foreach ($media->thumb_variants ?? [] as $variant) {
                if (is_string($variant) && $variant !== $media->thumb_path) {
                    $disk->delete($variant);
                }
            }
        });
    }

    /**
     * The folder this file is filed under, or none for the library root.
     *
     * @return BelongsTo<MediaFolder, $this>
     */
    /**
     * The file this one was cut from, when it was made in the editor.
     *
     * Nullable and usually null: most files are uploads. What it buys is that a
     * crop can be traced back to its original rather than being related to it
     * only by a name somebody typed — and that the panel can say so.
     */
    public function derivedFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'derived_from_id');
    }

    /** @return HasMany<self, $this> */
    public function derivatives(): HasMany
    {
        return $this->hasMany(self::class, 'derived_from_id');
    }

    public function folder(): BelongsTo
    {
        return $this->belongsTo(MediaFolder::class, 'folder_id');
    }

    /**
     * File it somewhere else.
     *
     * Nothing on the disk moves. A folder is a row this points at, and rewriting
     * the path would change the URL of a file a published page already links to
     * — see {@see MediaFolder} for why that trade goes this way.
     */
    public function moveTo(?MediaFolder $folder): self
    {
        $this->update(['folder_id' => $folder?->id]);

        return $this;
    }

    /**
     * Change the name it is listed under.
     *
     * The *name*, not the path. Renaming the stored file would break every link
     * to it that already exists, and this library's rule is that the bytes under
     * a path never change meaning — a different file is a different row.
     */
    public function rename(string $name): self
    {
        $name = trim($name);

        if ($name !== '') {
            $this->update(['name' => $name]);
        }

        return $this;
    }

    /**
     * Where the file can be read.
     *
     * A disk an application publishes answers its own URL and the browser
     * fetches the file directly, which is the fastest thing that can happen. A
     * disk it does not publish has no such address, and the answer used to stop
     * at null — which rendered as blank tiles and a missing download link, with
     * nothing saying why. Now it falls through to the module's route, which
     * streams the file behind the application's middleware and the Media policy.
     *
     * **"Published" is the disk's own `url` key, not what `Storage::url()`
     * says.** Laravel answers `/storage/{path}` for *any* local disk, whether or
     * not that address resolves: it is a convenience for the `public` disk and a
     * wrong guess for every other one, and taking it at its word is how a
     * private contract ends up linked from a page as though it were public.
     */
    public function url(): ?string
    {
        if ($this->diskIsPublished()) {
            return $this->versioned(Storage::disk($this->disk)->url($this->path));
        }

        return $this->routeUrl('wire-media.show');
    }

    /**
     * The address the **editor** reads from: this module's own route.
     *
     * Not `url()`, and the difference is the whole reason this method exists. A
     * public bucket answers a cross-origin address, and a canvas that has drawn
     * a cross-origin image refuses `toBlob()` — after the person has finished
     * composing their crop. The route is same-origin whatever disk the row
     * names, so the editor works the same on `public` and on S3. See ADR 0035.
     *
     * Null where the route is switched off, and the editor then falls back to
     * `url()` and is offered only where that is same-origin anyway.
     */
    public function streamUrl(): ?string
    {
        return $this->routeUrl('wire-media.show');
    }

    /** The download URL — the streamed route when there is one, else the file. */
    public function downloadUrl(): ?string
    {
        return $this->routeUrl('wire-media.download') ?? $this->url();
    }

    private function diskIsPublished(): bool
    {
        return config("filesystems.disks.{$this->disk}.url") !== null;
    }

    private function routeUrl(string $name, ?string $size = null): ?string
    {
        if (! config('wire-module-media.route.enabled', true) || ! Route::has($name)) {
            return null;
        }

        $parameters = ['media' => $this->getKey(), 'v' => $this->version()];

        // Named, so a private disk gets scaled copies too. Without it the route
        // could only ever stream the original, and a grid of a hundred private
        // files was a hundred full-size photographs — the thing thumbnails
        // exist to prevent, still happening for everybody who needs them most.
        if ($size !== null && $this->thumbPathFor($size) !== null) {
            $parameters['variant'] = $size;
        }

        return route($name, $parameters);
    }

    /**
     * The address, with a version on it.
     *
     * Replacing an original writes new bytes **to the same path** on purpose:
     * moving them would change the URL of a file a published page already links
     * to, and that is the one kind of use this library admits it cannot see. The
     * cost of keeping the path is that the same address now answers with
     * different content, which is precisely what a browser and a CDN get wrong.
     *
     * So the address carries the row's `updated_at`. Nothing moved, and every
     * cache sees a new URL the moment the bytes change. See ADR 0035.
     */
    private function versioned(string $url): string
    {
        return $url.(str_contains($url, '?') ? '&' : '?').'v='.$this->version();
    }

    private function version(): string
    {
        return (string) ($this->updated_at?->timestamp ?? 0);
    }

    /**
     * What a grid should show for this file.
     *
     * The scaled copy when there is one, the original otherwise — which is what
     * makes thumbnails optional everywhere rather than a second code path in
     * every view. A PDF, an SVG, a file uploaded before thumbnails existed and a
     * server built without GD all answer the same way they always did.
     */
    public function previewUrl(?string $size = null): ?string
    {
        $path = $this->thumbPathFor($size);

        if ($path === null) {
            return $this->url();
        }

        // Same rule as the original: a published disk answers directly, and
        // anything else goes through the route — a thumbnail of a private file
        // is just as private as the file, and the route names which size it is
        // being asked for.
        return $this->diskIsPublished()
            ? $this->versioned(Storage::disk($this->disk)->url($path))
            : $this->routeUrl('wire-media.show', $size);
    }

    /**
     * The stored path of one named size, or the best thing there is.
     *
     * Falls back to the tile and then to nothing, so a library that has not
     * remade its thumbnails since the sizes were configured shows the copy it
     * has rather than a broken image.
     */
    public function thumbPathFor(?string $size = null): ?string
    {
        $variants = $this->thumb_variants ?? [];

        if ($size !== null && isset($variants[$size]) && is_string($variants[$size])) {
            return $variants[$size];
        }

        return $this->thumb_path;
    }

    /**
     * A `srcset` in resolution descriptors: this size at 1×, the next one up at 2×.
     *
     * Deliberately `1x`/`2x` rather than width descriptors, which would need a
     * `sizes` attribute — and a `sizes` attribute is a guess about a layout this
     * model cannot see. Each surface asks for the size it actually draws, so the
     * only thing left to answer is a retina screen.
     *
     * Null when there is nothing better to offer, and then the `src` alone is
     * the whole answer.
     */
    public function srcset(string $size): ?string
    {
        $variants = $this->thumb_variants ?? [];
        $sizes = array_keys($variants);
        $index = array_search($size, $sizes, true);

        if ($index === false || ! isset($sizes[$index + 1])) {
            return null;
        }

        $one = $this->previewUrl($size);
        $two = $this->previewUrl($sizes[$index + 1]);

        return $one === null || $two === null || $one === $two ? null : $one.' 1x, '.$two.' 2x';
    }

    /**
     * The text a screen reader is given for this file.
     *
     * Falls back to the name, because an image inserted into a page with no alt
     * at all is worse than one described by its file name — and the file name is
     * at least what the person who uploaded it called it.
     */
    public function altText(): string
    {
        $alt = trim((string) $this->alt);

        return $alt !== '' ? $alt : (string) $this->name;
    }

    /** `1920 × 1080`, or nothing at all for a file that has no pixels. */
    public function dimensions(): ?string
    {
        return $this->width && $this->height ? $this->width.' × '.$this->height : null;
    }

    /**
     * What this file is, as a family: an image, a spreadsheet, an archive.
     *
     * The vocabulary lives in `wire-core` rather than here because two packages
     * ask the question — the library's own screens and the file upload field —
     * and a second copy of it would answer differently within a release. See
     * ADR 0033.
     */
    public function kind(): FileKind
    {
        return FileKind::for($this->mime_type, $this->name);
    }

    /**
     * Whether there are pixels worth previewing.
     *
     * Derived from {@see self::kind()} rather than matched on the mime type a
     * second time: half this model's callers want the family and half want this
     * question, and the two must not be able to disagree — a row whose kind is
     * `Image` and whose `isImage()` is false is a tile that shows a card next to
     * a detail panel showing a photograph.
     */
    public function isImage(): bool
    {
        return $this->kind()->isImage();
    }

    /** A size a person reads, rather than a number of bytes. */
    public function humanSize(): string
    {
        $bytes = max(0, (int) $this->size);
        $units = ['B', 'kB', 'MB', 'GB'];
        $power = $bytes > 0 ? (int) min(floor(log($bytes, 1024)), count($units) - 1) : 0;

        return round($bytes / (1024 ** $power), $power === 0 ? 0 : 1).' '.$units[$power];
    }
}
