<?php

declare(strict_types=1);

namespace NyonCode\WireForms\Components;

use Closure;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * File upload field with image mode, multiple files, disk/directory configuration.
 */
class FileUpload extends Field
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

    protected ?int $imageResizeTargetWidth = null;

    protected ?int $imageResizeTargetHeight = null;

    protected ?string $imageCropAspectRatio = null;

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
     * Whether already-stored files can be removed from the field (shows a
     * delete control next to each existing file). Defaults to true.
     */
    public function deletable(bool $condition = true): static
    {
        $this->deletable = $condition;

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
     * @return list<array{path: string, url: string, name: string, isImage: bool}>
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
    public function storeUploadedFile(UploadedFile $file): string
    {
        $disk = $this->getDisk();
        $directory = $this->getDirectory();
        $public = $this->getVisibility() === 'public';

        if ($this->shouldPreserveFilenames()) {
            $name = $file->getClientOriginalName();

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
     * Resolve a stored path to a browser URL. A value that is already a full URL
     * is returned as-is; otherwise it is resolved through the configured disk.
     */
    public function resolveFileUrl(string $path): string
    {
        if (filter_var($path, FILTER_VALIDATE_URL)) {
            return $path;
        }

        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk($this->getDisk());

        return $disk->url($path);
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
