<?php

declare(strict_types=1);

namespace NyonCode\WireCore\GlobalSearch;

/**
 * One row in the command palette.
 *
 * A resource turns a record into this, so the palette never reaches into a model
 * it knows nothing about. The shape is deliberately flat and already resolved —
 * a title, a line under it, somewhere to go — because the palette renders many
 * of these at once and must not call back into a resource per row.
 */
final readonly class GlobalSearchResult
{
    /**
     * @param  string  $resourceKey  Which resource produced this, for grouping.
     * @param  int|string  $recordKey  The record's key, for `wire:key` and for the click.
     * @param  string  $title  The line a user reads first.
     * @param  string|null  $subtitle  Context under it — a status, an email, a date.
     * @param  string|null  $url  Where selecting it goes; null when nothing routes a page for it.
     * @param  string|null  $icon  Icon name, resolved the same way every other icon in the framework is.
     */
    public function __construct(
        public string $resourceKey,
        public int|string $recordKey,
        public string $title,
        public ?string $subtitle = null,
        public ?string $url = null,
        public ?string $icon = null,
    ) {}

    /**
     * The same row, pointed somewhere.
     *
     * A new instance because this is readonly, and readonly because the palette
     * renders many of these at once and must not have a row change under it
     * mid-render. {@see GlobalSearch} calls this for a result that named no URL
     * of its own, so a resource stops hand-writing one it already has the two
     * halves of — its key and the record's.
     */
    public function withUrl(?string $url): self
    {
        return new self(
            $this->resourceKey,
            $this->recordKey,
            $this->title,
            $this->subtitle,
            $url,
            $this->icon,
        );
    }
}
