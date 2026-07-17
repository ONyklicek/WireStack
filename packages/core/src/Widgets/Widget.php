<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Widgets;

use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\View\View;
use NyonCode\WireCore\Foundation\Concerns\CanBeDisabled;
use NyonCode\WireCore\Foundation\Concerns\HasColumnSpan;
use NyonCode\WireCore\Foundation\Concerns\HasExtraAttributes;
use NyonCode\WireCore\Foundation\Concerns\HasVisibility;
use NyonCode\WireCore\Foundation\Concerns\InteractsWithStateConditions;
use NyonCode\WireCore\Foundation\Support\EvaluatesClosures;
use NyonCode\WireCore\Widgets\Concerns\HasPolling;

/**
 * Base widget class for dashboard components.
 *
 * @phpstan-consistent-constructor
 */
abstract class Widget implements Htmlable
{
    use CanBeDisabled;
    use EvaluatesClosures;
    use HasColumnSpan;
    use HasExtraAttributes;
    use HasPolling;
    use HasVisibility;
    use InteractsWithStateConditions;

    protected ?string $heading = null;

    protected ?string $description = null;

    protected bool $lazy = false;

    public static function make(): static
    {
        return new static;
    }

    public function heading(?string $heading): static
    {
        $this->heading = $heading;

        return $this;
    }

    public function getHeading(): ?string
    {
        return $this->heading;
    }

    public function description(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function lazy(bool $lazy = true): static
    {
        $this->lazy = $lazy;

        return $this;
    }

    public function isLazy(): bool
    {
        return $this->lazy;
    }

    abstract protected function viewName(): string;

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return [];
    }

    public function render(): View
    {
        return view($this->viewName(), array_merge(
            ['widget' => $this],
            $this->getViewData(),
        ));
    }

    public function toHtml(): string
    {
        return $this->render()->render();
    }

    public function __toString(): string
    {
        return $this->toHtml();
    }
}
