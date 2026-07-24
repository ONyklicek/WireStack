<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Infolists\Components;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use NyonCode\WireCore\Foundation\Concerns\HasImageConfig;

/**
 * Image entry — renders the state as one or more images (avatars, thumbnails).
 *
 * Absolute/data URLs are used verbatim; relative paths are resolved through the
 * configured `disk()`. Array states render as a (optionally stacked) gallery.
 */
class ImageEntry extends Entry
{
    use HasImageConfig;

    protected int $imageSize = 40;

    /** Set the rendered image edge length in pixels (default 40). */
    public function imageSize(int $size): static
    {
        $this->imageSize = $size;

        return $this;
    }

    public function getImageSize(): int
    {
        return $this->imageSize;
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
