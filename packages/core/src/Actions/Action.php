<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Actions;

use Closure;
use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Core\Support\Deprecation;

/**
 * Class Action - Row-level action with lifecycle hooks, dynamic properties, and more.
 *
 * Now extends BaseAction which provides shared traits and properties.
 *
 * @author Ondřej Nyklíček
 *
 * @phpstan-consistent-constructor
 */
class Action extends BaseAction
{
    protected bool $hideLabel = false;

    protected ?Closure $urlCallback = null;

    protected bool $openUrlInNewTab = false;

    protected bool $iconButton = false;

    protected bool $isDivider = false;

    /**
     * Create a visual divider for use in ActionGroup.
     */
    public static function divider(): static
    {
        $action = new static('__divider__');
        $action->isDivider = true;

        return $action;
    }

    // ─── Fluent setters ─────────────────────────────────────────

    public function iconButton(bool $iconButton = true): static
    {
        $this->iconButton = $iconButton;

        return $this;
    }

    /**
     * Hide the label text (show only icon). Fixed typo from `hiddeLabel`.
     */
    public function hideLabel(bool $hideLabel = true): static
    {
        $this->hideLabel = $hideLabel;

        return $this;
    }

    /**
     * @deprecated Use hideLabel() instead. Will be removed in v2.0.
     */
    public function hiddeLabel(bool $hiddeLabel = true): static
    {
        Deprecation::method('hiddeLabel', 'hideLabel');

        return $this->hideLabel($hiddeLabel);
    }

    /**
     * Alias for hideLabel(true).
     */
    public function onlyIcon(bool $onlyIcon = true): static
    {
        return $this->hideLabel($onlyIcon);
    }

    public function url(Closure|string $url, bool $openInNewTab = false): static
    {
        $this->urlCallback = $url instanceof Closure ? $url : fn () => $url;
        $this->openUrlInNewTab = $openInNewTab;

        return $this;
    }

    // ─── Getters ────────────────────────────────────────────────

    public function isIconButton(): bool
    {
        return $this->iconButton;
    }

    public function isHideLabel(): bool
    {
        return $this->hideLabel;
    }

    public function isDivider(): bool
    {
        return $this->isDivider;
    }

    public function shouldOpenUrlInNewTab(): bool
    {
        return $this->openUrlInNewTab;
    }

    public function getUrl(Model $record): ?string
    {
        return $this->urlCallback ? call_user_func($this->urlCallback, $record) : null;
    }

    // ─── Rendering ──────────────────────────────────────────────

    public function render(Model $record): string
    {
        if ($this->isDivider) {
            return '<div class="border-t border-gray-100 dark:border-gray-700 my-1" role="separator"></div>';
        }

        if (! $this->canExecute($record)) {
            return '';
        }

        return view('wire-table::tables.actions.action', [
            'action' => $this,
            'record' => $record,
        ])->render();
    }

    /**
     * Get all render data for Blade template. Resolves all dynamic properties per-record.
     *
     * @return array<string, mixed>
     */
    public function getRenderData(Model $record): array
    {
        $url = $this->getUrl($record);
        $color = $this->getColor($record);
        $icon = $this->getIcon($record);
        $size = $this->getSize($record);
        $loadingState = $this->getLoadingStateData();

        $colorClasses = $this->isIconButton()
            ? $this->resolveIconButtonColorClasses($color)
            : ($this->isOutlined()
                ? $this->resolveOutlinedColorClasses($color)
                : $this->resolveSolidColorClasses($color));

        $base = 'inline-flex items-center justify-center font-medium rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 dark:focus:ring-offset-gray-800';
        $sizeClasses = $this->resolveButtonSizeClasses($this->isIconButton(), $size);

        return [
            'url' => $url,
            'isButton' => ! $url,
            'classes' => "{$base} {$sizeClasses} {$colorClasses}",
            'iconHtml' => $icon
                ? $this->renderIconSvg($icon, $this->isIconButton() ? 'w-5 h-5' : 'w-4 h-4')
                : '',
            'label' => $this->hideLabel ? '' : e($this->getLabel($record)),
            'tooltip' => $this->getTooltip($record),
            'target' => $this->openUrlInNewTab ? '_blank' : null,
            'disabled' => $this->isDisabled($record),
            'recordKey' => $record->getKey(),
            'actionName' => $this->name,
            'hasModal' => $this->hasModal,
            'hideLabel' => $this->hideLabel,
            'iconPosition' => $this->iconPosition,
            'extraAttributes' => $this->getExtraAttributes($record),
            // Keyboard shortcut
            'shortcut' => $this->getKeyboardShortcut(),
            'shortcutLabel' => $this->getKeyboardShortcutLabel(),
            'shortcutAlpine' => $this->getAlpineKeydownExpression(),
            // Loading state
            'showLoading' => $loadingState['showLoading'],
            'loadingText' => $loadingState['loadingText'],
            'wireModifiers' => $loadingState['wireModifiers'],
        ];
    }
}
