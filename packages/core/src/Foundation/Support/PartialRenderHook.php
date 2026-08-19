<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Support;

use Closure;
use Illuminate\Contracts\Support\Htmlable;
use Livewire\ComponentHook;
use Livewire\Mechanisms\HandleComponents\ComponentContext;
use NyonCode\WireCore\Foundation\Concerns\InteractsWithPartials;

use function Livewire\store;

/**
 * Renders the regions a write asked for, and skips the view when they cover it.
 *
 * The server half of {@see InteractsWithPartials}.
 * Two jobs, and the first is the one that decides whether any of this is safe:
 *
 * **Coverage.** A partial response is correct only when *everything* the request
 * changed is inside a queued region. So the default is to render normally, and
 * the view is skipped only if every call queued at least one partial and no
 * property update happened that nothing covered. A property update can change
 * anything the view shows — a filter, a page, a sort — so one is enough to force
 * the full render unless the component has *looked* at what changed and queued
 * regions for all of it ({@see InteractsWithPartials::coverUpdatesWithPartials()},
 * which wire-forms uses to answer a field update with the fields that moved).
 * Erring this way costs a render; erring the other way puts a stale number in
 * front of somebody.
 *
 * **Emission.** The rendered regions ride out as a `wirePartials` effect, which
 * is a plain {@see ComponentContext::addEffect()} call — no override of anything.
 * The name is ours rather than Filament's `partials` so that an app running both
 * has each applier reading only its own.
 *
 * `skipRender` is only ever set to *true* here, never back to false: another
 * concern may have skipped the render for its own reasons
 * (`WithTable::skipTableRenderAfterWrite()`), and this is not the place to
 * overrule it.
 */
final class PartialRenderHook extends ComponentHook
{
    public const STORE_KEY = 'wirePartials';

    private const FORCE_KEY = 'wirePartialsForceRender';

    /** An update happened; whether anything covers it is a separate question. */
    public const UPDATED_KEY = 'wirePartialsUpdated';

    /** The component said the regions it queued cover every update in this request. */
    public const UPDATES_COVERED_KEY = 'wirePartialsUpdatesCovered';

    /**
     * Livewire's own synthetic commit, which `wire:model.live` sends alongside
     * the updates it is committing. It queues nothing and asks for nothing, so
     * counting it as "a call that rendered nothing partial" would force the full
     * render on the one path a form's partials exist for.
     */
    private const SYNTHETIC_COMMIT = '$commit';

    /**
     * A property update can change anything, so nothing partial can cover it.
     *
     * Types stay in the docblock: the parent declares these untyped, and PHP
     * treats adding a native type in a child as narrowing.
     *
     * @param  mixed  $propertyName
     * @param  mixed  $fullPath
     * @param  mixed  $newValue
     * @return Closure(): void
     */
    public function update($propertyName, $fullPath, $newValue)
    {
        store($this->component)->set(self::UPDATED_KEY, true);

        return function () {};
    }

    /**
     * @param  mixed  $method
     * @param  mixed  $params
     * @param  mixed  $returnEarly
     * @param  mixed  $context
     * @return Closure(): void
     */
    public function call($method, $params, $returnEarly, $context)
    {
        $before = count($this->queued());

        $synthetic = $method === self::SYNTHETIC_COMMIT;

        return function () use ($before, $synthetic): void {
            if (! $synthetic && count($this->queued()) <= $before) {
                // This call rendered nothing partial, so the view has to run.
                store($this->component)->set(self::FORCE_KEY, true);
            }

            if ($this->covered()) {
                // Set before the render that reads it — the whole reason this
                // needs no DataStore override.
                store($this->component)->set('skipRender', true);
            }
        };
    }

    /**
     * @param  mixed  $context
     */
    public function dehydrate($context): void
    {
        if (! $context instanceof ComponentContext || ! $this->covered()) {
            return;
        }

        $rendered = [];

        foreach ($this->queued() as $name => $render) {
            // A partial is a view rendered outside Livewire's own render pass, so
            // it needs the component in view scope for the same reason an island
            // body does — every modal and field here builds its view from PHP. The
            // whole callback runs inside it, not just the cast at the end: a
            // closure that renders eagerly (`view(...)->render()`) would otherwise
            // do its work with nothing shared.
            //
            // The NARROW scope, deliberately. `asLivewireRender()` would also
            // switch on morph markers, and the table spent real work removing
            // those from its rows — 848–1035 B of them per row. A caller whose
            // markup has to carry them (wire-forms, whose field views compile
            // `@if`s the full render marks up) opens the wider scope around its
            // own render, where it is the one region that needs it.
            $rendered[$name] = IslandViewScope::within(
                $this->component,
                function () use ($render): string {
                    $html = $render();

                    return $html instanceof Htmlable ? $html->toHtml() : (string) $html;
                },
            );
        }

        $context->addEffect(self::STORE_KEY, $rendered);
    }

    /** @return array<string, Closure> */
    private function queued(): array
    {
        return store($this->component)->get(self::STORE_KEY, []);
    }

    private function covered(): bool
    {
        if ($this->queued() === []) {
            return false;
        }

        if (store($this->component)->get(self::FORCE_KEY, false)) {
            return false;
        }

        // A property update can change anything the view shows, so it still
        // forces the render by default. The exception is a component that has
        // looked at what changed and queued regions for all of it — see
        // `InteractsWithPartials::coverUpdatesWithPartials()`. Asked here rather
        // than decided in `update()` so the answer does not depend on which hook
        // Livewire happens to run first.
        if (store($this->component)->get(self::UPDATED_KEY, false)) {
            return (bool) store($this->component)->get(self::UPDATES_COVERED_KEY, false);
        }

        return true;
    }
}
