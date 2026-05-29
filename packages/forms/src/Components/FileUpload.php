<?php

declare(strict_types=1);

namespace NyonCode\WireForms\Components;

use Closure;

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

    public function getStateType(): string
    {
        return 'array';
    }

    protected function viewName(): string
    {
        return 'wire-forms::components.file-upload';
    }
}
