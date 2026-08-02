<?php

declare(strict_types=1);

namespace NyonCode\WireForms\Concerns;

use NyonCode\WireCore\Foundation\Components\Component;
use NyonCode\WireCore\Foundation\Components\LayoutComponent;

/**
 * Re-binds a template schema to one item of a repeating field.
 *
 * Both repeating fields (Repeater, Builder) render the same template schema once
 * per item under a per-item state path. Getting that re-binding right is subtle
 * — a bare `statePath()` never reaches a layout's descendants, and a clone
 * carries a stale resolved path — so it is owned here once rather than being
 * re-derived per field.
 */
trait ClonesItemSchema
{
    /**
     * Clone a template schema and bind every component to the given item path.
     *
     * @param  array<int, Component|LayoutComponent>  $schema
     * @return array<int, Component|LayoutComponent>
     */
    protected function cloneSchemaForItem(array $schema, string $itemPath): array
    {
        $livewire = $this->getLivewire();
        $components = [];

        foreach ($schema as $component) {
            $clone = clone $component;

            if ($clone instanceof Component) {
                $clone->statePath($itemPath);
                // Re-bind the owning Livewire instance so per-item reactive
                // closures (visibleWhen, afterStateUpdated $get/$set) resolve
                // against live state even when the template child was never
                // bound (e.g. the field is rendered outside a full form-level
                // prepare, as in action modal hosts).
                if ($livewire !== null) {
                    $clone->livewire($livewire);
                }
            } elseif ($clone instanceof LayoutComponent) {
                // Re-prepare the (deep-)cloned layout under the item prefix: this
                // recomputes its resolved path (the clone carries a stale one from
                // the form-level prepare) and cascades the prefix — and the
                // Livewire binding — into descendant fields, which a bare
                // statePath() on the layout never reaches.
                $clone->prepareChildren($itemPath, livewire: $livewire);
            }

            $components[] = $clone;
        }

        return $components;
    }
}
