<?php

declare(strict_types=1);

namespace NyonCode\WireForms\Components;

use Closure;
use Illuminate\Contracts\View\View;
use NyonCode\WireCore\Foundation\Components\Component;
use NyonCode\WireCore\Foundation\Components\LayoutComponent;
use NyonCode\WireCore\Foundation\Concerns\CanBeDehydrated;
use NyonCode\WireCore\Foundation\Concerns\HasDefault;
use NyonCode\WireCore\Foundation\Concerns\HasItemExpansion;
use NyonCode\WireCore\Foundation\Contracts\CanBeDehydrated as CanBeDehydratedContract;
use NyonCode\WireCore\Foundation\Support\EvaluatesClosures;
use NyonCode\WireForms\Concerns\ClonesItemSchema;
use NyonCode\WireForms\Concerns\HasFormValidation;
use NyonCode\WireForms\Concerns\HasItemLimits;
use NyonCode\WireForms\Contracts\HasValidation;
use NyonCode\WireForms\Forms\Runtime\RelationshipSaveHandler;

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
class Repeater extends LayoutComponent implements CanBeDehydratedContract, HasValidation
{
    use CanBeDehydrated {
        isDehydrated as isDehydratedByDeclaration;
    }
    use ClonesItemSchema;
    use EvaluatesClosures;
    use HasDefault;
    use HasFormValidation;

    // Not CanBeCollapsed: this brings it in, plus the positional policies a list
    // needs (expandFirst/expandLast). Using both would bind the wrong collapsed().
    use HasItemExpansion;
    use HasItemLimits;

    protected ?string $relationship = null;

    protected bool $addable = true;

    protected bool $deletable = true;

    protected bool $reorderable = false;

    protected bool $table = false;

    protected bool $cloneable = false;

    protected ?string $orderColumn = null;

    protected string $itemKeyName = 'id';

    protected ?string $emptyLabel = null;

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

    /**
     * A relationship repeater's key names a relation, not a column: its rows are
     * written by {@see RelationshipSaveHandler} after the parent record, and
     * writing the key itself would fatal on a column that does not exist. Stated
     * here rather than enumerated by the save handler, and still overridable —
     * an owner may switch a column-backed repeater off as well.
     */
    public function isDehydrated(): bool
    {
        return $this->relationship === null && $this->isDehydratedByDeclaration();
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

    /**
     * Whether each row offers a "duplicate" button.
     *
     * The copy is inserted directly below its original and carries every field's
     * value — except {@see itemKeyName()}, which is stripped so the relationship
     * save treats it as a new child. Leaving the key on would make both rows
     * match the same record: the second `fill()->save()` would overwrite the
     * first and one of the two rows would vanish on reload.
     */
    public function cloneable(bool $condition = true): static
    {
        $this->cloneable = $condition;

        return $this;
    }

    /**
     * Persist the rows' order into this column on the related model.
     *
     * Reordering is otherwise only true of the array in the browser: a HasMany
     * comes back in whatever order the database returns, so a drag survived until
     * the next load and no further. With a column named, each row is written with
     * its zero-based position before it is saved (a pivot column, for a
     * BelongsToMany).
     *
     * Writing the order is this package's half. Reading it back is the caller's:
     * the form is filled from data you pass it, so order the relation yourself —
     * `$record->contacts()->orderBy('sort_order')->get()`, or an `orderBy` on the
     * relation method — or the rows return in the database's order and the column
     * looks broken while being written correctly.
     */
    public function orderColumn(?string $column = 'sort_order'): static
    {
        $this->orderColumn = $column;

        return $this;
    }

    /**
     * The per-row key that identifies an existing child record.
     *
     * Only {@see cloneable()} reads it, to strip the key from a copy. It defaults
     * to `id` rather than being derived from the relation because a component
     * never sees the model — the record reaches the save handler, not the schema
     * — and every other consumer of the key already resolves it there.
     */
    public function itemKeyName(string $name): static
    {
        $this->itemKeyName = $name;

        return $this;
    }

    /** What to show in place of the rows when there are none. */
    public function emptyLabel(?string $label): static
    {
        $this->emptyLabel = $label;

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

    /**
     * Duplicating a row adds one, so it is gated by the same switches adding is:
     * a full repeater must not grow past `maxItems()` through the back door, and
     * a repeater that cannot be added to cannot be cloned into either.
     */
    public function isCloneable(): bool
    {
        return $this->cloneable && $this->isAddable();
    }

    public function getOrderColumn(): ?string
    {
        return $this->orderColumn;
    }

    public function getItemKeyName(): string
    {
        return $this->itemKeyName;
    }

    public function getEmptyLabel(): string
    {
        return $this->emptyLabel ?? __('No items yet');
    }

    /**
     * Lay the items out as table rows: one column per schema field, headed once,
     * instead of a stacked card per item.
     *
     * Suited to short, uniform rows (an invoice line, a key/value pair) where a
     * card per item wastes vertical space. Per-item collapsing does not apply to
     * a row, so {@see collapsible()} is ignored in this layout.
     */
    public function table(bool $condition = true): static
    {
        $this->table = $condition;

        return $this;
    }

    public function isTable(): bool
    {
        return $this->table;
    }

    /**
     * Column headings for the table layout, taken from the schema's own labels.
     *
     * Reads the template schema (not a per-item clone): the heading is the same
     * for every row, so it must not depend on one item's state.
     *
     * @return array<int, string>
     */
    public function getTableHeadings(): array
    {
        $headings = [];

        foreach ($this->schema as $component) {
            $headings[] = (string) $component->getLabel();
        }

        return $headings;
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

    /**
     * Whether a name was configured at all — which is not the same as one
     * resolving. The table layout heads a whole column with it, so it has to know
     * before it reaches the first row whether that column exists; a closure that
     * happens to return null for row 1 must not remove the heading.
     */
    public function hasItemLabel(): bool
    {
        return $this->itemLabel !== null;
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
        return $this->cloneSchemaForItem($this->schema, $this->getItemStatePath($index));
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
        return $this->table
            ? 'wire-forms::components.repeater-table'
            : 'wire-forms::components.repeater';
    }

    public function render(): View
    {
        return view($this->viewName(), ['field' => $this]);
    }
}
