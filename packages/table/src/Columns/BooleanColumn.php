<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Columns;

use Illuminate\Database\Eloquent\Model;

class BooleanColumn extends Column
{
    protected string $trueIcon = 'check-circle';

    protected string $falseIcon = 'x-circle';

    protected string $trueColor = 'success';

    protected string $falseColor = 'danger';

    protected ?string $trueLabel = null;

    protected ?string $falseLabel = null;

    public function trueIcon(string $icon): static
    {
        $this->trueIcon = $icon;

        return $this;
    }

    public function falseIcon(string $icon): static
    {
        $this->falseIcon = $icon;

        return $this;
    }

    public function trueColor(string $color): static
    {
        $this->trueColor = $color;

        return $this;
    }

    public function falseColor(string $color): static
    {
        $this->falseColor = $color;

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

        $colorClass = $this->getColorClass($color);

        $labelHtml = $label ? "<span class=\"ml-1.5\">$label</span>" : '';

        return <<<HTML
        <span class="inline-flex items-center $colorClass">
            {$this->getIconSvg($icon)}
            {$labelHtml}
        </span>
        HTML;
    }

    public function getColorClass(string $color): string
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

    public function getIconSvg(string $icon): string
    {
        $svg = match ($icon) {
            'check-circle' => '<path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>',
            'x-circle' => '<path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>',
            'check' => '<path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>',
            'x' => '<path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>',
            default => '<path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>',
        };

        return <<<HTML
        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">$svg</svg>
        HTML;
    }
}
