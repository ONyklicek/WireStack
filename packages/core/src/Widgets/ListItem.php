<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Widgets;

use NyonCode\WireCore\Foundation\Concerns\HasColor;
use NyonCode\WireCore\Foundation\Concerns\HasExtraAttributes;
use NyonCode\WireCore\Foundation\Concerns\InteractsWithColor;
use NyonCode\WireCore\Foundation\Icons\Icon;
use NyonCode\WireCore\Foundation\Support\EvaluatesClosures;

/**
 * One entry of a {@see ListWidget}: what happened, optionally when, and
 * optionally where to go to see it.
 *
 * ## The link, and what it costs
 *
 * A url turns the whole row into an anchor rather than adding one — a feed of
 * recent orders is a list of things to open, and a link that only covers the
 * title gives a pointing device a target three pixels tall. {@see newTab()}
 * carries `rel="noopener noreferrer"` with it, which is not optional decoration:
 * without `noopener` the opened page can reach back through `window.opener`.
 *
 * ## The icon's two colours
 *
 * An entry's colour tints the icon's disc, not the text. A feed is read as a
 * column of sentences and colouring the sentences turns it into a rainbow;
 * colouring a 32-pixel disc beside each one keeps "failed" scannable while the
 * text stays the text. Both classes come from the canonical palette, so a
 * caller-supplied name never reaches Tailwind.
 *
 * @phpstan-consistent-constructor
 */
class ListItem
{
    use EvaluatesClosures;
    use HasExtraAttributes;

    // $color + color() + getColor() come from the canonical colour-state owner;
    // HasColor stays for the class-map resolvers (getSoftBgClass, …).
    use InteractsWithColor;

    protected ?string $description = null;

    protected ?string $meta = null;

    protected ?string $icon = null;

    protected ?string $url = null;

    protected bool $newTab = false;

    public function __construct(protected string $title) {}

    public static function make(string $title): static
    {
        return new static($title);
    }

    /** Set the secondary line under the title. */
    public function description(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    /** Set the right-aligned aside — a timestamp, a count, a status word. */
    public function meta(?string $meta): static
    {
        $this->meta = $meta;

        return $this;
    }

    /** Set the icon shown on the disc beside the entry. */
    public function icon(string|Icon|null $icon): static
    {
        $this->icon = $icon instanceof Icon ? $icon->value() : $icon;

        return $this;
    }

    /** Make the whole entry a link to this url. */
    public function url(?string $url): static
    {
        $this->url = $url;

        return $this;
    }

    /** Open the entry's link in a new tab, with `rel="noopener noreferrer"`. */
    public function newTab(bool $condition = true): static
    {
        $this->newTab = $condition;

        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getMeta(): ?string
    {
        return $this->meta;
    }

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function opensInNewTab(): bool
    {
        return $this->newTab;
    }

    /** Background classes for the icon's disc, from the canonical palette. */
    public function getIconBackgroundClass(): string
    {
        return HasColor::getSoftBgClass($this->color ?? 'primary');
    }

    /** Icon colour on that disc, from the same palette. */
    public function getIconColorClass(): string
    {
        return HasColor::getTextColorClasses($this->color ?? 'primary');
    }
}
