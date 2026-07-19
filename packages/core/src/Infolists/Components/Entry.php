<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Infolists\Components;

use Closure;
use NyonCode\WireCore\Foundation\Colors\Color;
use NyonCode\WireCore\Foundation\Components\ViewComponent;
use NyonCode\WireCore\Foundation\Concerns\HasActions;
use NyonCode\WireCore\Foundation\Concerns\HasColor;
use NyonCode\WireCore\Foundation\Concerns\HasIcon;
use NyonCode\WireCore\Foundation\Concerns\HasPlaceholder;
use NyonCode\WireCore\Foundation\Concerns\HasTooltip;
use NyonCode\WireCore\Foundation\Contracts\HasFieldActions;
use NyonCode\WireCore\Foundation\Support\EnumResolver;

/**
 * Base class for infolist entries — read-only display of a single value
 * resolved from the bound record.
 *
 * Entries reuse the canonical Foundation concerns (label, icon, color, size,
 * visibility, column span, placeholder, tooltip) so they share one vocabulary
 * with form fields and table columns. State is resolved from the record by the
 * entry name (dot notation supported via `data_get`) unless a `state()`
 * callback overrides it.
 *
 * @phpstan-consistent-constructor
 */
abstract class Entry extends ViewComponent implements HasFieldActions
{
    use HasActions;
    use HasColor;
    use HasIcon;
    use HasPlaceholder;
    use HasTooltip;

    protected mixed $record = null;

    protected string|Color|Closure|null $color = null;

    protected ?Closure $stateUsing = null;

    protected ?Closure $formatStateUsing = null;

    /**
     * Bind the record this entry reads its value from.
     */
    public function record(mixed $record): static
    {
        $this->record = $record;

        return $this;
    }

    public function getRecord(): mixed
    {
        return $this->record;
    }

    /**
     * Resolve the raw state with a custom callback instead of reading the
     * record by name. Receives the bound record.
     */
    public function getStateUsing(Closure $callback): static
    {
        $this->stateUsing = $callback;

        return $this;
    }

    /** Compute the displayed value from a Closure (receives the record) instead of reading the bound state key. */
    public function state(Closure $callback): static
    {
        return $this->getStateUsing($callback);
    }

    /**
     * Transform the resolved state for display. Receives ($state, $record).
     */
    public function formatStateUsing(Closure $callback): static
    {
        $this->formatStateUsing = $callback;

        return $this;
    }

    /** Set the entry's color (a palette name, a `Color` enum, or a Closure). */
    public function color(string|Color|Closure|null $color): static
    {
        $this->color = $color;

        return $this;
    }

    public function getColor(): ?string
    {
        $color = $this->evaluateForState($this->color);

        return $color instanceof Color ? $color->value : $color;
    }

    /**
     * Resolve the raw (unformatted) state from the record.
     */
    public function getState(): mixed
    {
        if ($this->stateUsing instanceof Closure) {
            $value = ($this->stateUsing)($this->record);
        } else {
            $value = data_get($this->record, $this->getName());
        }

        if ($value === null || $value === '') {
            return $this->getDefault();
        }

        return $value;
    }

    /**
     * The display string for the resolved state (placeholder when empty).
     */
    public function getFormattedState(): string
    {
        $state = $this->getState();

        if ($this->formatStateUsing instanceof Closure) {
            $state = ($this->formatStateUsing)($state, $this->record);
        }

        // Enum- and array/JSON-cast attributes arrive as raw instances; render a display-safe value.
        $state = EnumResolver::display($state);

        if ($state === null || $state === '') {
            return $this->getPlaceholder() ?? '-';
        }

        return (string) $state;
    }

    /**
     * Evaluate a value that may be a Closure, exposing $state and $record.
     */
    protected function evaluateForState(mixed $value): mixed
    {
        if (! $value instanceof Closure) {
            return $value instanceof Color ? $value->value : $value;
        }

        return $this->evaluate($value, [
            'state' => $this->getState(),
            'record' => $this->record,
        ]);
    }
}
