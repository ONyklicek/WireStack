<?php

declare(strict_types=1);

namespace NyonCode\WireForms\Components;

use Closure;
use Illuminate\Contracts\View\View;
use NyonCode\WireCore\Foundation\Components\Component;
use NyonCode\WireCore\Foundation\Components\LayoutComponent;
use NyonCode\WireCore\Foundation\Concerns\HasDefault;
use NyonCode\WireCore\Foundation\Support\EvaluatesClosures;
use NyonCode\WireForms\Concerns\HasFormValidation;
use NyonCode\WireForms\Concerns\HasItemLimits;
use NyonCode\WireForms\Contracts\HasValidation;

/**
 * Repeater field for HasMany / array data with inline add/remove/reorder.
 *
 * Usage:
 *   Repeater::make('contacts')
 *       ->relationship('contacts')
 *       ->schema([TextInput::make('name'), TextInput::make('email')])
 *       ->addable()
 *       ->deletable()
 *       ->reorderable()
 *       ->minItems(1)
 *       ->maxItems(10)
 */
class Repeater extends LayoutComponent implements HasValidation
{
    use EvaluatesClosures;
    use HasDefault;
    use HasFormValidation;
    use HasItemLimits;

    protected ?string $relationship = null;

    protected bool $addable = true;

    protected bool $deletable = true;

    protected bool $reorderable = false;

    protected bool $collapsible = false;

    protected bool $collapsed = false;

    protected ?string $addButtonLabel = null;

    /** @var string|Closure|null static label, or fn(array, int): ?string */
    protected string|Closure|null $itemLabel = null;

    protected bool|Closure $isDisabled = false;

    /** @var Closure|null fn(array): array — mutate item data before saving */
    protected ?Closure $mutateRelationshipDataBeforeSaveUsing = null;

    public function __construct(?string $name = null)
    {
        parent::__construct($name);
    }

    // ─── Configuration ─────────────────────────────────────────────

    /** Bind the repeater to a hasMany or belongsToMany relationship (rows saved through the model). */
    public function relationship(?string $name): static
    {
        $this->relationship = $name;

        return $this;
    }

    /** Whether the user can add rows. */
    public function addable(bool $condition = true): static
    {
        $this->addable = $condition;

        return $this;
    }

    /** Whether the user can remove rows. */
    public function deletable(bool $condition = true): static
    {
        $this->deletable = $condition;

        return $this;
    }

    /** Whether rows can be drag-reordered. */
    public function reorderable(bool $condition = true): static
    {
        $this->reorderable = $condition;

        return $this;
    }

    /** Whether rows can be collapsed. */
    public function collapsible(bool $condition = true): static
    {
        $this->collapsible = $condition;

        return $this;
    }

    /** Start rows collapsed. */
    public function collapsed(bool $condition = true): static
    {
        $this->collapsed = $condition;
        if ($condition) {
            $this->collapsible = true;
        }

        return $this;
    }

    /** Set the add-row button label. */
    public function addButtonLabel(?string $label): static
    {
        $this->addButtonLabel = $label;

        return $this;
    }

    /**
     * Give each repeater item a name shown next to its number in the header.
     *
     * Pass a static string, or a closure that receives the item's state and its
     * (zero-based) index and returns a label — e.g. derive it from a field:
     *
     *   Repeater::make('contacts')
     *       ->schema([TextInput::make('name')])
     *       ->itemLabel(fn (array $state) => $state['name'] ?? null);
     *
     * @param  string|Closure|null  $label  string | fn(array $state, int $index): ?string
     */
    public function itemLabel(string|Closure|null $label): static
    {
        $this->itemLabel = $label;

        return $this;
    }

    /** Disable the repeater (a bool or a live-state Closure). */
    public function disabled(bool|Closure $condition = true): static
    {
        $this->isDisabled = $condition;

        return $this;
    }

    /** Transform each rows data before it is saved to the relationship. */
    public function mutateRelationshipDataBeforeSaveUsing(?Closure $callback): static
    {
        $this->mutateRelationshipDataBeforeSaveUsing = $callback;

        return $this;
    }

    // ─── Getters ───────────────────────────────────────────────────

    public function getRelationship(): ?string
    {
        return $this->relationship;
    }

    public function isAddable(): bool
    {
        return $this->addable && ! $this->isDisabled();
    }

    public function isDeletable(): bool
    {
        return $this->deletable && ! $this->isDisabled();
    }

    public function isReorderable(): bool
    {
        return $this->reorderable && ! $this->isDisabled();
    }

    public function isCollapsible(): bool
    {
        return $this->collapsible;
    }

    public function isCollapsed(): bool
    {
        return $this->collapsed;
    }

    public function getAddButtonLabel(): string
    {
        return $this->addButtonLabel ?? __('Add item');
    }

    /**
     * Resolve the per-item label (see {@see itemLabel()}). Returns null when no
     * label is configured or the closure yields an empty value.
     *
     * @param  array<string, mixed>  $itemState
     */
    public function getItemLabel(array $itemState, int $index): ?string
    {
        $label = $this->itemLabel instanceof Closure
            ? ($this->itemLabel)($itemState, $index)
            : $this->itemLabel;

        return ($label === null || $label === '') ? null : (string) $label;
    }

    public function isDisabled(): bool
    {
        return $this->evaluate($this->isDisabled) === true;
    }

    public function getMutateRelationshipDataBeforeSaveUsing(): ?Closure
    {
        return $this->mutateRelationshipDataBeforeSaveUsing;
    }

    // ─── State path ────────────────────────────────────────────────

    public function getStatePath(): string
    {
        $prefix = $this->getResolvedStatePath();

        if ($prefix !== null && $prefix !== '') {
            return "{$prefix}.{$this->getName()}";
        }

        return $this->getName();
    }

    public function getWireModelAttribute(): string
    {
        return $this->getStatePath();
    }

    /**
     * Get the item state path for a specific index.
     */
    public function getItemStatePath(int $index): string
    {
        return "{$this->getStatePath()}.{$index}";
    }

    // ─── Schema for items ──────────────────────────────────────────

    /**
     * Get a cloned schema with state paths set for a specific item index.
     *
     * @return array<int, Component|LayoutComponent>
     */
    public function getItemSchema(int $index): array
    {
        $itemPath = $this->getItemStatePath($index);
        $livewire = $this->getLivewire();
        $components = [];

        foreach ($this->schema as $component) {
            $clone = clone $component;
            if ($clone instanceof Component) {
                $clone->statePath($itemPath);
                // Re-bind the owning Livewire instance so per-item reactive
                // closures (visibleWhen, afterStateUpdated $get/$set) resolve
                // against live state even when the template child was never
                // bound (e.g. the repeater is rendered outside a full
                // form-level prepare, as in action modal hosts).
                if ($livewire !== null) {
                    $clone->livewire($livewire);
                }
            } elseif ($clone instanceof LayoutComponent) {
                // Re-prepare the (deep-)cloned layout under the item prefix: this
                // recomputes its resolved path (the clone carries a stale one from
                // the form-level prepare) and cascades the prefix — and the
                // Livewire binding — into descendant fields, which a bare
                // statePath() on the layout never reaches.
                $clone->prepareChildren($itemPath, livewire: $livewire);
            }
            $components[] = $clone;
        }

        return $components;
    }

    /**
     * The repeater binds a whole subtree: report its own path plus a wildcard
     * covering every per-item child, so error-bag keys like
     * `data.contacts.0.email` map back to this layout.
     *
     * @return array<int, string>
     */
    public function getDescendantFieldStatePaths(): array
    {
        $path = $this->getStatePath();

        return [$path, "{$path}.*"];
    }

    // ─── Validation ────────────────────────────────────────────────

    /**
     * @return array<int, mixed>
     */
    public function getRules(): array
    {
        $rules = [];

        if ($this->minItems !== null) {
            $rules[] = 'min:'.$this->minItems;
        }

        if ($this->maxItems !== null) {
            $rules[] = 'max:'.$this->maxItems;
        }

        if (! empty($rules)) {
            array_unshift($rules, 'array');
        }

        return $rules;
    }

    public function getValidationAttribute(): ?string
    {
        return $this->getLabel() ?? $this->getName();
    }

    /**
     * Validation rules for the repeater container itself (array + min/max + required),
     * keyed at the repeater's own state path. Used by the form validation resolver.
     *
     * @return array<int, mixed>
     */
    public function getContainerValidationRules(): array
    {
        $rules = $this->getRules();

        if ($this->isRequired()) {
            if (! in_array('array', $rules, true)) {
                array_unshift($rules, 'array');
            }
            if (! in_array('required', $rules, true)) {
                array_unshift($rules, 'required');
            }
        }

        return $rules;
    }

    /**
     * Per-item child validation rules keyed by child field name, e.g.
     * ['label' => ['required'], 'email' => ['email']]. The resolver expands
     * these to wildcard paths like "data.contacts.*.label".
     *
     * @return array<string, array<int, mixed>>
     */
    public function getItemValidationRules(): array
    {
        return $this->collectItemValidationRules($this->schema);
    }

    /**
     * Recursively collect per-item field rules, descending into nested layout
     * components (Grid, Section, Fieldset, …) so fields wrapped in a layout are
     * validated the same as direct children.
     *
     * @param  array<int, Component|LayoutComponent>  $components
     * @return array<string, array<int, mixed>>
     */
    private function collectItemValidationRules(array $components): array
    {
        $rules = [];

        foreach ($components as $component) {
            if ($component instanceof LayoutComponent) {
                $rules = array_merge($rules, $this->collectItemValidationRules($component->getSchema()));

                continue;
            }

            if ($component instanceof HasValidation) {
                $childRules = $component->getValidationRules();
                // Fall back to ['nullable'] like the top-level resolver does: a
                // child with no rules still needs a wildcard entry, or Livewire's
                // validate() omits it from the validated data and its value is
                // silently dropped before it reaches the relationship save.
                $rules[$component->getName()] = $childRules !== [] ? $childRules : ['nullable'];
            }
        }

        return $rules;
    }

    // ─── Rendering ─────────────────────────────────────────────────

    protected function viewName(): string
    {
        return 'wire-forms::components.repeater';
    }

    public function render(): View
    {
        return view($this->viewName(), ['field' => $this]);
    }
}
