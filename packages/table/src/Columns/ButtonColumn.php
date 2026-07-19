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
use NyonCode\WireCore\Foundation\Colors\Color;
use NyonCode\WireCore\Foundation\Concerns\HasSize;
use NyonCode\WireCore\Foundation\Icons\Icon;
use NyonCode\WireCore\Foundation\Icons\IconManager;
use NyonCode\WireCore\Foundation\View\Primitives;

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
        $this->sortable(false);
        $this->searchable(false);
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
    public function buttonIcon(string|Icon|Closure $icon, ?string $position = 'before'): static
    {
        $this->buttonIcon = $icon instanceof Icon ? $icon->value() : $icon;
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
     * Set a per-record visibility condition. BC alias for the canonical
     * {@see Column::visibleForRecord()}.
     */
    public function visibleWhen(Closure $callback): static
    {
        return $this->visibleForRecord($callback);
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
        return $this->buttonColor(Color::Danger);
    }

    /**
     * Set the button color.
     */
    public function buttonColor(string|Color|Closure $color): static
    {
        $this->buttonColor = $color instanceof Color ? $color->value : $color;

        return $this;
    }

    /**
     * Shortcut for success button.
     */
    public function success(): static
    {
        return $this->buttonColor(Color::Success);
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

        $isDisabled = $this->isDisabledForRecord($record);
        $url = $this->evaluateForRecord($this->urlCallback, $record);

        return $this->renderView('tables.columns.button', [
            'url' => $url,
            'openInNewTab' => (bool) $this->evaluateForRecord($this->openUrlInNewTab, $record),
            'classes' => $this->getButtonClasses($record),
            'iconHtml' => $this->renderButtonIcon($record),
            'iconPosition' => $this->buttonIconPosition,
            'iconOnly' => $this->iconOnly,
            'buttonLabel' => $this->evaluateForRecord($this->buttonLabel, $record) ?? $this->getLabel(),
            'extraAttributes' => $this->evaluateForRecord($this->extraButtonAttributes, $record),
            'disabledTooltip' => $isDisabled ? $this->evaluateForRecord($this->disabledTooltip, $record) : null,
            'isDisabled' => $isDisabled,
            'showLoading' => (bool) $this->evaluateForRecord($this->showLoading, $record) && ! $url,
            'loadingText' => $this->evaluateForRecord($this->loadingText, $record),
            'wireClick' => $this->getWireClick($record),
            'loadingTarget' => $this->livewireAction ?? 'executeColumnAction',
            'removeTarget' => (string) $this->livewireAction,
            // Record-invariant spinner resolved once per request (not @included per row).
            'spinnerHtml' => app(Primitives::class)->spinner(),
        ]);
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
        $color = (string) $this->evaluateForRecord($this->buttonColor, $record);
        $size = $this->evaluateForRecord($this->buttonSize, $record);
        $variant = $this->evaluateForRecord($this->buttonVariant, $record);
        $isDisabled = $this->isDisabledForRecord($record);

        $baseClasses =
            'inline-flex items-center justify-center font-medium rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 dark:focus:ring-offset-gray-800';

        // Size classes — canonical scale owned by Foundation HasSize.
        $sizeClasses = HasSize::getButtonSizeClasses($size, $this->iconOnly);

        // Color classes are owned by Foundation HasColor (the canonical palette).
        // Only the variant→surface mapping lives here; each surface resolver is
        // shared with actions/badges so hues never drift (success → emerald,
        // info → cyan, blue → primary).
        $colorClasses = match ($variant) {
            'outlined' => $this->getOutlinedColorClasses($color),
            'link' => self::getLinkColorClasses($color),
            default => $this->getSolidColorClasses($color),
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
        $iconSize = HasSize::getButtonIconSizeClasses($size);

        return app(IconManager::class)->render($icon, $iconSize.' shrink-0');
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
