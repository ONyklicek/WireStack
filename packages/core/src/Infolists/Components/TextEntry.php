<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Infolists\Components;

use Illuminate\Support\Str;
use NyonCode\WireCore\Foundation\Concerns\FormatsState;
use NyonCode\WireCore\Foundation\Concerns\HasColor;
use NyonCode\WireCore\Foundation\Concerns\HasFontWeight;
use NyonCode\WireCore\Foundation\Enums\FontWeight;

/**
 * Text entry — the default entry. Supports number/money/date formatting (shared
 * with table columns via {@see FormatsState}), badge rendering, copy-to-clipboard,
 * truncation, font weight, and list rendering for array states.
 */
class TextEntry extends Entry
{
    use FormatsState;

    protected bool $badge = false;

    protected ?int $limit = null;

    protected bool $copyable = false;

    protected ?string $weight = null;

    protected bool $prose = false;

    protected bool $listWithLineBreaks = false;

    protected bool $bulleted = false;

    public function badge(bool $condition = true): static
    {
        $this->badge = $condition;

        return $this;
    }

    public function isBadge(): bool
    {
        return $this->badge;
    }

    public function limit(?int $limit): static
    {
        $this->limit = $limit;

        return $this;
    }

    public function copyable(bool $condition = true): static
    {
        $this->copyable = $condition;

        return $this;
    }

    public function isCopyable(): bool
    {
        return $this->copyable;
    }

    /**
     * Font weight: e.g. 'normal', 'medium', 'semibold', 'bold'.
     */
    public function weight(string|FontWeight|null $weight): static
    {
        $this->weight = $weight instanceof FontWeight ? $weight->value : $weight;

        return $this;
    }

    public function getWeight(): ?string
    {
        return $this->weight;
    }

    public function prose(bool $condition = true): static
    {
        $this->prose = $condition;

        return $this;
    }

    public function isProse(): bool
    {
        return $this->prose;
    }

    public function listWithLineBreaks(bool $condition = true): static
    {
        $this->listWithLineBreaks = $condition;

        return $this;
    }

    public function bulleted(bool $condition = true): static
    {
        $this->bulleted = $condition;

        if ($condition) {
            $this->listWithLineBreaks = true;
        }

        return $this;
    }

    public function isBulleted(): bool
    {
        return $this->bulleted;
    }

    public function isList(): bool
    {
        return $this->listWithLineBreaks || $this->bulleted;
    }

    /**
     * Foreground text color class for the rendered value (and list items).
     *
     * Delegates to the canonical {@see HasColor::getTextColorClasses()} palette so
     * the entry never re-encodes the hue map in Blade; an unset color falls back
     * to the default body text color.
     */
    public function getTextColorClass(): string
    {
        $color = $this->getColor();

        return $color !== null
            ? HasColor::getTextColorClasses($color)
            : 'text-gray-900 dark:text-white';
    }

    /**
     * Soft badge pill color class used when the entry renders as a badge.
     *
     * Delegates to the canonical {@see HasColor::getBadgeColorClasses()} palette,
     * defaulting to a neutral gray pill when no color is set.
     */
    public function getBadgeColorClass(): string
    {
        return HasColor::getBadgeColorClasses($this->getColor() ?? 'gray');
    }

    public function getWeightClass(): string
    {
        return HasFontWeight::getFontWeightClasses((string) $this->weight);
    }

    public function getFormattedState(): string
    {
        return $this->formatScalar($this->getState());
    }

    /**
     * Resolved, formatted state values as a list of strings. A single scalar
     * yields a one-element array; array/iterable states yield one entry each.
     *
     * @return array<int, string>
     */
    public function getFormattedStates(): array
    {
        $state = $this->getState();

        if (is_iterable($state)) {
            $items = [];

            foreach ($state as $item) {
                $items[] = $this->formatScalar($item);
            }

            return $items;
        }

        return [$this->formatScalar($state)];
    }

    protected function formatScalar(mixed $value): string
    {
        $value = $this->applyNumericAndDateFormatting($value);

        if ($this->formatStateUsing !== null) {
            $value = ($this->formatStateUsing)($value, $this->record);
        }

        if ($value === null || $value === '') {
            return $this->getPlaceholder() ?? '-';
        }

        $formatted = (string) $value;

        if ($this->limit !== null && Str::length($formatted) > $this->limit) {
            $formatted = Str::limit($formatted, $this->limit);
        }

        return $formatted;
    }

    protected function viewName(): string
    {
        return 'wire-core::infolists.entries.text';
    }
}
