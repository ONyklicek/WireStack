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

    protected ?string $key = null;

    public static function make(): static
    {
        return new static;
    }

    /**
     * A stable identity for this widget within its dashboard.
     *
     * Widgets are the one component here built by `make()` with no name, which
     * is fine until something has to address *one* of them across a round trip —
     * which polling does. {@see Concerns\WithWidgets} stamps a key derived from
     * the widget's position in `getWidgets()` when none was set, so the default
     * needs no ceremony; set one explicitly where the declaration's order is
     * likely to change, since the derived key moves with it.
     *
     * Deliberately position-in-`getWidgets()` rather than position among the
     * *visible* ones: a widget hidden by a condition would otherwise renumber
     * every widget after it, and a poll would then answer with the wrong one.
     */
    public function key(string $key): static
    {
        $this->key = $key;

        return $this;
    }

    public function getKey(): ?string
    {
        return $this->key;
    }

    /** Set the widget heading. */
    public function heading(?string $heading): static
    {
        $this->heading = $heading;

        return $this;
    }

    public function getHeading(): ?string
    {
        return $this->heading;
    }

    /** Set the descriptive subheading shown under the heading. */
    public function description(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
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
