<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Concerns;

use NyonCode\WireCore\Core\State\StateContainer;
use NyonCode\WireCore\Foundation\Components\LayoutComponent;
use NyonCode\WireCore\Foundation\Support\EvaluatesClosures;

/**
 * Canonical owner of the reactive `$get` / `$set` / `$state` accessors injected
 * into evaluated Closures by {@see EvaluatesClosures}.
 *
 * Both form fields and layout components ({@see LayoutComponent})
 * need Filament-style sibling-state access inside dynamic callbacks such as
 * `visible()`, `disabled()` and `hidden()`. Centralising the accessor logic here
 * keeps a single resolver rather than per-component-type variants.
 *
 * Consumers must expose:
 *  - getLivewire() (see {@see BelongsToComponent}) — the bound Livewire instance,
 *  - getName() — the component's own name,
 * and may override:
 *  - {@see resolveStateBagRoot()} — the dot-path prefix sibling state lives under,
 *  - {@see resolveOwnState()} — the component's own current value (fields only).
 */
trait InteractsWithState
{
    /**
     * Named accessors injected into evaluated Closures.
     *
     * @return array<string, mixed>
     */
    public function getStateAccessors(): array
    {
        $livewire = $this->getLivewire();
        $bagRoot = $this->resolveStateBagRoot();
        $ownName = $this->getName();

        $resolvePath = static function (string $path) use ($bagRoot): string {
            return $bagRoot !== null && $bagRoot !== ''
                ? "{$bagRoot}.{$path}"
                : $path;
        };

        return [
            'get' => static function (?string $path = null, mixed $default = null) use ($livewire, $resolvePath, $ownName): mixed {
                if ($livewire === null) {
                    return $default;
                }

                // No path → the component's own live value (fields), always
                // current even after an earlier $set in the same closure. Layout
                // components have no own value, so an empty target yields the
                // default rather than resolving the bag root itself.
                $target = $path ?? $ownName;

                if ($target === '') {
                    return $default;
                }

                return data_get($livewire, $resolvePath($target), $default);
            },
            'set' => static function (string $path, mixed $value) use ($livewire, $resolvePath): mixed {
                if ($livewire !== null) {
                    // Route through the canonical StateContainer-aware writer: inside a
                    // table action modal the bag is a StateContainer, and a plain
                    // data_set() would silently drop the write.
                    StateContainer::writeInto($livewire, $resolvePath($path), $value);
                }

                return $value;
            },
            'state' => $this->resolveOwnState($livewire),
        ];
    }

    /**
     * The dot-path prefix that sibling state lives under: a field's bound
     * state-path prefix, or a layout's resolved absolute path.
     */
    abstract protected function resolveStateBagRoot(): ?string;

    /**
     * The component's own current value, exposed as the `$state` snapshot.
     * Components without an own value (e.g. layouts) return null.
     */
    protected function resolveOwnState(mixed $livewire): mixed
    {
        return null;
    }
}
