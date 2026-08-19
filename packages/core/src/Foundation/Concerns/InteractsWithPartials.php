<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Concerns;

use Closure;
use Illuminate\Contracts\Support\Htmlable;
use NyonCode\WireCore\Foundation\Support\PartialRenderHook;

use function Livewire\store;

/**
 * Render a named region of this component instead of the whole of it.
 *
 * The one win Livewire 4 islands cannot reach. An island's name is re-evaluated
 * inside its own compiled view file, which never sees a loop variable, so there
 * is no island per record (see `IslandSemanticsTest`). A partial is only an HTML
 * attribute — `wire:partial="row-42"` — chosen by the *server* at write time, so
 * it costs nothing per row, registers nothing, and adds nothing to the snapshot.
 *
 * Measured on a 25-column, 20-row page with 10 editable columns
 * (`IslandDecompositionBenchmarkTest`): an inline cell save costs 49.3 ms and
 * 556 kB as a targeted island render, and 3.2 ms and 26 kB as one row.
 *
 * The shape is Filament's, and the parts that differ are deliberate:
 *
 *  - **no container binding.** They swap `DataStore::class` app-wide to intercept
 *    the *read* of `skipRender`. {@see PartialRenderHook} writes it instead, from
 *    a hook closure that runs after the method and before the render that reads
 *    it — proved by `SkipRenderFromHookTest`. So two packages doing this in one
 *    app cannot disable each other;
 *  - **its own key and effect name** (`wirePartials`), so a Filament install in
 *    the same app neither reads ours nor we theirs.
 *
 * Queue as many as one write touches — a row and its mobile card are two
 * renderings of one record and both have to move. **Every call in the request
 * must queue at least one**, or the hook falls back to the full render: a
 * partial response that covered only half of what changed is the staleness this
 * whole plan exists to avoid.
 */
trait InteractsWithPartials
{
    /**
     * Queue a named region to be rendered and sent instead of the full view.
     *
     * @param  Closure(): (string|Htmlable)  $render
     */
    public function renderPartial(string $name, Closure $render): void
    {
        $partials = store($this)->get(PartialRenderHook::STORE_KEY, []);

        $partials[$name] = $render;

        store($this)->set(PartialRenderHook::STORE_KEY, $partials);
    }

    /**
     * Declare that the queued regions cover every property update in this request.
     *
     * A property update forces the full render by default, and that default is
     * right: `wire:model` can change a filter, a page, a sort — anything the view
     * shows. Only a component that has *looked* at what its own update changed can
     * say otherwise, and only about itself.
     *
     * **Say it by comparing, not by reasoning.** wire-forms renders its fields and
     * compares each one's markup against what it last sent, exactly as
     * `WithTable::queueChangedRowPartials()` compares a row against a hash of the
     * record it last sent. That catches what a dependency analysis would miss: a
     * sibling whose options, label or helper text read the updated field's state
     * has different markup, and different markup is all this has to notice. The
     * set changing — a field appearing or disappearing — is a shape change no
     * region can express, and means a full render.
     *
     * Calling this without having done that comparison is how a stale value ends
     * up in front of somebody. It is not a performance switch.
     */
    public function coverUpdatesWithPartials(): void
    {
        store($this)->set(PartialRenderHook::UPDATES_COVERED_KEY, true);
    }
}
