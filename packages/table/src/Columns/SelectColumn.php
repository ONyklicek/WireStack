<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Columns;

use Closure;
use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Core\Capabilities\Capability;
use NyonCode\WireCore\Core\Support\Trans;
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
     * @param  array<string, string>|Closure  $options
     */
    public function options(array|Closure $options): static
    {
        $this->options = is_callable($options) ? $options() : $options;
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
        if (! $this->canView()) {
            return '';
        }

        $state = $this->getState($record);

        // If not editable, just show the display value
        if (! $this->isEditable()) {
            $displayValue = $this->options[$state] ?? ($state ?? ($this->getPlaceholder() ?? Trans::get('wire-table::messages.select_placeholder')));

            return e((string) $displayValue);
        }

        return $this->renderView('tables.columns.select', [
            'column' => $this,
            'record' => $record,
            'state' => $state,
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
}
