<?php

declare(strict_types=1);

namespace NyonCode\WireForms\Concerns;

use NyonCode\WireCore\Core\State\StateContainer;
use NyonCode\WireCore\Foundation\Components\Component;
use NyonCode\WireCore\Foundation\Contracts\HasStateAccessors;

/**
 * Supplies Filament-style reactive state accessors to evaluated Closures.
 *
 * Implements the Core {@see HasStateAccessors}
 * contract so that dynamic field callbacks — `visible()`, `disabled()`,
 * `hidden()`, `afterStateUpdated()` — can read sibling state via `$get`, write
 * via `$set`, and read their own value, all resolved against the bound Livewire
 * component's live state bag.
 *
 * Two ways to read the field's own value:
 *  - `$get()` (no path) resolves the field's own value live, every call — use it
 *    when later reads must reflect a `$set` made earlier in the same closure.
 *  - `$state` is a snapshot taken when the closure is invoked (Filament-style
 *    convenience); it does not reflect a `$set` performed inside that same call.
 *
 * Requires the host to expose getLivewire() (BelongsToComponent), getStatePath()
 * and a $statePath bag prefix (HasState).
 *
 * @phpstan-require-extends Component
 */
trait InteractsWithFormState
{
    /**
     * @return array<string, mixed>
     */
    public function getStateAccessors(): array
    {
        $livewire = $this->getLivewire();
        $bagRoot = $this->statePath;
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

                // No path → the field's own live value, always current (even after
                // an earlier $set in the same closure), unlike the $state snapshot.
                return data_get($livewire, $resolvePath($path ?? $ownName), $default);
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
            'state' => $livewire !== null
                ? data_get($livewire, $this->getStatePath())
                : null,
        ];
    }
}
