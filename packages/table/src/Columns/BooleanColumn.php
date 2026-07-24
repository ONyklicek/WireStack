<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Columns;

use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Foundation\Colors\Color;
use NyonCode\WireCore\Foundation\Icons\Icon;
use NyonCode\WireCore\Foundation\Icons\IconManager;

class BooleanColumn extends Column
{
    protected string $trueIcon = 'check-circle';

    protected string $falseIcon = 'x-circle';

    protected string $trueColor = 'success';

    protected string $falseColor = 'danger';

    protected ?string $trueLabel = null;

    protected ?string $falseLabel = null;

    /** Set the icon shown for a truthy value. */
    public function trueIcon(string|Icon $icon): static
    {
        $this->trueIcon = $icon instanceof Icon ? $icon->value() : $icon;

        return $this;
    }

    /** Set the icon shown for a falsy value. */
    public function falseIcon(string|Icon $icon): static
    {
        $this->falseIcon = $icon instanceof Icon ? $icon->value() : $icon;

        return $this;
    }

    /** Set the color used for a truthy value. */
    public function trueColor(string|Color $color): static
    {
        $this->trueColor = $color instanceof Color ? $color->value : $color;

        return $this;
    }

    /** Set the color used for a falsy value. */
    public function falseColor(string|Color $color): static
    {
        $this->falseColor = $color instanceof Color ? $color->value : $color;

        return $this;
    }

    /** Set tooltip labels for the true and false states. */
    public function labels(?string $trueLabel, ?string $falseLabel): static
    {
        $this->trueLabel = $trueLabel;
        $this->falseLabel = $falseLabel;

        return $this;
    }

    public function renderCell(Model $record): string
    {
        if (! $this->canView() || ! $this->isVisibleForRecord($record)) {
            return '';
        }

        $state = (bool) $this->getState($record);
        $icon = $state ? $this->trueIcon : $this->falseIcon;
        $color = $state ? $this->trueColor : $this->falseColor;
        $label = $state ? $this->trueLabel : $this->falseLabel;

        // §7: only two states (true/false) → memoise the view render by its data so
        // the whole column renders at most twice, not once per row.
        return $this->renderViewCached('tables.columns.boolean', [
            'colorClass' => self::getTextColorClasses($color),
            'iconHtml' => app(IconManager::class)->render($icon, 'w-5 h-5'),
            'label' => $label,
        ]);
    }
}
