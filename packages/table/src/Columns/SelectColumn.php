<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Columns;

use Closure;
use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Actions\Action;
use NyonCode\WireCore\Core\Capabilities\Capability;
use NyonCode\WireCore\Core\Support\Trans;
use NyonCode\WireCore\Foundation\Concerns\HasNativeControl;
use NyonCode\WireCore\Foundation\Concerns\HasRelationship;
use NyonCode\WireCore\Foundation\Contracts\DehydratesState;
use NyonCode\WireCore\Foundation\Support\EnumResolver;
use NyonCode\WireCore\Foundation\Support\MobileSheet;
use NyonCode\WireCore\Foundation\View\CellSync;
use NyonCode\WireTable\Concerns\HasRecordVersion;
use NyonCode\WireTable\Concerns\HasView;
use NyonCode\WireTable\Concerns\InteractsWithRecordDisabledState;
use NyonCode\WireTable\Concerns\InteractsWithTableActions;
use NyonCode\WireTable\Contracts\InheritsRecordState;
use NyonCode\WireTable\Contracts\RendersCellPanel;

/**
 * Inline editable select cell — the same control as a form's Select.
 *
 * The cell draws the shared combobox (`wire-core::partials.select-control`), or
 * the browser's `<select>` through the shared native choice
 * ({@see HasNativeControl}: `native()`, `nativeOnMobile()`, `touchOnMobile()`).
 * What differs from a form is who writes: the combobox is bound to the cell's
 * `wireEditableCell` component rather than to a Livewire property, and every
 * pick goes through `updateTableCell()` — the same guard, validation, lock and
 * hooks as any inline edit.
 *
 * Two costs are kept off the rows. The option list is drawn once per table
 * ({@see renderCellPanel()}), and a trigger clones it the first time it opens;
 * and each cell is a skeleton rendered once per column and filled per row
 * ({@see renderCell()}), so a column of selects costs no view render per row.
 *
 * The server never trusts the list the browser was shown: {@see validate()}
 * refuses any value that is not one of the options.
 */
class SelectColumn extends Column implements DehydratesState, InheritsRecordState, RendersCellPanel
{
    use HasNativeControl;
    use HasRecordVersion;
    use HasRelationship;
    use HasView;
    use InteractsWithRecordDisabledState;

    /** @var array<array-key, string>|Closure|string */
    protected array|Closure|string $optionsSource = [];

    /** @var array<string, string>|null Resolved on first use, then held for the render. */
    protected ?array $options = null;

    /** Guards the one relationship query per render — the list is the same for every row. */
    protected bool $relationshipOptionsLoaded = false;

    protected bool $multiple = false;

    protected bool $searchableOptions = false;

    protected bool $selectablePlaceholder = false;

    /** @var array<int, mixed>|Closure|null */
    protected array|Closure|null $createOptionSchema = null;

    protected ?Closure $createOptionCallback = null;

    protected ?string $createOptionModalHeading = null;

    public function __construct(string $name)
    {
        parent::__construct($name);
        $this->capabilities = $this->capabilities->add(Capability::Editable);
        $this->placeholder = Trans::get('wire-table::messages.select_placeholder');
    }

    /**
     * Set the dropdown options as a value => label map, a closure returning one,
     * or an enum class-string. A closure runs once, the first time the list is
     * needed.
     *
     * @param  array<array-key, string>|class-string|Closure  $options
     */
    public function options(array|string|Closure $options): static
    {
        $this->optionsSource = $options;
        $this->options = null;

        return $this;
    }

    /**
     * @return array<string, string>
     */
    public function getOptions(): array
    {
        if ($this->options !== null) {
            return $this->options;
        }

        $source = $this->optionsSource;

        return $this->options = EnumResolver::normalizeOptions($source instanceof Closure ? $source() : $source);
    }

    /** Let the cell hold several of the options — the attribute is then a list (cast it to `array`). */
    public function multiple(bool $multiple = true): static
    {
        $this->multiple = $multiple;

        return $this;
    }

    public function isMultiple(): bool
    {
        return $this->multiple;
    }

    /**
     * Put a search box at the top of the cell's list. Named apart from
     * `searchable()`, which on every column means the table's own search
     * reaches it.
     */
    public function searchableOptions(bool $searchable = true): static
    {
        $this->searchableOptions = $searchable;

        // A search box needs the combobox; an earlier native() gives way, as it
        // does on a SelectFilter.
        if ($searchable && $this->native === true) {
            $this->native = false;
        }

        return $this;
    }

    public function hasSearchableOptions(): bool
    {
        return $this->searchableOptions;
    }

    /**
     * Let the user pick the placeholder to empty the cell. Off by default: an
     * attribute that cannot be null would fail the write.
     */
    public function selectablePlaceholder(bool $selectable = true): static
    {
        $this->selectablePlaceholder = $selectable;

        return $this;
    }

    public function isPlaceholderSelectable(): bool
    {
        return $this->selectablePlaceholder;
    }

    /**
     * A form for creating a new option from the cell's list, as on a form's
     * Select. Pair with {@see createOptionUsing()}; the new option is written
     * into the cell it was created from.
     *
     * @param  array<int, mixed>|Closure  $schema
     */
    public function createOptionForm(array|Closure $schema): static
    {
        $this->createOptionSchema = $schema;

        return $this;
    }

    /**
     * Persist the new option: `fn (array $data)` returning its key, or the model
     * created (its key is used).
     */
    public function createOptionUsing(Closure $callback): static
    {
        $this->createOptionCallback = $callback;

        return $this;
    }

    /** Heading of the create-option modal (default: "Create option"). */
    public function createOptionModalHeading(?string $heading): static
    {
        $this->createOptionModalHeading = $heading;

        return $this;
    }

    public function hasCreateOptionForm(): bool
    {
        return $this->createOptionSchema !== null && $this->createOptionCallback !== null;
    }

    /** The name the create-option action is registered under on the table. */
    public function getCreateOptionActionName(): string
    {
        return 'select-column-create-option:'.$this->getName();
    }

    /**
     * The modal action behind the list's "Create" row. Found by
     * {@see InteractsWithTableActions::findAction()},
     * opened for the row the list belongs to; `$write` puts the new key into
     * that row's cell through the inline-edit path.
     *
     * @param  Closure(Model, mixed): void  $write
     */
    public function getCreateOptionAction(Closure $write): ?Action
    {
        if (! $this->hasCreateOptionForm()) {
            return null;
        }

        $schema = $this->createOptionSchema;

        return Action::make($this->getCreateOptionActionName())
            ->label(Trans::get('wire-forms::fields.create_option'))
            ->modalHeading($this->createOptionModalHeading ?? Trans::get('wire-forms::fields.create_option'))
            ->modalSubmitActionLabel(Trans::get('wire-forms::fields.create'))
            ->form($schema instanceof Closure ? $schema() : $schema)
            // The same answer the cell's write will give: nobody creates an
            // option for a cell they could not then set to it.
            ->visible(fn (?Model $record = null): bool => $record instanceof Model
                && $this->isEditable()
                && $this->isAuthorized()
                && $this->canInlineEdit()
                && $this->canEdit($record))
            ->action(function (array $data, Model $record) use ($write): void {
                $callback = $this->createOptionCallback;

                if (! $callback instanceof Closure) {
                    return;
                }

                $created = $callback($data);
                $key = is_object($created) && method_exists($created, 'getKey') ? $created->getKey() : $created;

                if (! is_scalar($key) || $key === '') {
                    return;
                }

                // The list was read before the option existed; read it again.
                // A list derived from the rows (distinct values of a column)
                // cannot have it until a row holds it, so it is added here: the
                // user has just made it an option.
                $this->options = null;
                $this->relationshipOptionsLoaded = false;
                $this->resolveRelationshipOptions($record);
                $this->options ??= $this->getOptions();
                $this->options[(string) $key] ??= $created instanceof Model && $this->titleAttribute !== null
                    ? (string) $created->getAttribute($this->titleAttribute)
                    : (string) $key;

                $value = $this->multiple
                    ? array_values(array_unique([...$this->selectedKeys($this->getState($record)), (string) $key]))
                    : (string) $key;

                $write($record, $value);
            });
    }

    // ─── Rendering ─────────────────────────────────────────────

    public function renderCell(Model $record): string
    {
        if (! $this->canView() || ! $this->isVisibleForRecord($record)) {
            return '';
        }

        $this->resolveRelationshipOptions($record);

        $state = $this->getState($record);

        if (! $this->isEditable()) {
            return e($this->displayLabel($state));
        }

        $keys = $this->selectedKeys($state);
        $json = json_encode($this->multiple ? $keys : ($keys[0] ?? ''), JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);
        $serverValue = $this->multiple ? $json : ($keys[0] ?? '');

        $disabled = $this->isDisabled($record);
        $skeleton = $this->cellSkeletons[$disabled ? 'd' : 'e'] ??= $this->buildCellSkeleton($disabled);

        $values = [
            // Longest first is strtr's own rule, and it matters: the JSON slot
            // contains the attribute slot.
            e(json_encode(self::SLOT_VALUE, JSON_THROW_ON_ERROR)) => e($json),
            self::SLOT_VALUE => e($serverValue),
            self::SLOT_KEY => e((string) $record->getKey()),
            self::SLOT_VERSION => e($this->recordVersion($record)),
        ];

        // The browser's <select> shows its first option until Alpine sets the
        // value; a `selected` on the right one keeps the first paint honest. In
        // the skeleton an option's `value` attribute is the only one written
        // with a leading space and a double quote, so it is the slot.
        if ($this->getNativeControlMode()->rendersNative()) {
            foreach ($keys as $key) {
                $values[' value="'.e($key).'"'] = ' value="'.e($key).'" selected';
            }
        }

        return strtr($skeleton, $values);
    }

    /**
     * The option list every cell of this column opens: drawn once per table,
     * cloned by the first trigger opened. Nothing when the cells are the
     * browser's own <select>, which carries its options itself.
     */
    public function renderCellPanel(): string
    {
        if (! $this->canView() || ! $this->isEditable() || ! $this->getNativeControlMode()->rendersCustom()) {
            return '';
        }

        // Options come from a relation only once a record is at hand; a
        // relation-backed list on an empty page has no cell to open it from.
        return $this->renderView('tables.columns.select-panel', [
            'column' => $this,
            'panelId' => $this->getCellPanelKey(),
            'options' => $this->getOptions(),
            'mobileBreakpoint' => MobileSheet::breakpoint(),
        ]);
    }

    /**
     * Per-record values are spliced into these. Plain letters, so `e()` and
     * `json_encode()` leave them alone and each position can be found again in
     * the encoding it was written in.
     */
    private const SLOT_VALUE = 'wcSelectSlotValue';

    private const SLOT_KEY = 'wcSelectSlotKey';

    private const SLOT_VERSION = 'wcSelectSlotVersion';

    /** @var array<string, string> One per shape: an enabled cell and a disabled one. */
    private array $cellSkeletons = [];

    private function buildCellSkeleton(bool $disabled): string
    {
        $mode = $this->getNativeControlMode();

        return $this->renderView('tables.columns.select', [
            'column' => $this,
            'recordKey' => self::SLOT_KEY,
            'value' => self::SLOT_VALUE,
            'recordVersion' => self::SLOT_VERSION,
            'syncHtml' => $this->cellSync()->node(self::SLOT_VALUE, self::SLOT_VERSION),
            'disabled' => $disabled,
            'nativeMode' => $mode,
            'panelId' => $this->getCellPanelKey(),
            // Native options carry their own list; the combobox reads the panel.
            'nativeOptions' => $mode->rendersNative() ? $this->getOptions() : [],
            'mobileBreakpoint' => MobileSheet::breakpoint(),
        ]);
    }

    /** The key the shared list, its triggers and a created-option event agree on. */
    public function getCellPanelKey(): string
    {
        return 'select-column:'.$this->getName();
    }

    /**
     * The state as option keys, as strings — what the cell holds and the list
     * compares against. A multiple column's state is a list; a single one gives
     * one key or none.
     *
     * @return list<string>
     */
    public function selectedKeys(mixed $state): array
    {
        if ($state === null || $state === '') {
            return [];
        }

        if (is_string($state) && $this->multiple && str_starts_with($state, '[')) {
            $decoded = json_decode($state, true);
            $state = is_array($decoded) ? $decoded : $state;
        }

        $list = is_iterable($state) ? $state : [$state];
        $keys = [];

        foreach ($list as $item) {
            $scalar = EnumResolver::scalar($item);

            if (is_scalar($scalar) && (string) $scalar !== '') {
                $keys[] = (string) $scalar;
            }
        }

        return $this->multiple ? array_values(array_unique($keys)) : array_slice($keys, 0, 1);
    }

    /** What a read-only cell shows: the labels, joined, or the placeholder. */
    private function displayLabel(mixed $state): string
    {
        $options = $this->getOptions();
        $labels = [];

        foreach ($this->multiple ? (is_iterable($state) ? $state : $this->selectedKeys($state)) : [$state] as $item) {
            $key = EnumResolver::scalar($item);

            if ($key === null || $key === '') {
                continue;
            }

            // Enum-cast state cannot index the options array directly, so a
            // scalar key is tried first and the enum's own label after it.
            $labels[] = $options[(string) $key] ?? EnumResolver::label($item) ?? (string) $key;
        }

        return $labels !== []
            ? implode(', ', $labels)
            : ($this->getPlaceholder() ?? Trans::get('wire-table::messages.select_placeholder'));
    }

    // ─── The write ─────────────────────────────────────────────

    /**
     * What arrived, as option keys. A label is read as its option — a paste
     * from a spreadsheet carries what the cell showed, not the key behind it —
     * and a multiple column also takes a comma-separated list.
     */
    public function dehydrateState(mixed $state, ?Model $record = null): mixed
    {
        if ($record instanceof Model) {
            $this->resolveRelationshipOptions($record);
        }

        if ($this->multiple) {
            if (is_string($state) && ! str_starts_with($state, '[')) {
                $state = $state === '' ? [] : array_map('trim', explode(',', $state));
            }

            if (! is_array($state)) {
                return $state;
            }

            return array_map(fn (mixed $item): mixed => $this->keyFor($item), array_values($state));
        }

        return $this->keyFor($state);
    }

    private function keyFor(mixed $value): mixed
    {
        if (! is_scalar($value) || $value === '') {
            return $value;
        }

        $options = $this->getOptions();

        if (array_key_exists((string) $value, $options)) {
            return (string) $value;
        }

        $match = array_search(mb_strtolower((string) $value), array_map(mb_strtolower(...), $options), true);

        return $match === false ? $value : (string) $match;
    }

    /**
     * The value must be one of the options — whatever the browser sent. A list
     * the user was shown is not a rule the server can lean on: a forged write
     * would otherwise store anything in the attribute.
     *
     * @return array{valid: bool, errors: array<int, string>}
     */
    public function validate(mixed $value, Model $record): array
    {
        $this->resolveRelationshipOptions($record);
        $options = $this->getOptions();
        $invalid = ['valid' => false, 'errors' => [Trans::get('wire-table::messages.select_invalid_option')]];

        $values = $this->multiple ? $value : [$value];

        if ($value === null || $value === '' || $value === []) {
            return $this->selectablePlaceholder
                ? ['valid' => true, 'errors' => []]
                : ['valid' => false, 'errors' => [Trans::get('wire-table::messages.select_required')]];
        }

        if (! is_array($values) || ! array_is_list($values)) {
            return $invalid;
        }

        foreach ($values as $item) {
            if (! is_scalar($item) || ! array_key_exists((string) $item, $options)) {
                return $invalid;
            }
        }

        return ['valid' => true, 'errors' => []];
    }

    /**
     * Fill the option list from relationship() the first time a cell renders.
     *
     * The related list is identical for every row, so this runs once per render
     * and is then memoized — loading it per cell would be a query per row. An
     * explicit ->options() always wins: it is the caller being specific.
     */
    protected function resolveRelationshipOptions(Model $record): void
    {
        if ($this->relationshipOptionsLoaded || $this->relationship === null) {
            return;
        }

        $this->relationshipOptionsLoaded = true;

        if ($this->getOptions() === []) {
            $this->loadRelationshipOptions($record);
        }
    }

    /**
     * Load options from the relationship's related model.
     *
     * Called automatically on the first render when {@see relationship()} is set;
     * public so a caller can pre-seed the list from a known record.
     */
    public function loadRelationshipOptions(Model $record): static
    {
        if ($this->relationship === null || $this->titleAttribute === null) {
            return $this;
        }

        if (! method_exists($record, $this->relationship)) {
            return $this;
        }

        try {
            $relation = $record->{$this->relationship}();
            $relatedModel = $relation->getRelated();
            $options = $relatedModel::query()
                ->pluck($this->titleAttribute, $relatedModel->getKeyName())
                ->all();

            return $this->options($options);
        } catch (\Throwable) {
            // Options left as they were, deliberately. This runs per rendered
            // row to fill a dropdown from a relation, so the failure modes are a
            // name that is not a relation and a related table that cannot be
            // queried — neither of which should take the table down. The cell
            // renders with whatever options were declared by hand.
            return $this;
        }
    }

    /**
     * Server-side edit guard consulted by WithTable::updateTableCell().
     *
     * The client-side `disabled` state is only cosmetic (a forged request could
     * still hit updateTableCell), so a per-record disabled cell must also be
     * rejected here. Column-level permissions are enforced separately by
     * updateTableCell before the write.
     */
    public function canEdit(Model $record): bool
    {
        return ! $this->isDisabled($record);
    }

    /** Held for the render, not resolved per row — see HasRecordVersion. */
    private ?CellSync $cellSyncResolver = null;

    private function cellSync(): CellSync
    {
        return $this->cellSyncResolver ??= app(CellSync::class);
    }
}
