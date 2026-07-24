<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Columns;

use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Core\Capabilities\Capability;
use NyonCode\WireCore\Foundation\Colors\Color;
use NyonCode\WireCore\Foundation\Icons\Icon;
use NyonCode\WireCore\Foundation\Icons\IconManager;
use NyonCode\WireTable\Concerns\HasRecordVersion;
use NyonCode\WireTable\Concerns\InteractsWithRecordDisabledState;

class ToggleColumn extends Column
{
    use HasRecordVersion;
    use InteractsWithRecordDisabledState;

    protected ?string $onColor = 'primary';

    protected ?string $offColor = 'gray';

    protected ?string $onIcon = null;

    protected ?string $offIcon = null;

    public function __construct(string $name)
    {
        parent::__construct($name);
        $this->capabilities = $this->capabilities->add(Capability::Editable);
        $this->editableType = 'toggle';
    }

    /** Set the track color when the toggle is on. */
    public function onColor(string|Color|null $color): static
    {
        $this->onColor = $color instanceof Color ? $color->value : $color;

        return $this;
    }

    /** Set the track color when the toggle is off. */
    public function offColor(string|Color|null $color): static
    {
        $this->offColor = $color instanceof Color ? $color->value : $color;

        return $this;
    }

    /** Set the icon shown on the thumb when the toggle is on. */
    public function onIcon(string|Icon|null $icon): static
    {
        $this->onIcon = $icon instanceof Icon ? $icon->value() : $icon;

        return $this;
    }

    /** Set the icon shown on the thumb when the toggle is off. */
    public function offIcon(string|Icon|null $icon): static
    {
        $this->offIcon = $icon instanceof Icon ? $icon->value() : $icon;

        return $this;
    }

    public function getOnIcon(): ?string
    {
        return $this->onIcon;
    }

    public function getOffIcon(): ?string
    {
        return $this->offIcon;
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

    public function renderCell(Model $record): string
    {
        if (! $this->canView() || ! $this->isVisibleForRecord($record)) {
            return '';
        }

        $state = (bool) $this->getState($record);

        return $this->renderView('tables.columns.toggle', [
            'state' => $state,
            'recordKey' => (string) $record->getKey(),
            'columnName' => $this->getName(),
            'disabled' => $this->isDisabled($record),
            'onColorClass' => $this->getOnColorClass(),
            'offColorClass' => $this->getOffColorClass(),
            'recordVersion' => $this->recordVersion($record),
            // Resolved here, not in Blade: the column owns icon semantics.
            'onIcon' => $this->onIcon ? app(IconManager::class)->render($this->onIcon, 'w-3 h-3') : '',
            'offIcon' => $this->offIcon ? app(IconManager::class)->render($this->offIcon, 'w-3 h-3') : '',
        ]);
    }

    protected function getOnColorClass(): string
    {
        // Solid background fill is owned by Foundation HasColor (canonical palette:
        // primary, success → emerald, warning → amber, info → cyan).
        return self::getSolidBgClass($this->onColor ?? 'primary');
    }

    protected function getOffColorClass(): string
    {
        // Soft (muted) background fill for the "off" track is owned by the same
        // Foundation HasColor palette; gray default matches the neutral track.
        return self::getSoftBgClass($this->offColor ?? 'gray');
    }
}
