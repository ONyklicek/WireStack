<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Columns;

use Illuminate\Database\Eloquent\Model;

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
        $this->editable = true;
        $this->editableType = 'toggle';
    }

    public function onColor(?string $color): static
    {
        $this->onColor = $color;

        return $this;
    }

    public function offColor(?string $color): static
    {
        $this->offColor = $color;

        return $this;
    }

    public function onIcon(?string $icon): static
    {
        $this->onIcon = $icon;

        return $this;
    }

    public function offIcon(?string $icon): static
    {
        $this->offIcon = $icon;

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
            return call_user_func($this->disabledCallback, $record);
        }

        return $this->disabled;
    }

    public function renderCell(Model $record): string
    {
        if (! $this->canView()) {
            return '';
        }

        $state = (bool) $this->getState($record);
        $recordKey = $record->getKey();
        $columnName = $this->getName();
        $disabled = $this->isDisabled($record);

        $bgColor = $state ? $this->getOnColorClass() : 'bg-gray-200 dark:bg-gray-700';

        $translateClass = $state ? 'translate-x-5' : 'translate-x-0';

        $cursorClass = $disabled ? 'cursor-not-allowed opacity-50' : 'cursor-pointer';

        $wireClick = $disabled ? '' : "wire:click=\"updateTableCell('{$recordKey}', '{$columnName}', ".($state ? 'false' : 'true').')"';

        return <<<HTML
        <button
            type="button"
            {$wireClick}
            class="relative inline-flex h-6 w-11 flex-shrink-0 {$cursorClass} rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 {$bgColor}"
            role="switch"
            aria-checked="{$state}"
        >
            <span class="sr-only">Toggle</span>
            <span
                aria-hidden="true"
                class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out {$translateClass}"
            ></span>
        </button>
        HTML;
    }

    protected function getOnColorClass(): string
    {
        return match ($this->onColor) {
            'primary', 'blue' => 'bg-blue-600',
            'success', 'green' => 'bg-green-600',
            'danger', 'red' => 'bg-red-600',
            'warning', 'yellow' => 'bg-yellow-500',
            'info' => 'bg-cyan-500',
            'secondary', 'gray' => 'bg-gray-600',
            'purple' => 'bg-purple-600',
            'pink' => 'bg-pink-600',
            default => 'bg-blue-600',
        };
    }
}
