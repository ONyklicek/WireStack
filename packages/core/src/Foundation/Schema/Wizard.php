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
