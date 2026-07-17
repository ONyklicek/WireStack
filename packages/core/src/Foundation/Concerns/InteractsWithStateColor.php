<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Concerns;

use Closure;
use NyonCode\WireCore\Foundation\Colors\Color;
use NyonCode\WireCore\Foundation\Support\StateColorResolver;

/**
 * Deriving a component's color from its current state.
 *
 * Where {@see HasColor} owns the static color an author sets, this owns the one
 * a component derives from its state. It holds configuration and delegates the
 * resolving to {@see StateColorResolver}.
 *
 * Three hooks let a surface differ without re-encoding the ladder:
 *  - resolveStateColorOverride() — a mode that answers first, e.g. boolean()
 *  - resolveStateColorMap()      — evaluate a Closure map against the record
 *  - getDefaultStateColor()      — what to wear when nothing maps
 */
trait InteractsWithStateColor
{
    /** @var array<array-key, string|Color>|Closure|null state → color name */
    protected array|Closure|null $stateColorMap = null;

    protected ?Closure $colorCallback = null;

    /** The static color the author set, supplied by {@see HasColor}. */
    abstract public function getColor(): ?string;

    /**
     * Map state values to colors.
     *
     * @param  array<array-key, string|Color>|Closure  $colors
     */
    public function colors(array|Closure $colors): static
    {
        // Normalize an array eagerly so a bad map is rejected here rather than
        // mid-render; a Closure can only be unwrapped once it has been called.
        $this->stateColorMap = is_array($colors)
            ? array_map(app(StateColorResolver::class)->normalize(...), $colors)
            : $colors;

        return $this;
    }

    /** Derive the color from the state. The callback may return a Color or a string. */
    public function colorUsing(Closure $callback): static
    {
        $this->colorCallback = $callback;

        return $this;
    }

    public function getColorForState(mixed $state): ?string
    {
        return $this->resolveStateColorOverride($state)
            ?? app(StateColorResolver::class)->resolve(
                $state,
                $this->resolveStateColorMap(),
                $this->colorCallback,
                $this->getDefaultStateColor(),
            );
    }

    /** A mode that answers before any author-supplied mapping — e.g. boolean(). */
    protected function resolveStateColorOverride(mixed $state): ?string
    {
        return null;
    }

    /** @return array<array-key, string|Color>|null */
    protected function resolveStateColorMap(): ?array
    {
        return is_array($this->stateColorMap) ? $this->stateColorMap : null;
    }

    /**
     * The color when the state maps to nothing: the component's own color()
     * first — otherwise setting one on a stateful component would do nothing —
     * then Color::Gray as the neutral floor.
     */
    protected function getDefaultStateColor(): ?string
    {
        return $this->getColor() ?? Color::Gray->value;
    }
}
