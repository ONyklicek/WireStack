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

    protected ?string $relationship = null;

    protected bool $addable = true;

    protected bool $deletable = true;

    protected bool $reorderable = false;

    protected bool $collapsible = false;

    protected bool $collapsed = false;

    protected ?int $minItems = null;

    protected ?int $maxItems = null;

    protected ?string $addButtonLabel = null;

    protected bool|Closure $isDisabled = false;

    /** @var Closure|null fn(array): array — mutate item data before saving */
    protected ?Closure $mutateRelationshipDataBeforeSaveUsing = null;

    public function __construct(?string $name = null)
    {
        parent::__construct($name);
    }

    // ─── Configuration ─────────────────────────────────────────────

    public function relationship(?string $name): static
    {
        $this->relationship = $name;

        return $this;
    }

    public function addable(bool $condition = true): static
    {
        $this->addable = $condition;

        return $this;
    }

    public function deletable(bool $condition = true): static
    {
        $this->deletable = $condition;

        return $this;
    }

    public function reorderable(bool $condition = true): static
    {
        $this->reorderable = $condition;

        return $this;
    }

    public function collapsible(bool $condition = true): static
    {
        $this->collapsible = $condition;

        return $this;
    }

    public function collapsed(bool $condition = true): static
    {
        $this->collapsed = $condition;
        if ($condition) {
            $this->collapsible = true;
        }

        return $this;
    }

    public function minItems(?int $count): static
    {
        $this->minItems = $count;

        return $this;
    }

    public function maxItems(?int $count): static
    {
        $this->maxItems = $count;

        return $this;
    }

    public function addButtonLabel(?string $label): static
    {
        $this->addButtonLabel = $label;

        return $this;
    }

    public function disabled(bool|Closure $condition = true): static
    {
        $this->isDisabled = $condition;

        return $this;
    }

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

    public function getMinItems(): ?int
    {
        return $this->minItems;
    }

    public function getMaxItems(): ?int
    {
        return $this->maxItems;
    }

    public function getAddButtonLabel(): string
    {
        return $this->addButtonLabel ?? __('Add item');
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
        $components = [];

        foreach ($this->schema as $component) {
            $clone = clone $component;
            if ($clone instanceof Component) {
                $clone->statePath($itemPath);
            } elseif ($clone instanceof LayoutComponent) {
                $clone->statePath($itemPath);
            }
            $components[] = $clone;
        }

        return $components;
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
        $rules = [];

        foreach ($this->schema as $component) {
            if ($component instanceof Component && $component instanceof HasValidation) {
                $childRules = $component->getValidationRules();
                if ($childRules !== []) {
                    $rules[$component->getName()] = $childRules;
                }
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
