<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Support;

use Closure;
use NyonCode\WireCore\Foundation\Concerns\InteractsWithStateIcon;
use NyonCode\WireCore\Foundation\Icons\Icon;

/**
 * Resolves the icon a component should show for a given state.
 *
 * The icon twin of {@see StateColorResolver}, and the canonical owner of the
 * ladder that BadgeColumn, IconColumn and IconEntry each used to re-encode.
 * Stateless and dependency-free, so it is testable on its own; components reach
 * it through {@see InteractsWithStateIcon}.
 *
 * The guard matters more here than it did for colour. A state may be an array or
 * an object — an ordinary JSON-cast attribute is one — and `isset($map[$array])`
 * does not quietly answer false, it throws "Cannot access offset of type array
 * in isset or empty". Every copy of this ladder indexed the map unguarded, so a
 * BadgeColumn or IconColumn pointed at a JSON column took the whole render down
 * with no icons() configured at all. Column::formatValue() was hardened against
 * exactly that case; these were missed.
 */
final class IconResolver
{
    /**
     * @param  array<array-key, string|Icon>|null  $map  state → icon, already evaluated
     * @param  Closure|null  $callback  derives the icon from the state
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

        // A non-scalar state cannot index the map — see the class docblock.
        $key = EnumResolver::scalar($state);

        if ($map !== null && is_scalar($key) && array_key_exists($key, $map)) {
            return $this->normalize($map[$key]);
        }

        // The state enum carrying its own icon via the opt-in HasIcon contract.
        $enumIcon = EnumResolver::icon($state);

        if ($enumIcon !== null) {
            return $this->normalize($enumIcon);
        }

        return $default;
    }

    /** Unwrap an author-supplied icon to its name. */
    public function normalize(string|Icon $icon): string
    {
        return $icon instanceof Icon ? $icon->value() : $icon;
    }
}
