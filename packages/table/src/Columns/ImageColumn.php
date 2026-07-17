<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Columns;

use Closure;
use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Foundation\Enums\Size;
use NyonCode\WireCore\Foundation\Support\StoredFileUrlResolver;

class ImageColumn extends Column
{
    protected string $imageSize = 'md';

    protected bool $circular = false;

    protected ?string $defaultImageUrl = null;

    protected ?string $disk = null;

    /**
     * 'public' builds a plain Storage URL; anything else asks the disk for a
     * signed temporary URL instead. Defaults to 'public' because that is what
     * this column has always rendered — a different default would silently
     * change every existing ->disk() column.
     */
    protected ?string $visibility = 'public';

    protected int $urlExpiryMinutes = 5;

    protected bool $stacked = false;

    protected int $stackLimit = 3;

    protected int $ring = 0;

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

    /**
     * 'public' (default) renders a plain Storage URL; any other value makes the
     * disk sign a temporary URL. Only meaningful together with {@see disk()}.
     */
    public function visibility(?string $visibility): static
    {
        $this->visibility = $visibility;

        return $this;
    }

    public function getVisibility(): ?string
    {
        return $this->visibility;
    }

    /**
     * How long a non-public image's signed URL stays valid.
     */
    public function urlExpiry(int $minutes): static
    {
        $this->urlExpiryMinutes = $minutes;

        return $this;
    }

    public function getUrlExpiry(): int
    {
        return $this->urlExpiryMinutes;
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

    /**
     * Width of the ring separating stacked images from the background.
     *
     * Took a second `?int $color` until 2026-07-15 that nothing ever read — an
     * int cannot name a colour in this palette, no caller ever passed one, and
     * its meaning did not survive the commit that introduced it. The ring uses
     * the canonical white/dark-gray separator; a real colour knob would go
     * through HasColor, and can be added when someone actually needs it.
     */
    public function ring(int $ring): static
    {
        $this->ring = $ring;

        return $this;
    }

    public function renderCell(Model $record): string
    {
        if (! $this->canView() || ! $this->isVisibleForRecord($record)) {
            return '';
        }

        $urls = $this->resolveImageUrls($this->getState($record));

        if ($urls === []) {
            return $this->getEmptyCellText();
        }

        // A stacked gallery only shows the first stackLimit images; the rest are
        // summarised as a "+N" chip so a long list cannot blow the row height.
        $overflow = 0;
        if ($this->stacked && count($urls) > $this->stackLimit) {
            $overflow = count($urls) - $this->stackLimit;
            $urls = array_slice($urls, 0, $this->stackLimit);
        }

        return $this->renderView('tables.columns.image', [
            'urls' => $urls,
            'overflow' => $overflow,
            'stacked' => $this->stacked,
            'sizeClasses' => $this->getSizeClasses(),
            'shapeClasses' => $this->circular ? 'rounded-full' : 'rounded-md',
            'ringClasses' => $this->ring > 0 ? "ring-$this->ring ring-white dark:ring-gray-800" : '',
        ]);
    }

    /**
     * Resolve the cell's state to a list of image URLs.
     *
     * An array (or JSON array, as an `array`-cast column arrives) renders as a
     * gallery; a scalar stays a single image. The empty list means "nothing to
     * show" and lets the caller fall back to the placeholder.
     *
     * @return array<int, string>
     */
    private function resolveImageUrls(mixed $state): array
    {
        if (is_string($state) && str_starts_with(trim($state), '[')) {
            $decoded = json_decode($state, true);
            if (is_array($decoded)) {
                $state = $decoded;
            }
        }

        $states = is_array($state) ? array_values($state) : [$state];

        $urls = [];
        foreach ($states as $single) {
            $url = $this->resolveImageUrl($single);
            if ($url !== null && $url !== '') {
                $urls[] = $url;
            }
        }

        // A single empty state still deserves the default image, if configured.
        if ($urls === [] && $this->defaultImageUrl !== null && ! is_array($state)) {
            $urls[] = $this->defaultImageUrl;
        }

        return $urls;
    }

    private function resolveImageUrl(mixed $state): ?string
    {
        // Signing is only meaningful once a disk is named; a diskless column has
        // always rendered a plain default-disk URL regardless of visibility.
        $visibility = $this->disk !== null ? ($this->visibility ?? 'public') : 'public';

        return StoredFileUrlResolver::resolve(
            is_string($state) ? $state : null,
            $this->disk,
            $visibility,
            $this->urlExpiryMinutes,
            $this->defaultImageUrl,
        );
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
