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
 * property update happened at all. A property update can change anything the
 * view shows — a filter, a page, a sort — so one is enough to force the full
 * render, whatever else the request also did. Erring this way costs a render;
 * erring the other way puts a stale number in front of somebody.
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
        store($this->component)->set(self::FORCE_KEY, true);

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

        return function () use ($before): void {
            if (count($this->queued()) <= $before) {
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
            $html = $render();

            // A partial is a view rendered outside Livewire's own render pass, so
            // it needs the component in view scope for the same reason an island
            // body does — every modal and field here builds its view from PHP.
            $rendered[$name] = IslandViewScope::within(
                $this->component,
                fn (): string => $html instanceof Htmlable ? $html->toHtml() : (string) $html,
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
        return $this->queued() !== []
            && ! store($this->component)->get(self::FORCE_KEY, false);
    }
}
