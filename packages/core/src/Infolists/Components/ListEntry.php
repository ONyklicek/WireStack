<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Infolists\Components;

/**
 * List entry — renders a collection state as a bulleted list or a row of badge
 * chips, without the full per-row schema of a {@see RepeatableEntry}.
 *
 * The state may be an array/iterable, or a delimited string split via
 * {@see separator()}. Items reuse the canonical {@see TextEntry} formatting
 * (number/money/date, `formatStateUsing`, `limit`). Use {@see badge()} for
 * chips and {@see limitList()} to cap the number of visible items with a
 * "+N" overflow indicator.
 */
class ListEntry extends TextEntry
{
    protected bool $bulleted = true;

    protected ?int $limitList = null;

    protected ?string $separator = null;

    /**
     * Cap the number of visible items; the remainder is summarised as "+N".
     */
    public function limitList(?int $limit): static
    {
        $this->limitList = $limit;

        return $this;
    }

    public function getLimitList(): ?int
    {
        return $this->limitList;
    }

    /**
     * Split a scalar string state into list items on this delimiter.
     */
    public function separator(?string $separator): static
    {
        $this->separator = $separator;

        return $this;
    }

    /**
     * All formatted, non-empty list items resolved from the state.
     *
     * @return array<int, string>
     */
    public function getItems(): array
    {
        $state = $this->getState();

        if (is_string($state) && $this->separator !== null && $this->separator !== '') {
            $values = array_map('trim', explode($this->separator, $state));
        } elseif (is_iterable($state)) {
            $values = [];

            foreach ($state as $item) {
                $values[] = $item;
            }
        } elseif ($state === null || $state === '') {
            $values = [];
        } else {
            $values = [$state];
        }

        $items = [];

        foreach ($values as $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $items[] = $this->formatScalar($value);
        }

        return $items;
    }

    /**
     * The items rendered up front, honoring {@see limitList()}.
     *
     * @return array<int, string>
     */
    public function getVisibleItems(): array
    {
        $items = $this->getItems();

        if ($this->limitList !== null && count($items) > $this->limitList) {
            return array_slice($items, 0, $this->limitList);
        }

        return $items;
    }

    /**
     * How many items are hidden behind the {@see limitList()} cap.
     */
    public function getRemainingCount(): int
    {
        if ($this->limitList === null) {
            return 0;
        }

        return max(0, count($this->getItems()) - $this->limitList);
    }

    protected function viewName(): string
    {
        return 'wire-core::infolists.entries.list';
    }
}
