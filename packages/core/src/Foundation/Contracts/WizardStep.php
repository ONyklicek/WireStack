<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Contracts;

/**
 * A step a wizard can serialize into its frontend config.
 *
 * This exists to break one specific edge. `Modals\Wizard` type-hinted and
 * `instanceof`-checked `Actions\ModalStep` while `Actions\Concerns\HasModal`
 * reached back into `Modals\` — a cycle between two modules, and the one shape
 * that cannot survive becoming two packages, since composer cannot release a
 * pair that requires each other at `self.version`.
 *
 * The wizard never needed the class. It needed one method, called once, on
 * whatever the caller put in `steps()`.
 *
 * Note what this is NOT: `Foundation\View\Step` and `Foundation\View\Wizard`
 * are Blade primitives for rendering `<x-wire::wizard>`, and they deliberately
 * do not implement this. Rendering a step and describing one are different
 * jobs, and collapsing them would be the parallel vocabulary CLAUDE.md warns
 * about rather than the single one it asks for.
 */
interface WizardStep
{
    /**
     * Serialize this step for the frontend.
     *
     * `$context` is the record or payload the surrounding action was invoked
     * with; steps that build their schema from a closure need it, and steps
     * with a static schema ignore it.
     *
     * @return array<string, mixed>
     */
    public function toArray(mixed $context = null): array;
}
