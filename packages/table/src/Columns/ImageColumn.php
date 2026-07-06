<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Columns;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use NyonCode\WireCore\Foundation\Enums\Size;

class ImageColumn extends Column
{
    protected string $imageSize = 'md';

    protected bool $circular = false;

    protected ?string $defaultImageUrl = null;

    protected ?string $disk = null;

    protected ?string $visibility = 'protected';

    protected bool $stacked = false;

    protected int $stackLimit = 3;

    protected int $ring = 0;

    protected ?int $ringColor = null;

    /**
     * Set the image size scale (xs|sm|md|lg|xl|2xl).
     *
     * Signature stays compatible with the canonical {@see HasSize::size()}
     * (`string|Closure`) so loading/instantiating ImageColumn does not fatal on
     * the LSP contravariance check; a Closure is resolved to its string scale.
     */
    public function size(string|Size|Closure $size): static
    {
        $this->imageSize = match (true) {
            $size instanceof Closure => (string) $this->evaluate($size),
            $size instanceof Size => $size->value,
            default => $size,
        };

        return $this;
    }

    public function getImageSize(): string
    {
        return $this->imageSize;
    }

    public function circular(bool $circular = true): static
    {
        $this->circular = $circular;

        return $this;
    }

    public function isCircular(): bool
    {
        return $this->circular;
    }

    public function defaultImageUrl(?string $url): static
    {
        $this->defaultImageUrl = $url;

        return $this;
    }

    public function getDefaultImageUrl(): ?string
    {
        return $this->defaultImageUrl;
    }

    public function getDisk(): ?string
    {
        return $this->disk;
    }

    public function visibility(?string $visibility): static
    {
        $this->visibility = $visibility;

        return $this;
    }

    public function stacked(bool $stacked = true): static
    {
        $this->stacked = $stacked;

        return $this;
    }

    public function isStacked(): bool
    {
        return $this->stacked;
    }

    public function stackLimit(int $limit): static
    {
        $this->stackLimit = $limit;

        return $this;
    }

    public function getStackLimit(): int
    {
        return $this->stackLimit;
    }

    public function ring(int $ring, ?int $color = null): static
    {
        $this->ring = $ring;
        $this->ringColor = $color;

        return $this;
    }

    public function renderCell(Model $record): string
    {
        if (! $this->canView() || ! $this->isVisibleForRecord($record)) {
            return '';
        }

        $state = $this->getState($record);
        $url = $this->resolveImageUrl($state);

        if (! $url) {
            return $this->getPlaceholder() ?? '';
        }

        return $this->renderView('tables.columns.image', [
            'url' => $url,
            'sizeClasses' => $this->getSizeClasses(),
            'shapeClasses' => $this->circular ? 'rounded-full' : 'rounded-md',
            'ringClasses' => $this->ring > 0 ? "ring-$this->ring ring-white dark:ring-gray-800" : '',
        ]);
    }

    private function resolveImageUrl(mixed $state): ?string
    {
        if (empty($state)) {
            return $this->defaultImageUrl;
        }

        // If it's already a full URL
        if (filter_var($state, FILTER_VALIDATE_URL)) {
            return $state;
        }

        // If using disk storage
        if ($this->disk) {
            /** @var FilesystemAdapter $diskInstance */
            $diskInstance = Storage::disk($this->disk);

            return $diskInstance->url($state);
        }

        // Assume it's a path in public storage
        return Storage::url($state);
    }

    public function disk(?string $disk): static
    {
        $this->disk = $disk;

        return $this;
    }

    public function getSizeClasses(): string
    {
        return match ($this->imageSize) {
            'xs' => 'w-6 h-6',
            'sm' => 'w-8 h-8',
            'md' => 'w-10 h-10',
            'lg' => 'w-12 h-12',
            'xl' => 'w-14 h-14',
            '2xl' => 'w-16 h-16',
            default => 'w-10 h-10',
        };
    }
}
