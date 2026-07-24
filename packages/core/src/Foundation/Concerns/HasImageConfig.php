<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Concerns;

/**
 * Shared display configuration for image surfaces (the ImageEntry infolist
 * component and the ImageColumn).
 *
 * Owns the source disk, a fallback URL, and the circular / stacked layout flags.
 * Deliberately excludes size and URL resolution: the pixel-vs-scale size default
 * and the per-surface URL ladder genuinely differ, so those stay local.
 */
trait HasImageConfig
{
    protected ?string $disk = null;

    protected bool $circular = false;

    protected bool $stacked = false;

    protected ?string $defaultImageUrl = null;

    /** Set the storage disk the image path is resolved against. */
    public function disk(?string $disk): static
    {
        $this->disk = $disk;

        return $this;
    }

    /** Render the image as a circle instead of a rounded square. */
    public function circular(bool $condition = true): static
    {
        $this->circular = $condition;

        return $this;
    }

    public function isCircular(): bool
    {
        return $this->circular;
    }

    /** Overlap several images into a stacked avatar group. */
    public function stacked(bool $condition = true): static
    {
        $this->stacked = $condition;

        return $this;
    }

    public function isStacked(): bool
    {
        return $this->stacked;
    }

    /** Set the URL shown when the state has no image. */
    public function defaultImageUrl(?string $url): static
    {
        $this->defaultImageUrl = $url;

        return $this;
    }

    public function getDisk(): ?string
    {
        return $this->disk;
    }

    public function getDefaultImageUrl(): ?string
    {
        return $this->defaultImageUrl;
    }
}
