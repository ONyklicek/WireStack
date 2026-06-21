<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Infolists\Components;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Image entry — renders the state as one or more images (avatars, thumbnails).
 *
 * Absolute/data URLs are used verbatim; relative paths are resolved through the
 * configured `disk()`. Array states render as a (optionally stacked) gallery.
 */
class ImageEntry extends Entry
{
    protected ?string $disk = null;

    protected int $imageSize = 40;

    protected bool $circular = false;

    protected bool $stacked = false;

    protected ?string $defaultImageUrl = null;

    public function disk(?string $disk): static
    {
        $this->disk = $disk;

        return $this;
    }

    public function imageSize(int $size): static
    {
        $this->imageSize = $size;

        return $this;
    }

    public function getImageSize(): int
    {
        return $this->imageSize;
    }

    public function circular(bool $condition = true): static
    {
        $this->circular = $condition;

        return $this;
    }

    public function isCircular(): bool
    {
        return $this->circular;
    }

    public function stacked(bool $condition = true): static
    {
        $this->stacked = $condition;

        return $this;
    }

    public function isStacked(): bool
    {
        return $this->stacked;
    }

    public function defaultImageUrl(?string $url): static
    {
        $this->defaultImageUrl = $url;

        return $this;
    }

    /**
     * Resolved image URLs for the state.
     *
     * @return array<int, string>
     */
    public function getImageUrls(): array
    {
        $state = $this->getState();

        $values = is_iterable($state)
            ? (is_array($state) ? $state : iterator_to_array($state))
            : ($state === null || $state === '' ? [] : [$state]);

        $urls = [];

        foreach ($values as $value) {
            $url = $this->resolveUrl($value);

            if ($url !== null) {
                $urls[] = $url;
            }
        }

        if ($urls === [] && $this->defaultImageUrl !== null) {
            $urls[] = $this->defaultImageUrl;
        }

        return $urls;
    }

    private function resolveUrl(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return $this->defaultImageUrl;
        }

        if (Str::startsWith($value, ['http://', 'https://', 'data:', '//', '/'])) {
            return $value;
        }

        if ($this->disk !== null) {
            /** @var FilesystemAdapter $diskInstance */
            $diskInstance = Storage::disk($this->disk);

            return $diskInstance->url($value);
        }

        return $value;
    }

    protected function viewName(): string
    {
        return 'wire-core::infolists.entries.image';
    }
}
