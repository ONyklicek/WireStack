<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Actions\Support;

use Livewire\Component;

use function Livewire\store;

/**
 * A raised halt renders the host — whatever island the call that raised it
 * targeted.
 *
 * The halt modal is drawn by the host's own view, outside every island. A call
 * fired from inside an island targets that island implicitly (Livewire 4), and
 * a targeted call skips the component render and answers with the island
 * alone. A table's row buttons sit in its `data-region` island, so a row action
 * without a modal of its own that halted set the halt state and sent nothing
 * that draws it: the click looked dead, the next full render opened a modal
 * nobody asked for, and a PHP test — which renders the whole component —
 * passed regardless.
 *
 * `forceRender()` is Livewire's own switch for that: it makes every later
 * `skipRender()` of the request a no-op, the implicit island one included,
 * which runs after the call returns. A skip granted earlier in the request is
 * taken back too. A halt is rare and waits on the user, so the full render it
 * costs is the right trade.
 */
final class HaltRender
{
    public static function force(object $host): void
    {
        if (! $host instanceof Component) {
            return;
        }

        $host->forceRender();
        store($host)->set('skipRender', false);
    }
}
