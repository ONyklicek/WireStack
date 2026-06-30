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

    /**
     * Absolute state path resolved during prepareChildren() (parent prefix + own
     * statePath). Stateful layouts such as Repeater rely on this to report the
     * correct getStatePath(); kept separate from $statePath so re-running
     * prepareChildren() never double-applies the parent prefix.
     */
    protected ?string $resolvedStatePath = null;

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
     * Absolute state path prefix for this layout, resolved during
     * prepareChildren(). Falls back to the raw statePath before preparation.
     */
    public function getResolvedStatePath(): ?string
    {
        return $this->resolvedStatePath ?? $this->statePath;
    }

    /**
     * Propagate state path (and optionally live mode) to all child components.
     *
     * The owning Livewire instance is forwarded too so child components can
     * resolve reactive state inside dynamic callbacks (e.g. visible()).
     */
    public function prepareChildren(string $parentPath = '', bool $live = false, mixed $livewire = null): void
    {
        $basePath = $this->statePath
            ? ($parentPath ? "{$parentPath}.{$this->statePath}" : $this->statePath)
            : $parentPath;

        // Record the resolved absolute path so stateful layouts (Repeater)
        // can report a correctly-prefixed getStatePath().
        $this->resolvedStatePath = $basePath !== '' ? $basePath : null;

        foreach ($this->schema as $component) {
            if ($component instanceof self) {
                $component->prepareChildren($basePath, $live, $livewire);
            } elseif ($component instanceof Component) {
                if ($basePath) {
                    $component->statePath($basePath);
                }
                if ($live && method_exists($component, 'live')) {
                    $component->live();
                }
                if ($livewire !== null) {
                    $component->livewire($livewire);
                }
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
