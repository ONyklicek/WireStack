<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Support;

use Closure;
use NyonCode\WireCore\Foundation\Colors\Color;
use NyonCode\WireCore\Foundation\Concerns\InteractsWithStateColor;

/**
 * Resolves the color a component should wear for a given state.
 *
 * The canonical owner of the "state → color" ladder that BadgeColumn,
 * IconColumn, PollColumn and IconEntry each used to re-encode. Stateless and
 * dependency-free, so it is testable on its own; components reach it through
 * {@see InteractsWithStateColor}.
 *
 * Two details are the reason this lives in one place. A state may be an enum,
 * which cannot index an array — PHP throws "Cannot access offset of type X on
 * array" — so the key goes through EnumResolver::scalar() behind an is_scalar()
 * guard. And an author-supplied color may arrive as a Color enum rather than a
 * string, so every rung is unwrapped. Missing either is not a style nit:
 * PollColumn missed both and threw a TypeError out of renderCell().
 */
final class StateColorResolver
{
    /**
     * @param  array<array-key, string|Color>|null  $map  state → color, already evaluated
     * @param  Closure|null  $callback  derives the color from the state
     * @param  string|null  $default  when no rung matches
     */
    public function resolve(
        mixed $state,
        ?array $map = null,
        ?Closure $callback = null,
        ?string $default = null,
    ): ?string {
        if ($callback !== null) {
            $result = $callback($state);

            if ($result !== null) {
                return $this->normalize($result);
            }
        }

        $key = EnumResolver::scalar($state);

        if ($map !== null && is_scalar($key) && array_key_exists($key, $map)) {
            return $this->normalize($map[$key]);
        }

        // The state enum carrying its own color via the opt-in HasColor contract.
        $enumColor = EnumResolver::color($state);

        if ($enumColor !== null) {
            return $this->normalize($enumColor);
        }

        return $default;
    }

    /** Unwrap an author-supplied colour to its name. */
    public function normalize(string|Color $color): string
    {
        return $color instanceof Color ? $color->value : $color;
    }
}
