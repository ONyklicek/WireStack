<?php

declare(strict_types=1);

namespace NyonCode\WireForms\Components;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use NyonCode\WireCore\Foundation\Contracts\DehydratesState;
use NyonCode\WireCore\Foundation\Support\StoredFileUrlResolver;
use NyonCode\WireForms\Contracts\ProvidesImplicitValidationRules;
use NyonCode\WireForms\Contracts\ProvidesItemValidationRules;

/**
 * File upload field with image mode, multiple files, disk/directory configuration.
 */
class FileUpload extends Field implements DehydratesState, ProvidesImplicitValidationRules, ProvidesItemValidationRules
{
    /** @var array<int, string>|Closure */
    protected array|Closure $acceptedFileTypes = [];

    protected ?int $maxSize = null;

    protected ?int $minSize = null;

    protected ?int $maxFiles = null;

    protected ?int $minFiles = null;

    protected bool $multiple = false;

    protected bool $image = false;

    protected bool $avatar = false;

    protected ?string $disk = null;

    protected ?string $directory = null;

    protected string $visibility = 'public';

    protected bool $preserveFilenames = false;

    protected bool $deletable = true;

    protected int $urlExpiryMinutes = 5;

    /** @var (Closure(string): (string|null))|null */
    protected ?Closure $previewUrlUsing = null;

    protected bool $deletesFromDisk = false;

    /** @var (Closure(string): void)|null */
    protected ?Closure $deleteUsing = null;

    /** @var (Closure(UploadedFile): (string|null))|null */
    protected ?Closure $fileNameUsing = null;

    /** @var (Closure(UploadedFile, string): (string|null))|null */
    protected ?Closure $storeFileUsing = null;

    protected ?int $imageResizeTargetWidth = null;

    protected ?int $imageResizeTargetHeight = null;

    protected ?string $imageCropAspectRatio = null;

    protected bool $interactiveCrop = false;

    /**
     * @param  array<int, string>|Closure  $types
     */
    public function acceptedFileTypes(array|Closure $types): static
    {
        $this->acceptedFileTypes = $types;

        return $this;
    }

    public function maxSize(?int $kilobytes): static
    {
        $this->maxSize = $kilobytes;

        return $this;
    }

    public function minSize(?int $kilobytes): static
    {
        $this->minSize = $kilobytes;

        return $this;
    }

    public function maxFiles(?int $count): static
    {
        $this->maxFiles = $count;

        return $this;
    }

    public function minFiles(?int $count): static
    {
        $this->minFiles = $count;

        return $this;
    }

    public function multiple(bool $condition = true): static
    {
        $this->multiple = $condition;

        return $this;
    }

    public function image(bool $condition = true): static
    {
        $this->image = $condition;

        if ($condition && empty($this->acceptedFileTypes)) {
            $this->acceptedFileTypes = ['image/*'];
        }

        return $this;
    }

    public function avatar(bool $condition = true): static
    {
        $this->avatar = $condition;

        if ($condition) {
            $this->image();

            // An avatar is square by definition; an explicit ratio still wins.
            $this->imageCropAspectRatio ??= '1:1';
        }

        return $this;
    }

    public function disk(?string $disk): static
    {
        $this->disk = $disk;

        return $this;
    }

    public function directory(?string $directory): static
    {
        $this->directory = $directory;

        return $this;
    }

    public function visibility(string $visibility): static
    {
        $this->visibility = $visibility;

        return $this;
    }

    public function preserveFilenames(bool $condition = true): static
    {
        $this->preserveFilenames = $condition;

        return $this;
    }

    /**
     * Name the stored file yourself instead of the hashed default or the
     * original client name ({@see preserveFilenames()}).
     *
     * The callback receives the {@see UploadedFile} and returns the bare filename
     * to store under (within {@see directory()} on {@see disk()}) — return an
     * empty string or non-string to fall back to the default naming. For full
     * control over the whole path, use {@see storeFileUsing()} instead.
     *
     * @param  Closure(UploadedFile): (string|null)  $callback
     */
    public function fileNameUsing(Closure $callback): static
    {
        $this->fileNameUsing = $callback;

        return $this;
    }

    /**
     * Own the entire store step — build the full path, choose the folder, write
     * to the disk however you like.
     *
     * Takes precedence over {@see directory()} / {@see preserveFilenames()} /
     * {@see fileNameUsing()}: when set, the field runs only your callback and
     * keeps whatever stored path (relative to the disk) it returns. The callback
     * receives the {@see UploadedFile} and the resolved disk name.
     *
     * @param  Closure(UploadedFile, string): (string|null)  $callback
     */
    public function storeFileUsing(Closure $callback): static
    {
        $this->storeFileUsing = $callback;

        return $this;
    }

    /**
     * Whether already-stored files can be removed from the field (shows a
     * delete control next to each existing file). Defaults to true.
     */
    public function deletable(bool $condition = true): static
    {
        $this->deletable = $condition;

        return $this;
    }

    /**
     * Lifetime of the signed temporary URL generated for a stored file on a
     * non-public disk (see {@see visibility()}). Ignored for public disks, which
     * render a plain, non-expiring URL.
     */
    public function signedUrlExpiration(int $minutes): static
    {
        $this->urlExpiryMinutes = $minutes;

        return $this;
    }

    /**
     * Supply the browser URL for an already-stored file yourself.
     *
     * The escape hatch for a private disk whose driver cannot produce a URL — a
     * `local` disk with no temporary-url route throws on both `url()` and
     * `temporaryUrl()` — or simply to serve previews through your own signed
     * route. The callback receives the stored `$path` and returns a URL or a
     * `data:` URI (or null for "no preview, show the filename only"). When set it
     * takes precedence over the built-in disk resolution.
     *
     * @param  Closure(string): (string|null)  $callback
     */
    public function previewUrlUsing(Closure $callback): static
    {
        $this->previewUrlUsing = $callback;

        return $this;
    }

    /**
     * Also delete the physical file from disk when a stored file is removed from
     * the field.
     *
     * Off by default: removing a stored file from the field only drops the
     * reference, leaving the file on disk (cleanup stays the application's
     * concern). Opt in when the field owns the file's lifecycle.
     */
    public function deletesFromDisk(bool $condition = true): static
    {
        $this->deletesFromDisk = $condition;

        return $this;
    }

    /**
     * Run custom teardown when a stored file is removed from the field — delete
     * the file plus a derived thumbnail, detach a record, anything.
     *
     * Providing a callback implies {@see deletesFromDisk()}: it means "yes, tear
     * the stored file down", and your callback owns exactly how. The callback
     * receives the stored `$path` and fully replaces the built-in disk delete.
     *
     * @param  Closure(string): void  $callback
     */
    public function deleteUsing(Closure $callback): static
    {
        $this->deleteUsing = $callback;

        return $this;
    }

    public function imageResizeTargetWidth(?int $width): static
    {
        $this->imageResizeTargetWidth = $width;

        return $this;
    }

    public function imageResizeTargetHeight(?int $height): static
    {
        $this->imageResizeTargetHeight = $height;

        return $this;
    }

    /**
     * Crop the picked image to this ratio before uploading: "16:9", "4/3", "1.5".
     *
     * The crop is taken from the centre and happens in the browser, so an
     * oversized original never travels. Non-raster images (SVG) pass through.
     */
    public function imageCropAspectRatio(?string $ratio): static
    {
        $this->imageCropAspectRatio = $ratio;

        return $this;
    }

    // ─── Getters ───────────────────────────────────────────────────

    /**
     * @return array<int, string>
     */
    public function getAcceptedFileTypes(): array
    {
        return $this->evaluate($this->acceptedFileTypes);
    }

    public function getMaxSize(): ?int
    {
        return $this->maxSize;
    }

    public function getMinSize(): ?int
    {
        return $this->minSize;
    }

    public function getMaxFiles(): ?int
    {
        return $this->maxFiles;
    }

    public function getMinFiles(): ?int
    {
        return $this->minFiles;
    }

    public function isMultiple(): bool
    {
        return $this->multiple;
    }

    public function isImage(): bool
    {
        return $this->image;
    }

    /**
     * Let the user place the crop frame instead of taking the centre.
     *
     * Only meaningful with {@see imageCropAspectRatio()} (or {@see avatar()}):
     * without a ratio there is nothing to place. A batch selection and any
     * non-raster image still go straight through — there is no sensible frame to
     * drag over five files at once, or over an SVG.
     */
    public function cropInteractively(bool $condition = true): static
    {
        $this->interactiveCrop = $condition;

        return $this;
    }

    public function cropsInteractively(): bool
    {
        return $this->interactiveCrop && $this->imageCropAspectRatio !== null;
    }

    /**
     * Does this field rewrite the image before upload?
     *
     * Drives the two-input markup: without processing the field keeps its plain
     * wire:model input, so an ordinary upload path stays exactly as it was.
     */
    public function processesImages(): bool
    {
        return $this->imageCropAspectRatio !== null
            || $this->imageResizeTargetWidth !== null
            || $this->imageResizeTargetHeight !== null;
    }

    /**
     * The processing config handed to the browser.
     *
     * @return array{aspectRatio: string|null, targetWidth: int|null, targetHeight: int|null, interactive: bool}
     */
    public function getImageProcessingConfig(): array
    {
        return [
            'aspectRatio' => $this->imageCropAspectRatio,
            'targetWidth' => $this->imageResizeTargetWidth,
            'targetHeight' => $this->imageResizeTargetHeight,
            'interactive' => $this->cropsInteractively(),
        ];
    }

    public function isAvatar(): bool
    {
        return $this->avatar;
    }

    public function getDisk(): string
    {
        return $this->disk ?? config('wire-forms.file_upload.disk', 'public');
    }

    public function getDirectory(): string
    {
        return $this->directory ?? config('wire-forms.file_upload.directory', 'uploads');
    }

    public function getVisibility(): string
    {
        return $this->visibility;
    }

    public function shouldPreserveFilenames(): bool
    {
        return $this->preserveFilenames;
    }

    public function getImageResizeTargetWidth(): ?int
    {
        return $this->imageResizeTargetWidth;
    }

    public function getImageResizeTargetHeight(): ?int
    {
        return $this->imageResizeTargetHeight;
    }

    public function getImageCropAspectRatio(): ?string
    {
        return $this->imageCropAspectRatio;
    }

    public function isDeletable(): bool
    {
        return $this->deletable;
    }

    /**
     * Turn the bound state into displayable descriptors for **already-stored**
     * files. Freshly-uploaded `TemporaryUploadedFile` instances (and empty
     * values) are skipped — those are previewed client-side by the Alpine view;
     * only persisted string paths / URLs become entries here.
     *
     * Accepts a single path or an array of paths (the field's state type is
     * `array`, but a single-file field may hold a bare string).
     *
     * @return list<array{path: string, url: string|null, name: string, isImage: bool}>
     */
    public function getStoredFiles(mixed $state): array
    {
        $values = is_array($state) ? $state : [$state];

        $files = [];

        foreach ($values as $value) {
            // Only genuine stored references (relative path / URL); a leaked
            // absolute temp path is never rendered.
            if (! is_string($value) || ! $this->isStoredReference($value)) {
                continue;
            }

            $files[] = [
                'path' => $value,
                'url' => $this->resolveFileUrl($value),
                'name' => basename($value),
                'isImage' => $this->isImage(),
            ];
        }

        return $files;
    }

    /**
     * Unified list of every file currently in the field state — already-stored
     * paths **and** pending {@see TemporaryUploadedFile} uploads (not yet moved
     * to permanent storage under the store-on-submit model). Each entry carries
     * its `index` in the state array so the remove control can target it.
     *
     * @return list<array{index: int|string, name: string, url: ?string, isImage: bool, stored: bool}>
     */
    public function getFilePreviews(mixed $state): array
    {
        $values = match (true) {
            is_array($state) => $state,
            $state === null || $state === '' => [],
            default => [$state],
        };

        $previews = [];

        foreach ($values as $index => $value) {
            if (is_string($value) && $this->isStoredReference($value)) {
                $previews[] = [
                    'index' => $index,
                    'name' => basename($value),
                    'url' => $this->resolveFileUrl($value),
                    'isImage' => $this->isImage(),
                    'stored' => true,
                ];
            } elseif ($value instanceof TemporaryUploadedFile) {
                $previewable = $this->isImage() && $value->isPreviewable();

                $previews[] = [
                    'index' => $index,
                    'name' => $value->getClientOriginalName(),
                    'url' => $previewable ? $value->temporaryUrl() : null,
                    'isImage' => $previewable,
                    'stored' => false,
                ];
            }
        }

        return $previews;
    }

    /**
     * Move a freshly-uploaded file to permanent storage on the configured disk
     * and return its stored path (relative to the disk root), honouring the
     * field's directory, visibility, and preserve-filenames settings.
     */
    /**
     * Store-on-submit: freshly-uploaded files become paths, existing string
     * paths pass through. A multiple field yields a list of paths, a single one
     * yields one path (or null).
     *
     * Ran as a hardcoded `instanceof FileUpload` step inside SaveHandler until
     * ADR 0021 gave the save path a seam; the logic is unchanged, it just lives
     * with the field that owns it now.
     */
    /**
     * Per-file size limits for a multiple upload.
     *
     * On a single upload these live on the field's own key (the value *is* the
     * file). On a multiple one that key holds an array, where `max:` would mean
     * a count — so the size rules belong to each item instead.
     *
     * @return array<int, mixed>
     */
    public function itemValidationRules(): array
    {
        if (! $this->multiple) {
            return [];
        }

        return array_values(array_filter([
            $this->maxSize !== null ? 'max:'.$this->maxSize : null,
            $this->minSize !== null ? 'min:'.$this->minSize : null,
        ]));
    }

    /**
     * Turn the field's own limits into real validation.
     *
     * Until now maxSize()/minSize()/maxFiles()/minFiles() reached nothing but the
     * hint text under the dropzone: the field advertised "up to 5 MB" and then
     * accepted anything, because no rule was ever derived from the config. A
     * client-side limit is not a limit.
     *
     * The same words mean different things to Laravel depending on the state:
     * on a single upload `max:` is kilobytes of file, on a multiple upload the
     * state is an array and `max:` is a count of items.
     *
     * Per-file size on a multiple upload is not here but in
     * {@see itemValidationRules()}, which the resolver mounts at `field.*`.
     *
     * @return array<int, mixed>
     */
    public function implicitValidationRules(): array
    {
        $rules = [];

        if ($this->multiple) {
            // Array state: these bound the number of files.
            if ($this->maxFiles !== null) {
                $rules[] = 'max:'.$this->maxFiles;
            }

            if ($this->minFiles !== null) {
                $rules[] = 'min:'.$this->minFiles;
            }

            return $rules;
        }

        // Single upload: the value is the file, so these bound its size in KB.
        if ($this->maxSize !== null) {
            $rules[] = 'max:'.$this->maxSize;
        }

        if ($this->minSize !== null) {
            $rules[] = 'min:'.$this->minSize;
        }

        return $rules;
    }

    public function dehydrateState(mixed $state, ?Model $record = null): mixed
    {
        $values = match (true) {
            is_array($state) => $state,
            $state === null || $state === '' => [],
            default => [$state],
        };

        $paths = [];

        foreach ($values as $item) {
            if ($item instanceof UploadedFile) {
                $paths[] = $this->storeUploadedFile($item);
            } elseif (is_string($item) && $item !== '') {
                if ($this->isStoredReference($item)) {
                    // A legitimate disk-relative path or URL — keep as-is.
                    $paths[] = $item;
                } else {
                    // A raw absolute/temp path leaked into state (e.g. /tmp/phpXXX):
                    // rescue the file if it still exists, otherwise drop the dead
                    // reference — never persist a temp path as if it were stored.
                    $rescued = $this->storeFileFromPath($item);

                    if ($rescued !== null) {
                        $paths[] = $rescued;
                    }
                }
            }
        }

        return $this->isMultiple() ? $paths : ($paths[0] ?? null);
    }

    public function storeUploadedFile(UploadedFile $file): string
    {
        $disk = $this->getDisk();

        // Full control: the callback owns the entire stored path.
        if ($this->storeFileUsing !== null) {
            $path = $this->evaluate($this->storeFileUsing, ['file' => $file, 'disk' => $disk]);

            return is_string($path) ? $path : '';
        }

        $directory = $this->getDirectory();
        $public = $this->getVisibility() === 'public';

        // A custom filename (fileNameUsing / preserveFilenames) wins over the
        // hashed default; null means "let the disk generate a hashed name".
        $name = $this->resolveStoredFileName($file);

        if ($name !== null) {
            $path = $public
                ? $file->storePubliclyAs($directory, $name, $disk)
                : $file->storeAs($directory, $name, $disk);
        } else {
            $path = $public
                ? $file->storePublicly($directory, $disk)
                : $file->store($directory, $disk);
        }

        return is_string($path) ? $path : '';
    }

    /**
     * The filename to store an upload under, or null to let the disk generate a
     * hashed one. A {@see fileNameUsing()} callback wins; otherwise
     * {@see preserveFilenames()} keeps the original client name.
     */
    private function resolveStoredFileName(UploadedFile $file): ?string
    {
        if ($this->fileNameUsing !== null) {
            $name = $this->evaluate($this->fileNameUsing, ['file' => $file]);

            return is_string($name) && $name !== '' ? $name : null;
        }

        if ($this->shouldPreserveFilenames()) {
            return $file->getClientOriginalName();
        }

        return null;
    }

    /**
     * Resolve a stored path to a browser URL.
     *
     * A caller-supplied {@see previewUrlUsing()} wins outright; otherwise the
     * shared {@see StoredFileUrlResolver} handles the full URL / `data:` URI /
     * public `url()` / private signed-URL ladder, honouring {@see visibility()}.
     *
     * A private `local` disk with no temporary-url route cannot produce a URL at
     * all — both `url()` and `temporaryUrl()` throw — so a failed resolution
     * degrades to `null` (the file shows as a filename without a thumbnail)
     * rather than fatalling the whole field. Configure {@see previewUrlUsing()}
     * to give such files a real preview.
     */
    public function resolveFileUrl(string $path): ?string
    {
        if ($this->previewUrlUsing !== null) {
            $resolved = $this->evaluate($this->previewUrlUsing, ['path' => $path]);

            return is_string($resolved) ? $resolved : null;
        }

        try {
            return StoredFileUrlResolver::resolve(
                $path,
                $this->getDisk(),
                $this->getVisibility(),
                $this->urlExpiryMinutes,
            );
        } catch (\Throwable) {
            return null;
        }
    }

    public function shouldDeleteFromDisk(): bool
    {
        return $this->deletesFromDisk || $this->deleteUsing !== null;
    }

    /**
     * Physically tear down a stored file when the field is configured to
     * ({@see deletesFromDisk()} / {@see deleteUsing()}), otherwise a no-op.
     *
     * A custom {@see deleteUsing()} callback owns teardown entirely. Without one,
     * the file is deleted from the configured disk — but only a genuine
     * disk-relative path is ours to remove; a full URL / `data:` URI (an external
     * reference the field never stored) is left untouched.
     */
    public function deleteStoredFile(string $path): void
    {
        if (! $this->shouldDeleteFromDisk()) {
            return;
        }

        if ($this->deleteUsing !== null) {
            $this->evaluate($this->deleteUsing, ['path' => $path]);

            return;
        }

        if (! $this->isStoredReference($path) || filter_var($path, FILTER_VALIDATE_URL)) {
            return;
        }

        Storage::disk($this->getDisk())->delete($path);
    }

    /**
     * Whether a bare string value is a legitimate **stored** reference — a
     * disk-relative path or a full URL — rather than a raw absolute filesystem
     * path that leaked into state (e.g. a `/tmp/phpXXX` PHP upload tmp_name, whose
     * file no longer exists after its upload request).
     *
     * {@see storeUploadedFile()} always returns a disk-relative path, so an
     * absolute filesystem path is never a value this field legitimately stored.
     * Treating one as "already stored" would persist a dead temp path (and later
     * try to render it), so every consumer classifies string state through here.
     */
    public function isStoredReference(string $value): bool
    {
        if ($value === '') {
            return false;
        }

        // A full URL is a legitimate external stored value.
        if (filter_var($value, FILTER_VALIDATE_URL)) {
            return true;
        }

        // Absolute filesystem paths are raw upload paths, never a stored
        // disk-relative path.
        return ! $this->isAbsoluteFilesystemPath($value);
    }

    /**
     * Rescue a bare absolute filesystem path (a raw upload path that leaked into
     * state) by moving it onto the configured disk **only if the file still
     * exists**. Returns the stored disk-relative path, or null when the file is
     * already gone — so the caller drops the dead reference instead of persisting
     * it. Legitimate stored references never reach here (see {@see isStoredReference()}).
     */
    public function storeFileFromPath(string $path): ?string
    {
        if (! is_file($path)) {
            return null;
        }

        // test: true bypasses the is_uploaded_file() guard — this is a rescue of
        // an already-moved upload path, not a live HTTP upload.
        $file = new UploadedFile($path, basename($path), null, null, true);

        $stored = $this->storeUploadedFile($file);

        return $stored === '' ? null : $stored;
    }

    private function isAbsoluteFilesystemPath(string $value): bool
    {
        return str_starts_with($value, '/')                       // POSIX absolute
            || str_starts_with($value, '\\')                      // UNC path
            || (bool) preg_match('#^[A-Za-z]:[\\\\/]#', $value);  // Windows drive
    }

    public function getStateType(): string
    {
        return 'array';
    }

    protected function viewName(): string
    {
        return 'wire-forms::components.file-upload';
    }
}
