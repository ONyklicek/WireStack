<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Columns;

use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Foundation\Colors\Color;
use NyonCode\WireCore\Foundation\View\CellSync;
use NyonCode\WireTable\Concerns\CanEditBooleanCell;

/**
 * Checkbox column — an inline checkbox writing a boolean straight to the record.
 *
 * The table-side counterpart of the panel `CheckboxEntry`, sharing the optimistic
 * write path with {@see ToggleColumn}; use it where a checkbox reads more
 * naturally than a switch, or where a dense table has no room for a track.
 */
class CheckboxColumn extends Column
{
    // canEdit() + the record-version/disabled wiring are shared with ToggleColumn.
    use CanEditBooleanCell;

    protected ?string $accentColor = 'primary';

    public function __construct(string $name)
    {
        parent::__construct($name);
        $this->markEditable();
    }

    /** Set the accent color used when the checkbox is checked. */
    public function accentColor(string|Color|null $color): static
    {
        $this->accentColor = $color instanceof Color ? $color->value : $color;

        return $this;
    }

    public function renderCell(Model $record): string
    {
        if (! $this->canView() || ! $this->isVisibleForRecord($record)) {
            return '';
        }

        $state = (bool) $this->getState($record);

        return $this->renderView('tables.columns.checkbox', [
            'state' => $state,
            'recordKey' => (string) $record->getKey(),
            'columnName' => $this->getName(),
            'disabled' => $this->isDisabled($record),
            'accentColorClass' => $this->getAccentColorClass(),
            'recordVersion' => $this->recordVersion($record),
            'syncHtml' => app(CellSync::class)->node($state ? '1' : '0', $this->recordVersion($record)),
        ]);
    }

    /**
     * Checked-state accent from the canonical Foundation palette, matching the
     * panel CheckboxEntry rather than re-encoding a hue map here.
     */
    public function getAccentColorClass(): string
    {
        return self::getTextColorClasses($this->accentColor ?? 'primary');
    }
}
