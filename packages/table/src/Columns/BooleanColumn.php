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

    public function trueIcon(string|Icon $icon): static
    {
        $this->trueIcon = $icon instanceof Icon ? $icon->value() : $icon;

        return $this;
    }

    public function falseIcon(string|Icon $icon): static
    {
        $this->falseIcon = $icon instanceof Icon ? $icon->value() : $icon;

        return $this;
    }

    public function trueColor(string|Color $color): static
    {
        $this->trueColor = $color instanceof Color ? $color->value : $color;

        return $this;
    }

    public function falseColor(string|Color $color): static
    {
        $this->falseColor = $color instanceof Color ? $color->value : $color;

        return $this;
    }

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

        return $this->renderView('tables.columns.boolean', [
            'colorClass' => $this->resolveColorClass($color),
            'iconHtml' => app(IconManager::class)->render($icon, 'w-5 h-5'),
            'label' => $label,
        ]);
    }

    protected function resolveColorClass(string $color): string
    {
        return self::getTextColorClasses($color);
    }
}
