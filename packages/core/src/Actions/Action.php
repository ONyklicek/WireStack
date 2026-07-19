<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Actions;

use Closure;
use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Actions\Contracts\RendersAsButton;
use NyonCode\WireCore\Actions\Contracts\ResolvesActionClick;
use NyonCode\WireCore\Actions\Support\MountActionClickResolver;
use NyonCode\WireCore\Core\Support\Deprecation;
use NyonCode\WireCore\Foundation\View\Primitives;

/**
 * Class Action - Row-level action with lifecycle hooks, dynamic properties, and more.
 *
 * Now extends BaseAction which provides shared traits and properties.
 *
 * @author Ondřej Nyklíček
 *
 * @phpstan-consistent-constructor
 */
class Action extends BaseAction implements RendersAsButton
{
    protected bool $hideLabel = false;

    protected ?Closure $urlCallback = null;

    protected bool $openUrlInNewTab = false;

    protected bool $iconButton = false;

    /** Render with the "quiet" resting style (neutral until hover/focus). Set by the table. */
    protected bool $quiet = false;

    /** Escape hatch: force the solid filled button even under a quiet table. */
    protected bool $solid = false;

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

    /** Render as an icon-only button (no visible label text). */
    public function iconButton(bool $iconButton = true): static
    {
        $this->iconButton = $iconButton;

        return $this;
    }

    /**
     * Render with the quiet resting style (neutral text at rest, color on
     * hover/focus). Normally set for the whole table via Table::actionsStyle('quiet');
     * exposed fluently so a single action can opt in too.
     */
    public function quiet(bool $quiet = true): static
    {
        $this->quiet = $quiet;

        return $this;
    }

    /**
     * Force the solid filled button, overriding a quiet table. Use for the one
     * deliberately prominent action in a row (e.g. "Approve").
     */
    public function solid(bool $solid = true): static
    {
        $this->solid = $solid;

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
     * Misspelled alias of {@see hideLabel()}, kept for backwards compatibility.
     *
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

    /** Make the action navigate to a URL instead of running a callback. */
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

    public function isQuiet(): bool
    {
        return $this->quiet;
    }

    public function isSolid(): bool
    {
        return $this->solid;
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
        return $this->urlCallback ? ($this->urlCallback)($record) : null;
    }

    // ─── Rendering ──────────────────────────────────────────────

    /**
     * Memoised record-invariant render fragments (classes + icon SVG). Built once
     * per action and reused across every row, unless a visual property is a
     * per-record closure (then it is recomputed per record).
     *
     * @var array<string, string>|null
     */
    private ?array $staticRenderCache = null;

    /**
     * Render this action's button through the canonical core view.
     *
     * The host supplies a {@see ResolvesActionClick} so core never hardcodes a
     * table/form Livewire method; without one a standalone `mountAction()` is used.
     */
    public function render(Model $record, ?ResolvesActionClick $click = null): string
    {
        if ($this->isDivider) {
            return $this->renderDivider();
        }

        if (! $this->canExecute($record)) {
            return '';
        }

        return view('wire-core::actions.button', [
            'action' => $this,
            'record' => $record,
            'click' => $click ?? new MountActionClickResolver,
        ])->render();
    }

    /**
     * Render this action as a dropdown menu item (used inside an ActionGroup).
     *
     * Dividers resolve to the shared separator partial; everything else renders
     * through the canonical dropdown-item partial so menu rows stay consistent.
     */
    public function renderForDropdown(Model $record, ?ResolvesActionClick $click = null): string
    {
        if ($this->isDivider) {
            return $this->renderDivider();
        }

        if (! $this->canExecute($record)) {
            return '';
        }

        return view('wire-core::actions.dropdown-item', [
            'action' => $this,
            'record' => $record,
            'click' => $click ?? new MountActionClickResolver,
        ])->render();
    }

    /**
     * Render the shared visual separator partial.
     */
    public function renderDivider(): string
    {
        return view('wire-core::actions.partials.divider')->render();
    }

    /**
     * Resolve all render data for the canonical button view.
     *
     * Per-record dynamic properties (label, color, icon, disabled, extra
     * attributes) and the host-supplied click expression are resolved here so the
     * Blade view only echoes state.
     *
     * @return array<string, mixed>
     */
    public function toButtonRenderArray(?Model $record = null, ?ResolvesActionClick $click = null): array
    {
        $click ??= new MountActionClickResolver;
        $static = $this->staticRender($record);
        $loadingState = $this->getLoadingStateData();

        // Same bare expression drives wire:click and the wire:loading target.
        $clickHandler = $click->clickHandler($this, $record);

        $url = $record ? $this->getUrl($record) : null;

        return [
            'url' => $url,
            'isButton' => ! $url,
            'classes' => $static['classes'],
            'iconHtml' => $static['iconHtml'],
            // Not escaped here: the button-content view echoes this through {{ }},
            // which escapes it. Pre-escaping produced double-encoded labels (a
            // literal `&amp;` for `&`, `&lt;` shown as text).
            'label' => $this->hideLabel ? '' : $this->getLabel($record),
            'tooltip' => $this->getTooltip($record),
            'target' => $this->openUrlInNewTab ? '_blank' : null,
            'disabled' => $record ? $this->isDisabled($record) : false,
            'recordKey' => $record?->getKey(),
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
            // Host-resolved click + the wire:target the loading indicator gates on.
            'wireClick' => $clickHandler,
            'loadingTarget' => $clickHandler,
            // Record-invariant spinner, resolved once per request (a wrapping
            // wire:loading span carries the per-row target, so this stays cacheable).
            'spinnerHtml' => app(Primitives::class)->spinner('w-4 h-4'),
        ];
    }

    /**
     * BC alias for {@see toButtonRenderArray()} kept for callers that pass a record.
     *
     * @return array<string, mixed>
     */
    public function getRenderData(Model $record): array
    {
        return $this->toButtonRenderArray($record);
    }

    /**
     * Record-invariant render fragments (button classes + icon SVG).
     *
     * Memoised across rows unless a visual property (color/size/icon) is a
     * per-record closure, in which case it is recomputed for the given record.
     *
     * @return array<string, string>
     */
    private function staticRender(?Model $record): array
    {
        if ($this->colorCallback !== null || $this->sizeCallback !== null || $this->iconCallback !== null) {
            return $this->computeStaticRender($record);
        }

        return $this->staticRenderCache ??= $this->computeStaticRender($record);
    }

    /**
     * @return array<string, string>
     */
    private function computeStaticRender(?Model $record): array
    {
        $color = $this->getColor($record);
        $icon = $this->getIcon($record);
        $size = $this->getSize($record);

        $colorClasses = $this->isIconButton()
            ? $this->resolveIconButtonColorClasses($color)
            : ($this->isOutlined()
                ? $this->resolveOutlinedColorClasses($color)
                : ($this->quiet && ! $this->solid
                    ? $this->resolveQuietColorClasses($color)
                    : $this->resolveSolidColorClasses($color)));

        $sizeClasses = $this->resolveButtonSizeClasses($this->isIconButton(), $size);

        return [
            'classes' => self::BUTTON_BASE_CLASSES." {$sizeClasses} {$colorClasses}",
            'iconHtml' => $icon
                ? $this->renderIconSvg($icon, $this->isIconButton() ? 'w-5 h-5' : 'w-4 h-4')
                : '',
        ];
    }
}
