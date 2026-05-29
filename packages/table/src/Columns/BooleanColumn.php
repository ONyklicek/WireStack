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
        if (! $this->canView()) {
            return '';
        }

        $state = (bool) $this->getState($record);
        $icon = $state ? $this->trueIcon : $this->falseIcon;
        $color = $state ? $this->trueColor : $this->falseColor;
        $label = $state ? $this->trueLabel : $this->falseLabel;

        $colorClass = $this->resolveColorClass($color);
        $svg = app(IconManager::class)->render($icon, 'w-5 h-5');
        $labelHtml = $label ? "<span class=\"ml-1.5\">$label</span>" : '';

        return <<<HTML
        <span class="inline-flex items-center $colorClass">
            $svg
            $labelHtml
        </span>
        HTML;
    }

    protected function resolveColorClass(string $color): string
    {
        return match ($color) {
            'success', 'green' => 'text-green-500',
            'danger', 'red' => 'text-red-500',
            'warning', 'yellow' => 'text-yellow-500',
            'info', 'blue' => 'text-blue-500',
            'gray' => 'text-gray-500',
            default => 'text-gray-500',
        };
    }
}
