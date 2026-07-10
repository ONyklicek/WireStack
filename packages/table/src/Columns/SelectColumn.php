<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Columns;

use Closure;
use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Core\Capabilities\Capability;
use NyonCode\WireCore\Core\Support\Trans;
use NyonCode\WireCore\Foundation\Support\EnumResolver;
use NyonCode\WireTable\Concerns\HasView;

class SelectColumn extends Column
{
    use HasView;

    /** @var array<string, string> */
    protected array $options = [];

    protected bool $native = true;

    protected bool $disabled = false;

    protected ?Closure $disabledCallback = null;

    /** @var string|null Relationship name for auto-loading options */
    protected ?string $relationship = null;

    /** @var string|null Display attribute on the related model */
    protected ?string $titleAttribute = null;

    public function __construct(string $name)
    {
        parent::__construct($name);
        $this->capabilities = $this->capabilities->add(Capability::Editable);
        $this->editableType = 'select';
        $this->placeholder = Trans::get('wire-table::messages.select_placeholder');
    }

    /**
     * @param  array<string, string>|class-string|Closure  $options
     */
    public function options(array|string|Closure $options): static
    {
        $resolved = is_callable($options) ? $options() : $options;
        $this->options = EnumResolver::normalizeOptions($resolved);
        $this->editableOptions = $this->options;

        return $this;
    }

    /**
     * @return array<string, string>
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    public function native(bool $native = true): static
    {
        $this->native = $native;

        return $this;
    }

    public function isNative(): bool
    {
        return $this->native;
    }

    public function disabled(bool|Closure $disabled = true): static
    {
        if ($disabled instanceof Closure) {
            $this->disabledCallback = $disabled;
        } else {
            $this->disabled = $disabled;
        }

        return $this;
    }

    public function renderCell(Model $record): string
    {
        if (! $this->canView() || ! $this->isVisibleForRecord($record)) {
            return '';
        }

        $state = $this->getState($record);

        // If not editable, just show the display value
        if (! $this->isEditable()) {
            // Enum-cast state cannot index the options array directly, so resolve a
            // scalar key first and fall back to the enum's label.
            $key = EnumResolver::scalar($state);
            $displayValue = $this->options[$key]
                ?? (EnumResolver::label($state) ?? ($this->getPlaceholder() ?? Trans::get('wire-table::messages.select_placeholder')));

            return e((string) $displayValue);
        }

        return $this->renderView('tables.columns.select', [
            'column' => $this,
            'record' => $record,
            // Pass a scalar so the <option> selected comparison never stringifies an enum.
            'state' => EnumResolver::scalar($state),
        ]);
    }

    /**
     * Configure relationship for auto-loading options from related model.
     *
     * Usage: SelectColumn::make('category_id')->relationship('category', 'name')
     */
    public function relationship(string $name, string $titleAttribute): static
    {
        $this->relationship = $name;
        $this->titleAttribute = $titleAttribute;

        return $this;
    }

    public function getRelationshipName(): ?string
    {
        return $this->relationship;
    }

    public function getTitleAttribute(): ?string
    {
        return $this->titleAttribute;
    }

    /**
     * Load options from the relationship's related model.
     * Call this with a model instance to auto-populate options.
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
            return $this;
        }
    }

    public function isDisabled(Model $record): bool
    {
        if ($this->disabledCallback) {
            return ($this->disabledCallback)($record);
        }

        return $this->disabled;
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
}
