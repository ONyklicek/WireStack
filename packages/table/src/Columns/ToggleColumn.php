<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Columns;

use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Core\Capabilities\Capability;
use NyonCode\WireCore\Foundation\Colors\Color;
use NyonCode\WireCore\Foundation\Icons\Icon;

class ToggleColumn extends Column
{
    protected ?string $onColor = 'primary';

    protected ?string $offColor = 'gray';

    protected ?string $onIcon = null;

    protected ?string $offIcon = null;

    protected bool $disabled = false;

    protected ?\Closure $disabledCallback = null;

    public function __construct(string $name)
    {
        parent::__construct($name);
        $this->capabilities = $this->capabilities->add(Capability::Editable);
        $this->editableType = 'toggle';
    }

    public function onColor(string|Color|null $color): static
    {
        $this->onColor = $color instanceof Color ? $color->value : $color;

        return $this;
    }

    public function offColor(string|Color|null $color): static
    {
        $this->offColor = $color instanceof Color ? $color->value : $color;

        return $this;
    }

    public function onIcon(string|Icon|null $icon): static
    {
        $this->onIcon = $icon instanceof Icon ? $icon->value() : $icon;

        return $this;
    }

    public function offIcon(string|Icon|null $icon): static
    {
        $this->offIcon = $icon instanceof Icon ? $icon->value() : $icon;

        return $this;
    }

    public function disabled(bool|\Closure $disabled = true): static
    {
        if ($disabled instanceof \Closure) {
            $this->disabledCallback = $disabled;
        } else {
            $this->disabled = $disabled;
        }

        return $this;
    }

    public function isDisabled(Model $record): bool
    {
        if ($this->disabledCallback) {
            return ($this->disabledCallback)($record);
        }

        return $this->disabled;
    }

    public function renderCell(Model $record): string
    {
        if (! $this->canView()) {
            return '';
        }

        $state = (bool) $this->getState($record);

        return $this->renderView('tables.columns.toggle', [
            'state' => $state,
            'recordKey' => (string) $record->getKey(),
            'columnName' => $this->getName(),
            'disabled' => $this->isDisabled($record),
            'onColorClass' => $this->getOnColorClass(),
        ]);
    }

    protected function getOnColorClass(): string
    {
        // Solid background fill is owned by Foundation HasColor (canonical palette:
        // primary, success → emerald, warning → amber, info → cyan).
        return self::getSolidBgClass($this->onColor ?? 'primary');
    }
}
