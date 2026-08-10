<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Schema;

use NyonCode\WireCore\Foundation\Components\LayoutComponent;

/**
 * Multi-step wizard layout: a step indicator over a set of {@see Step} panels,
 * navigated client-side with Previous / Next controls. All steps stay in the DOM
 * so nested fields flatten and validate together on submit regardless of the
 * active step.
 *
 * A standalone form counterpart to the action-modal wizard (HasModal::steps);
 * here the steps live directly in a form schema rather than a modal.
 */
class Wizard extends LayoutComponent
{
    protected int $activeStep = 0;

    protected bool $skippable = false;

    protected bool $navigation = true;

    /**
     * Zero-based index of the step shown first.
     */
    public function activeStep(int $index): static
    {
        $this->activeStep = $index;

        return $this;
    }

    /**
     * Allow jumping to any step from the indicator, not just the adjacent one.
     */
    public function skippable(bool $condition = true): static
    {
        $this->skippable = $condition;

        return $this;
    }

    /**
     * Render the wizard without its own Previous / Next row.
     *
     * For a surface that drives the steps from its own chrome — a modal footer,
     * a page toolbar — so the two navigations do not sit on screen at once. The
     * wizard still owns the step state: an external driver mirrors it from the
     * `wire-wizard-state` window event and steps it with `wire-wizard-navigate`,
     * both scoped by the wizard's name. Name the wizard when more than one can be
     * on screen at a time, or the two share an (empty) scope.
     */
    public function navigation(bool $condition = true): static
    {
        $this->navigation = $condition;

        return $this;
    }

    public function hasNavigation(): bool
    {
        return $this->navigation;
    }

    public function getActiveStep(): int
    {
        return $this->activeStep;
    }

    public function isSkippable(): bool
    {
        return $this->skippable;
    }

    /**
     * The visible steps, re-indexed so the active-step index and rendered order
     * stay aligned when a step is hidden.
     *
     * @return array<int, Step>
     */
    public function getSteps(): array
    {
        return array_values(array_filter(
            $this->getSchema(),
            static fn ($component): bool => $component instanceof Step && $component->isVisible(),
        ));
    }

    protected function viewName(): string
    {
        return 'wire-core::schema.wizard';
    }
}
