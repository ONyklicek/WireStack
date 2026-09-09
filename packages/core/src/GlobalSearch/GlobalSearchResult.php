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
 *
 * ## Why an action is a name and not a closure
 *
 * A row that carried the action itself would have to survive a Livewire round
 * trip, and a closure does not. It would also make this class the thing that
 * knows what an `Action` is, which is the import the layer rule forbids. So a
 * row carries the action's **name** and the key of whatever offers it, and the
 * palette asks that owner again when the row is chosen — one more static call,
 * against a whole class of serialization bugs.
 */
final readonly class GlobalSearchResult
{
    /**
     * @param  string  $resourceKey  Which resource produced this, for grouping.
     * @param  int|string|null  $recordKey  The record's key, for `wire:key` and for the click.
     *                                      Null for a row that is about no record at all — a
     *                                      navigation entry, or a command that stands alone.
     * @param  string  $title  The line a user reads first.
     * @param  string|null  $subtitle  Context under it — a status, an email, a date.
     * @param  string|null  $url  Where selecting it goes; null when nothing routes a page for it.
     * @param  string|null  $icon  Icon name, resolved the same way every other icon in the framework is.
     * @param  PaletteRowKind  $kind  What pressing Enter on this row does.
     * @param  string|null  $actionName  The action to run, for the two invoking kinds.
     */
    public function __construct(
        public string $resourceKey,
        public int|string|null $recordKey,
        public string $title,
        public ?string $subtitle = null,
        public ?string $url = null,
        public ?string $icon = null,
        public PaletteRowKind $kind = PaletteRowKind::Record,
        public ?string $actionName = null,
    ) {}

    /**
     * The same row, pointed somewhere.
     *
     * A new instance because this is readonly, and readonly because the palette
     * renders many of these at once and must not have a row change under it
     * mid-render. {@see GlobalSearch} calls this for a result that named no URL
     * of its own, so a resource stops hand-writing one it already has the two
     * halves of — its key and the record's.
     *
     * Every field is named rather than positional. The positional form was one
     * argument away from silently dropping `kind` and `actionName` when they were
     * added — a command would have come back out of this method as a record.
     */
    public function withUrl(?string $url): self
    {
        return new self(
            resourceKey: $this->resourceKey,
            recordKey: $this->recordKey,
            title: $this->title,
            subtitle: $this->subtitle,
            url: $url,
            icon: $this->icon,
            kind: $this->kind,
            actionName: $this->actionName,
        );
    }
}
