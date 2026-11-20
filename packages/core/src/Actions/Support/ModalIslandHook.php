<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Actions\Support;

use Closure;
use Livewire\ComponentHook;

use function Livewire\store;

/**
 * A call that opens a modal answers with the modal, whatever island it targeted.
 *
 * The host draws its action modals in an `action-modals` island, and a call fired
 * from inside another island targets that one implicitly (Livewire 4): it skips
 * the component render and answers with that island alone. A button that opens a
 * modal says `wire:island="action-modals"` and is fine. A button whose action has
 * no modal of its own and opens one from its callback — a row action switching to
 * the editor for the record's kind, `$this->openActionModal(...)` or
 * `mountAction(...)` — is drawn without it, inside the table's `data-region`. Its
 * frame was mounted, and nothing in the response drew it: the click looked dead,
 * the modal appeared with whatever request came next, and a PHP test — which
 * renders the whole component — passed regardless.
 *
 * So after such a call this queues the modal island beside the one it targeted.
 * Only then: an untargeted call renders the whole component, the modal included,
 * and a call that targeted the modal island already renders it. Unlike a halt
 * ({@see HaltRender}), this does not force the full render — the targeted island
 * still answers for whatever the call wrote, and the modal island is the one
 * thing missing.
 *
 * Which calls mounted a frame is counted rather than inferred: every host's
 * `pushActionFrame()` reports it through {@see frameMounted()}.
 */
final class ModalIslandHook extends ComponentHook
{
    public const ISLAND = 'action-modals';

    private const STORE_KEY = 'wireActionFramesMounted';

    /** Called by every `pushActionFrame()`: this request mounted a modal frame. */
    public static function frameMounted(object $host): void
    {
        store($host)->set(self::STORE_KEY, (int) store($host)->get(self::STORE_KEY, 0) + 1);
    }

    /**
     * Types stay in the docblock: the parent declares these untyped, and PHP
     * treats adding a native type in a child as narrowing.
     *
     * @param  mixed  $method
     * @param  mixed  $params
     * @param  mixed  $returnEarly
     * @param  mixed  $metadata
     * @return Closure(): void|null
     */
    public function call($method, $params, $returnEarly, $metadata)
    {
        $island = is_array($metadata) ? ($metadata['island']['name'] ?? null) : null;

        // Untargeted, or already aimed at the modals: the render draws them.
        if (! is_string($island) || $island === self::ISLAND) {
            return null;
        }

        $before = $this->mounted();

        return function () use ($before): void {
            if ($this->mounted() > $before && $this->hasModalIsland()) {
                $this->component->triggerImplicitIslandRender(name: self::ISLAND);
            }
        };
    }

    private function mounted(): int
    {
        return (int) store($this->component)->get(self::STORE_KEY, 0);
    }

    /** Only an island this component has rendered before can be sent alone. */
    private function hasModalIsland(): bool
    {
        foreach ($this->component->getIslands() as $island) {
            if (($island['name'] ?? null) === self::ISLAND && ! empty($island['token'])) {
                return true;
            }
        }

        return false;
    }
}
