<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Components;

use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Traits\Conditionable;
use NyonCode\WireCore\Foundation\Concerns;
use NyonCode\WireCore\Foundation\Support\EvaluatesClosures;

/**
 * Abstract base class for all Wire form field components.
 *
 * Provides shared functionality: name, label, state management,
 * visibility, validation, extra attributes, size, and rendering.
 *
 * @phpstan-consistent-constructor
 */
abstract class Component implements Htmlable
{
    use Concerns\BelongsToComponent;
    use Concerns\CanBeDisabled;
    use Concerns\HasColumnSpan;
    use Concerns\HasDefault;
    use Concerns\HasExtraAttributes;
    use Concerns\HasHelperText;
    use Concerns\HasHint;
    use Concerns\HasId;
    use Concerns\HasLabel;
    use Concerns\HasName;
    use Concerns\HasSize;
    use Concerns\HasState;
    use Concerns\HasViewRenderCache;
    use Concerns\HasVisibility;
    use Concerns\InteractsWithStateConditions;
    use Conditionable;
    use EvaluatesClosures;

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    public static function make(string $name): static
    {
        return new static($name);
    }

    /**
     * Get the view name for rendering this component.
     */
    abstract protected function viewName(): string;

    public function render(): View
    {
        return view($this->viewName(), ['field' => $this]);
    }

    public function toHtml(): string
    {
        return $this->renderCachedHtml();
    }

    public function __toString(): string
    {
        return $this->toHtml();
    }
}
