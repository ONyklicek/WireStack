<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Concerns;

use Closure;
use NyonCode\WireCore\Foundation\Icons\Icon;
use NyonCode\WireCore\Foundation\Support\IconResolver;

/**
 * Deriving a component's icon from its current state.
 *
 * The icon twin of {@see InteractsWithStateColor}: where {@see HasIcon} owns the
 * static icon an author sets, this owns the one a component derives from its
 * state. It holds configuration and delegates the resolving to
 * {@see IconResolver}.
 *
 * Three hooks let a surface differ without re-encoding the ladder:
 *  - resolveStateIconOverride() — a mode that answers first, e.g. boolean()
 *  - resolveStateIconMap()      — evaluate a Closure map against the record
 *  - getDefaultStateIcon()      — what to show when nothing maps
 */
trait InteractsWithStateIcon
{
    /** @var array<array-key, string|Icon>|Closure|null state → icon name */
    protected array|Closure|null $stateIconMap = null;

    protected ?Closure $iconCallback = null;

    /** The static icon the author set, supplied by {@see HasIcon}. */
    abstract public function getIcon(): ?string;

    /**
     * Map state values to icons.
     *
     * @param  array<array-key, string|Icon>|Closure  $icons
     */
    public function icons(array|Closure $icons): static
    {
        // Normalize an array eagerly so a bad map is rejected here rather than
        // mid-render; a Closure can only be unwrapped once it has been called.
        $this->stateIconMap = is_array($icons)
            ? array_map(app(IconResolver::class)->normalize(...), $icons)
            : $icons;

        return $this;
    }

    /** Derive the icon from the state. The callback may return an Icon or a string. */
    public function iconUsing(Closure $callback): static
    {
        $this->iconCallback = $callback;

        return $this;
    }

    public function getIconForState(mixed $state): ?string
    {
        return $this->resolveStateIconOverride($state)
            ?? app(IconResolver::class)->resolve(
                $state,
                $this->resolveStateIconMap(),
                $this->iconCallback,
                $this->getDefaultStateIcon(),
            );
    }

    /** A mode that answers before any author-supplied mapping — e.g. boolean(). */
    protected function resolveStateIconOverride(mixed $state): ?string
    {
        return null;
    }

    /** @return array<array-key, string|Icon>|null */
    protected function resolveStateIconMap(): ?array
    {
        return is_array($this->stateIconMap) ? $this->stateIconMap : null;
    }

    /**
     * The icon when the state maps to nothing: the component's own icon() —
     * otherwise setting one on a stateful component would do nothing. There is
     * no neutral floor to fall back to, so an unset icon() means no icon.
     */
    protected function getDefaultStateIcon(): ?string
    {
        return $this->getIcon();
    }
}
