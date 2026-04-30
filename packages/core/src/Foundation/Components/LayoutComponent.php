<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Components;

use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\View\View;
use NyonCode\WireCore\Foundation\Concerns;
use NyonCode\WireCore\Foundation\Support\EvaluatesClosures;

/**
 * Base class for layout components (Section, Grid, Fieldset, Tabs).
 *
 * Layout components contain child components in their schema
 * and propagate state paths to children.
 *
 * @phpstan-consistent-constructor
 */
abstract class LayoutComponent implements Htmlable
{
    use Concerns\HasColumnSpan;
    use Concerns\HasLabel;
    use Concerns\HasVisibility;
    use EvaluatesClosures;

    /** @var array<int, Component|LayoutComponent> */
    protected array $schema = [];

    protected ?string $statePath = null;

    protected ?string $name;

    public function __construct(?string $name = null)
    {
        $this->name = $name;
    }

    public static function make(?string $name = null): static
    {
        return new static($name);
    }

    public function getName(): string
    {
        return $this->name ?? '';
    }

    /**
     * @param  array<int, Component|LayoutComponent>  $components
     */
    public function schema(array $components): static
    {
        $this->schema = $components;

        return $this;
    }

    /**
     * @return array<int, Component|LayoutComponent>
     */
    public function getSchema(): array
    {
        return $this->schema;
    }

    public function statePath(?string $path): static
    {
        $this->statePath = $path;

        return $this;
    }

    /**
     * Propagate state path to all child components.
     */
    public function prepareChildren(string $parentPath = ''): void
    {
        $basePath = $this->statePath
            ? ($parentPath ? "{$parentPath}.{$this->statePath}" : $this->statePath)
            : $parentPath;

        foreach ($this->schema as $component) {
            if ($component instanceof self) {
                $component->prepareChildren($basePath);
            } elseif ($component instanceof Component && $basePath) {
                $component->statePath($basePath);
            }
        }
    }

    /**
     * Get the view name for rendering this layout.
     */
    abstract protected function viewName(): string;

    public function render(): View
    {
        return view($this->viewName(), ['layout' => $this]);
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
