<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Components;

use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\View\View;
use NyonCode\WireCore\Foundation\Concerns;
use NyonCode\WireCore\Foundation\Contracts\HasStateAccessors;
use NyonCode\WireCore\Foundation\Support\EvaluatesClosures;

/**
 * Base class for layout components (Section, Grid, Fieldset, Tabs).
 *
 * Layout components contain child components in their schema
 * and propagate state paths to children.
 *
 * @phpstan-consistent-constructor
 */
abstract class LayoutComponent implements HasStateAccessors, Htmlable
{
    use Concerns\BelongsToComponent;
    use Concerns\CanBeDisabled;
    use Concerns\HasColumnSpan;
    use Concerns\HasLabel;
    use Concerns\HasVisibility;
    use Concerns\InteractsWithState;
    use Concerns\InteractsWithStateConditions;
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
     * Set the child components this layout contains.
     *
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

    /** Set the state-path prefix this layout propagates to its children. */
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
     * Sibling state for layout closures (`visible(fn ($get) => ...)`) resolves
     * against the layout's resolved absolute path, so children of a Repeater or
     * nested layout read the correctly-prefixed bag.
     */
    protected function resolveStateBagRoot(): ?string
    {
        return $this->getResolvedStatePath();
    }

    /**
     * Propagate state path (and optionally live mode) to all child components.
     *
     * The owning Livewire instance is forwarded too so child components can
     * resolve reactive state inside dynamic callbacks (e.g. visible()).
     */
    public function prepareChildren(string $parentPath = '', bool $live = false, mixed $livewire = null, bool $disabled = false): void
    {
        // Bind the owning Livewire instance to this layout too (not just its
        // children) so layout-level reactive closures resolve $get/$set against
        // live state. Covers both the top-level layout (prepared by the form
        // runtime) and nested layouts (prepared recursively below).
        if ($livewire !== null) {
            $this->livewire($livewire);
        }

        // A disabled form must disable everything inside its layouts too, not just
        // its direct fields — mark the layout and cascade to descendants.
        if ($disabled) {
            $this->disabled();
        }

        $basePath = $this->statePath
            ? ($parentPath ? "{$parentPath}.{$this->statePath}" : $this->statePath)
            : $parentPath;

        // Record the resolved absolute path so stateful layouts (Repeater)
        // can report a correctly-prefixed getStatePath().
        $this->resolvedStatePath = $basePath !== '' ? $basePath : null;

        foreach ($this->schema as $component) {
            if ($component instanceof self) {
                $component->prepareChildren($basePath, $live, $livewire, $disabled);
            } elseif ($component instanceof Component) {
                if ($basePath) {
                    $component->statePath($basePath);
                }
                if ($live && method_exists($component, 'live')) {
                    $component->live();
                }
                if ($disabled) {
                    $component->disabled();
                }
                if ($livewire !== null) {
                    $component->livewire($livewire);
                }
            }
        }
    }

    /**
     * Absolute state paths of every field this layout (transitively) contains.
     *
     * Used by step-scoped concerns — wizard per-step validation and the
     * "jump to the first errored step" behavior — to map a layout subtree to
     * the error-bag keys it owns. Stateful layouts that bind whole subtrees
     * (e.g. a forms Repeater) override this to report their own path plus a
     * `{path}.*` wildcard covering per-item children.
     *
     * @return array<int, string>
     */
    public function getDescendantFieldStatePaths(): array
    {
        $paths = [];

        foreach ($this->schema as $component) {
            if ($component instanceof self) {
                $paths = array_merge($paths, $component->getDescendantFieldStatePaths());
            } elseif ($component instanceof Component) {
                $path = $component->getStatePath();
                if ($path !== '') {
                    $paths[] = $path;
                }
            }
        }

        return array_values(array_unique($paths));
    }

    /**
     * Deep-clone the child schema. A shallow clone would share the child
     * component instances with the original (and with every other clone), so a
     * per-clone statePath — e.g. a Repeater item prefix — would leak across all
     * copies, leaving every row bound to the last-set path.
     */
    public function __clone(): void
    {
        $this->schema = array_map(
            static fn (Component|LayoutComponent $component): Component|LayoutComponent => clone $component,
            $this->schema,
        );
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
