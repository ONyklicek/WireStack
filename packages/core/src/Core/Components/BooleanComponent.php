<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Components;

/**
 * Shared boolean-specific behavior for boolean columns and toggles.
 */
class BooleanComponent extends DataComponent
{
    protected ?string $trueLabel = null;

    protected ?string $falseLabel = null;

    public function trueLabel(string $label): static
    {
        $this->trueLabel = $label;

        return $this;
    }

    public function getTrueLabel(): ?string
    {
        return $this->trueLabel;
    }

    public function falseLabel(string $label): static
    {
        $this->falseLabel = $label;

        return $this;
    }

    public function getFalseLabel(): ?string
    {
        return $this->falseLabel;
    }
}
