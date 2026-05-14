<?php

declare(strict_types=1);

/**
 * Class ButtonColumn
 *
 * @project   intranet.local
 *
 * @author    Ondřej Nyklíček
 *
 * @created   27.01.2026
 */

namespace NyonCode\WireTable\Columns;

use Closure;
use Illuminate\Database\Eloquent\Model;

class ButtonColumn extends Column
{
    /** @var string|Closure|null Button label */
    protected string|Closure|null $buttonLabel = null;

    /** @var string|Closure|null Button icon */
    protected string|Closure|null $buttonIcon = null;

    /** @var string|Closure Button color (primary, danger, success, warning, info, secondary) */
    protected string|Closure $buttonColor = 'primary';

    /** @var string|Closure Button size (xs, sm, md, lg) */
    protected string|Closure $buttonSize = 'sm';

    /** @var string|Closure Button variant (filled, outlined, link) */
    protected string|Closure $buttonVariant = 'filled';

    /** @var Closure|null Action callback */
    protected ?Closure $action = null;

    /** @var string|null Livewire action method name */
    protected ?string $livewireAction = null;

    /** @var string|Closure|null URL for link buttons */
    protected string|Closure|null $actionUrl = null;

    /** @var bool|Closure Whether to open URL in new tab */
    protected bool|Closure $openInNewTab = false;

    /** @var bool|Closure Whether to show confirmation dialog */
    protected bool|Closure $requiresConfirmation = false;

    /** @var string|Closure|null Confirmation modal title */
    protected string|Closure|null $confirmationTitle = null;

    /** @var string|Closure|null Confirmation modal description */
    protected string|Closure|null $confirmationDescription = null;

    /** @var string|Closure|null Confirm button text */
    protected string|Closure|null $confirmButtonText = null;

    /** @var string|Closure|null Cancel button text */
    protected string|Closure|null $cancelButtonText = null;

    /** @var bool|Closure Whether button is disabled */
    protected bool|Closure $disabled = false;

    /** @var string|Closure|null Tooltip for disabled state */
    protected string|Closure|null $disabledTooltip = null;

    /** @var bool|Closure Whether to show loading state */
    protected bool|Closure $showLoading = true;

    /** @var string|Closure|null Loading text */
    protected string|Closure|null $loadingText = null;

    /** @var Closure|null Condition for visibility */
    protected ?Closure $visibleWhen = null;

    /** @var Closure|null Condition for enabled state */
    protected ?Closure $enabledWhen = null;

    /** @var array<string, mixed>|Closure Extra attributes for the button */
    protected array|Closure $extraButtonAttributes = [];

    /** @var string|null Icon position (before, after) */
    protected ?string $buttonIconPosition = 'before';

    /** @var bool Whether this is an icon-only button */
    protected bool $iconOnly = false;

    public function __construct(string $name)
    {
        parent::__construct($name);
        $this->sortable = false;
        $this->searchable = false;
    }

    /**
     * Set the button label.
     */
    public function buttonLabel(string|Closure $label): static
    {
        $this->buttonLabel = $label;

        return $this;
    }

    /**
     * Set the button icon.
     */
    public function buttonIcon(string|Closure $icon, ?string $position = 'before'): static
    {
        $this->buttonIcon = $icon;
        $this->buttonIconPosition = $position;

        return $this;
    }

    /**
     * Make this an icon-only button.
     */
    public function iconOnly(bool $iconOnly = true): static
    {
        $this->iconOnly = $iconOnly;

        return $this;
    }

    /**
     * Set the button size.
     */
    public function buttonSize(string|Closure $size): static
    {
        $this->buttonSize = $size;

        return $this;
    }

    /**
     * Set the action callback.
     */
    public function action(Closure $action): static
    {
        $this->action = $action;

        return $this;
    }

    /**
     * Set a Livewire action method.
     */
    public function livewireAction(string $method): static
    {
        $this->livewireAction = $method;

        return $this;
    }

    /**
     * Enable confirmation dialog.
     */
    public function requiresConfirmation(
        bool|Closure $requires = true,
        string|Closure|null $title = null,
        string|Closure|null $description = null,
        string|Closure|null $confirmText = null,
        string|Closure|null $cancelText = null,
    ): static {
        $this->requiresConfirmation = $requires;

        if ($title !== null) {
            $this->confirmationTitle = $title;
        }
        if ($description !== null) {
            $this->confirmationDescription = $description;
        }
        if ($confirmText !== null) {
            $this->confirmButtonText = $confirmText;
        }
        if ($cancelText !== null) {
            $this->cancelButtonText = $cancelText;
        }

        return $this;
    }

    /**
     * Set the disabled state.
     */
    public function disabled(bool|Closure $disabled = true, string|Closure|null $tooltip = null): static
    {
        $this->disabled = $disabled;
        $this->disabledTooltip = $tooltip;

        return $this;
    }

    /**
     * Set condition for visibility.
     */
    public function visibleWhen(Closure $callback): static
    {
        $this->visibleWhen = $callback;

        return $this;
    }

    /**
     * Set condition for enabled state.
     */
    public function enabledWhen(Closure $callback): static
    {
        $this->enabledWhen = $callback;

        return $this;
    }

    /**
     * Configure loading state.
     */
    public function loading(bool|Closure $show = true, string|Closure|null $text = null): static
    {
        $this->showLoading = $show;
        $this->loadingText = $text;

        return $this;
    }

    /**
     * Set extra button attributes.
     *
     * @param  array<string, mixed>|Closure  $attributes
     */
    public function extraButtonAttributes(array|Closure $attributes): static
    {
        $this->extraButtonAttributes = $attributes;

        return $this;
    }

    /**
     * Shortcut for danger button.
     */
    public function danger(): static
    {
        return $this->buttonColor('danger');
    }

    /**
     * Set the button color.
     */
    public function buttonColor(string|Closure $color): static
    {
        $this->buttonColor = $color;

        return $this;
    }

    /**
     * Shortcut for success button.
     */
    public function success(): static
    {
        return $this->buttonColor('success');
    }

    /**
     * Shortcut for outlined variant.
     */
    public function outlined(): static
    {
        return $this->buttonVariant('outlined');
    }

    /**
     * Set the button variant.
     */
    public function buttonVariant(string|Closure $variant): static
    {
        $this->buttonVariant = $variant;

        return $this;
    }

    /**
     * Shortcut for link variant.
     */
    public function link(): static
    {
        return $this->buttonVariant('link');
    }

    /**
     * Execute the action callback.
     */
    public function executeAction(Model $record): mixed
    {
        if ($this->action) {
            return ($this->action)($record, $this);
        }

        return null;
    }

    /**
     * Get the action callback.
     */
    public function getAction(): ?Closure
    {
        return $this->action;
    }

    public function renderCell(Model $record): string
    {
        if (! $this->canView() || ! $this->isVisibleForRecord($record)) {
            return '';
        }

        $buttonLabel = $this->evaluateForRecord($this->buttonLabel, $record) ?? $this->getLabel();
        $isDisabled = $this->isDisabledForRecord($record);
        $showLoading = $this->evaluateForRecord($this->showLoading, $record);
        $loadingText = $this->evaluateForRecord($this->loadingText, $record);
        $url = $this->evaluateForRecord($this->urlCallback, $record);
        $openInNewTab = $this->evaluateForRecord($this->openUrlInNewTab, $record);

        $classes = $this->getButtonClasses($record);
        $iconHtml = $this->renderButtonIcon($record);

        $extraAttributes = $this->evaluateForRecord($this->extraButtonAttributes, $record);
        $attributesString = '';
        foreach ($extraAttributes as $key => $value) {
            $attributesString .= ' '.e($key).'="'.e($value).'"';
        }

        // Disabled tooltip
        $disabledTooltip = $isDisabled ? $this->evaluateForRecord($this->disabledTooltip, $record) : null;
        if ($disabledTooltip) {
            $attributesString .= ' title="'.e($disabledTooltip).'"';
        }

        // Content
        $content = '';
        if ($iconHtml && $this->buttonIconPosition === 'before') {
            $content .= $iconHtml;
        }
        if (! $this->iconOnly) {
            $content .= '<span>'.e($buttonLabel).'</span>';
        }
        if ($iconHtml && $this->buttonIconPosition === 'after') {
            $content .= $iconHtml;
        }

        // Loading state
        $loadingHtml = '';
        if ($showLoading && ! $url) {
            $loadingContent = $loadingText ? e($loadingText) : ($this->iconOnly ? '' : e($buttonLabel));
            $loadingHtml =
                '
                <span wire:loading wire:target="'.
                ($this->livewireAction ?? 'executeColumnAction').
                '" class="inline-flex items-center gap-1.5">
                    <svg class="animate-spin w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    '.
                ($loadingContent ? '<span>'.$loadingContent.'</span>' : '').
                '
                </span>
            ';
        }

        // If URL, render as link
        if ($url) {
            $target = $openInNewTab ? ' target="_blank" rel="noopener noreferrer"' : '';

            return '<a href="'.
                e($url).
                '"'.
                $target.
                ' class="'.
                $classes.
                '"'.
                $attributesString.
                '>'.
                $content.
                '</a>';
        }

        // Render as button
        $wireClick = $this->getWireClick($record);
        $disabled = $isDisabled ? ' disabled' : '';

        return <<<HTML
        <button type="button" class="$classes" $wireClick$disabled$attributesString>
            <span wire:loading.remove wire:target="$this->livewireAction">$content</span>
            {$loadingHtml}
        </button>
        HTML;
    }

    /**
     * Check if button should be visible for this record.
     */
    public function isVisibleForRecord(Model $record): bool
    {
        if ($this->visibleWhen !== null) {
            return (bool) ($this->visibleWhen)($record, $this);
        }

        return true;
    }

    /**
     * Resolve a value that may be a Closure with record context.
     */
    protected function evaluateForRecord(mixed $value, Model $record): mixed
    {
        if ($value instanceof Closure) {
            return $value($record, $this);
        }

        return $value;
    }

    /**
     * Check if button is disabled for this record.
     */
    public function isDisabledForRecord(Model $record): bool
    {
        if ($this->enabledWhen !== null) {
            return ! ($this->enabledWhen)($record, $this);
        }

        return (bool) $this->evaluateForRecord($this->disabled, $record);
    }

    /**
     * Get button classes based on color, size, and variant.
     */
    protected function getButtonClasses(Model $record): string
    {
        $color = $this->evaluateForRecord($this->buttonColor, $record);
        $size = $this->evaluateForRecord($this->buttonSize, $record);
        $variant = $this->evaluateForRecord($this->buttonVariant, $record);
        $isDisabled = $this->isDisabledForRecord($record);

        $baseClasses =
            'inline-flex items-center justify-center font-medium rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 dark:focus:ring-offset-gray-800';

        // Size classes
        $sizeClasses = match ($size) {
            'xs' => $this->iconOnly ? 'p-1' : 'px-2 py-1 text-xs gap-1',
            'sm' => $this->iconOnly ? 'p-1.5' : 'px-2.5 py-1.5 text-sm gap-1.5',
            'md' => $this->iconOnly ? 'p-2' : 'px-3 py-2 text-sm gap-2',
            'lg' => $this->iconOnly ? 'p-2.5' : 'px-4 py-2.5 text-base gap-2',
            default => $this->iconOnly ? 'p-1.5' : 'px-2.5 py-1.5 text-sm gap-1.5',
        };

        // Color classes based on variant
        $colorClasses = match ($variant) {
            'outlined' => match ($color) {
                'primary' => 'border border-primary-500 text-primary-600 hover:bg-primary-50 dark:text-primary-400 dark:hover:bg-primary-950 focus:ring-primary-500',
                'danger' => 'border border-red-500 text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-950 focus:ring-red-500',
                'success' => 'border border-emerald-500 text-emerald-600 hover:bg-emerald-50 dark:text-emerald-400 dark:hover:bg-emerald-950 focus:ring-emerald-500',
                'warning' => 'border border-amber-500 text-amber-600 hover:bg-amber-50 dark:text-amber-400 dark:hover:bg-amber-950 focus:ring-amber-500',
                'info' => 'border border-sky-500 text-sky-600 hover:bg-sky-50 dark:text-sky-400 dark:hover:bg-sky-950 focus:ring-sky-500',
                'secondary' => 'border border-gray-300 text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-800 focus:ring-gray-500',
                default => 'border border-gray-300 text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-800 focus:ring-gray-500',
            },
            'link' => match ($color) {
                'primary' => 'text-primary-600 hover:text-primary-800 dark:text-primary-400 dark:hover:text-primary-300 hover:underline',
                'danger' => 'text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300 hover:underline',
                'success' => 'text-emerald-600 hover:text-emerald-800 dark:text-emerald-400 dark:hover:text-emerald-300 hover:underline',
                'warning' => 'text-amber-600 hover:text-amber-800 dark:text-amber-400 dark:hover:text-amber-300 hover:underline',
                'info' => 'text-sky-600 hover:text-sky-800 dark:text-sky-400 dark:hover:text-sky-300 hover:underline',
                'secondary' => 'text-gray-600 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-300 hover:underline',
                default => 'text-gray-600 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-300 hover:underline',
            },
            default => match ($color) {
                'primary' => 'bg-primary-600 text-white hover:bg-primary-700 dark:bg-primary-500 dark:hover:bg-primary-600 focus:ring-primary-500',
                'danger' => 'bg-red-600 text-white hover:bg-red-700 dark:bg-red-500 dark:hover:bg-red-600 focus:ring-red-500',
                'success' => 'bg-emerald-600 text-white hover:bg-emerald-700 dark:bg-emerald-500 dark:hover:bg-emerald-600 focus:ring-emerald-500',
                'warning' => 'bg-amber-500 text-white hover:bg-amber-600 dark:bg-amber-400 dark:hover:bg-amber-500 focus:ring-amber-500',
                'info' => 'bg-sky-600 text-white hover:bg-sky-700 dark:bg-sky-500 dark:hover:bg-sky-600 focus:ring-sky-500',
                'secondary' => 'bg-gray-200 text-gray-800 hover:bg-gray-300 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600 focus:ring-gray-500',
                default => 'bg-gray-200 text-gray-800 hover:bg-gray-300 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600 focus:ring-gray-500',
            },
        };

        $disabledClasses = $isDisabled ? 'opacity-50 cursor-not-allowed pointer-events-none' : '';

        return implode(' ', array_filter([$baseClasses, $sizeClasses, $colorClasses, $disabledClasses]));
    }

    /**
     * Render the icon SVG.
     */
    protected function renderButtonIcon(Model $record): string
    {
        $icon = $this->evaluateForRecord($this->buttonIcon, $record);
        if (! $icon) {
            return '';
        }

        $size = $this->evaluateForRecord($this->buttonSize, $record);
        $iconSize = match ($size) {
            'xs' => 'w-3.5 h-3.5',
            'sm' => 'w-4 h-4',
            'md' => 'w-5 h-5',
            'lg' => 'w-5 h-5',
            default => 'w-4 h-4',
        };

        $path = $this->getIconPath($icon);

        return '<svg class="'.$iconSize.' shrink-0" fill="currentColor" viewBox="0 0 20 20">'.$path.'</svg>';
    }

    /**
     * Get wire:click directive.
     */
    protected function getWireClick(Model $record): string
    {
        $requiresConfirmation = $this->evaluateForRecord($this->requiresConfirmation, $record);

        if ($this->livewireAction) {
            $action = $this->livewireAction;
            $recordKey = $record->getKey();

            if ($requiresConfirmation) {
                return "wire:click=\"\$dispatch('open-confirmation-modal', {
                    action: '$action',
                    recordKey: '$recordKey',
                    title: '".
                    addslashes($this->evaluateForRecord($this->confirmationTitle, $record)).
                    "',
                    description: '".
                    addslashes($this->evaluateForRecord($this->confirmationDescription, $record)).
                    "',
                    confirmText: '".
                    addslashes($this->evaluateForRecord($this->confirmButtonText, $record)).
                    "',
                    cancelText: '".
                    addslashes($this->evaluateForRecord($this->cancelButtonText, $record)).
                    "'
                })\"";
            }

            return "wire:click=\"$action('$recordKey')\"";
        }

        if ($this->action) {
            $recordKey = $record->getKey();
            $columnName = $this->name;

            if ($requiresConfirmation) {
                return "wire:click=\"\$dispatch('open-confirmation-modal', {
                    action: 'executeColumnAction',
                    recordKey: '$recordKey',
                    column: '$columnName',
                    title: '".
                    addslashes($this->evaluateForRecord($this->confirmationTitle, $record)).
                    "',
                    description: '".
                    addslashes($this->evaluateForRecord($this->confirmationDescription, $record)).
                    "',
                    confirmText: '".
                    addslashes($this->evaluateForRecord($this->confirmButtonText, $record)).
                    "',
                    cancelText: '".
                    addslashes($this->evaluateForRecord($this->cancelButtonText, $record)).
                    "'
                })\"";
            }

            return "wire:click=\"executeColumnAction('$columnName', '$recordKey')\"";
        }

        return '';
    }
}
