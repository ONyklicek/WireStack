<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Actions;

use AllowDynamicProperties;
use Closure;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Traits\Macroable;
use NyonCode\WireCore\Actions\Concerns\HasColor;
use NyonCode\WireCore\Actions\Concerns\HasDynamicProperties;
use NyonCode\WireCore\Actions\Concerns\HasIcons;
use NyonCode\WireCore\Actions\Concerns\HasKeyboardShortcut;
use NyonCode\WireCore\Actions\Concerns\HasLifecycle;
use NyonCode\WireCore\Actions\Concerns\HasLoadingState;
use NyonCode\WireCore\Actions\Concerns\HasModal;
use NyonCode\WireCore\Actions\Concerns\HasVisibility;
use NyonCode\WireCore\Foundation\Colors\Color;
use NyonCode\WireCore\Foundation\Concerns\HasSize;

/**
 * Abstract BaseAction
 *
 * Shared foundation for Action, BulkAction, and HeaderAction.
 * Eliminates duplicated name, label, icon, color, size, outlined, tooltip,
 * actionCallback, and all trait wiring across all three action types.
 *
 * @author Ondřej Nyklíček
 *
 * @phpstan-consistent-constructor
 */
#[AllowDynamicProperties]
abstract class BaseAction implements Htmlable
{
    use HasColor;
    use HasDynamicProperties;
    use HasIcons;
    use HasKeyboardShortcut;
    use HasLifecycle;
    use HasLoadingState;
    use HasModal;
    use HasVisibility;
    use Macroable;

    /**
     * Canonical base class string for every rendered action button. The single
     * source consumed by both {@see getButtonClasses()} (record-less fallback)
     * and {@see Action::toButtonRenderArray()} (per-record assembly).
     */
    public const BUTTON_BASE_CLASSES = 'inline-flex items-center justify-center font-medium rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 dark:focus:ring-offset-gray-800';

    public string $name;

    protected ?string $label = null;

    protected ?string $icon = null;

    protected ?string $iconPosition = 'before';

    protected ?string $color = Color::Primary->value;

    protected ?string $size = 'sm';

    protected bool $outlined = false;

    protected ?Closure $actionCallback = null;

    protected ?string $tooltip = null;

    /** @var array<string, string> */
    protected array $extraAttributes = [];

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    public static function make(string $name): static
    {
        return new static($name);
    }

    // ─── Fluent setters ─────────────────────────────────────────

    /** Render the action as an outlined button instead of a solid one. */
    public function outlined(bool $outlined = true): static
    {
        $this->outlined = $outlined;

        return $this;
    }

    /** Set the callback run when the action executes (receives the record where applicable). */
    public function action(Closure $callback): static
    {
        $this->actionCallback = $callback;

        return $this;
    }

    public function getActionCallback(): ?Closure
    {
        return $this->actionCallback;
    }

    // ─── Getters ────────────────────────────────────────────────

    public function getName(): string
    {
        return $this->name;
    }

    public function getIconPosition(): string
    {
        return $this->iconPosition ?? 'before';
    }

    public function isOutlined(): bool
    {
        return $this->outlined;
    }

    // ─── Rendering helpers ──────────────────────────────────────

    /**
     * Resolve solid color classes for buttons.
     */
    protected function resolveSolidColorClasses(string $color): string
    {
        return $this->getSolidColorClasses($color);
    }

    /**
     * Resolve outlined color classes for buttons.
     */
    protected function resolveOutlinedColorClasses(string $color): string
    {
        return $this->getOutlinedColorClasses($color);
    }

    /**
     * Resolve icon button color classes.
     */
    protected function resolveIconButtonColorClasses(string $color): string
    {
        return $this->getIconButtonColorClasses($color);
    }

    /**
     * Resolve "quiet" color classes (neutral at rest, color on hover/focus).
     */
    protected function resolveQuietColorClasses(string $color): string
    {
        return $this->getQuietButtonColorClasses($color);
    }

    /**
     * Canonical color classes for a rendered action button (solid or outlined).
     *
     * Delegates to the shared Foundation color resolver so action views never
     * re-encode the palette. Used by the header/bulk action Blade partials.
     */
    public function getButtonColorClasses(): string
    {
        $color = $this->getColor();

        return $this->isOutlined()
            ? $this->getOutlinedColorClasses($color)
            : $this->getSolidColorClasses($color);
    }

    /**
     * Full CSS class string for a rendered action button (base + size + color).
     *
     * Public counterpart to Action::getRenderData()'s class assembly, used by the
     * header/bulk action Blade fallback where no per-record render data exists.
     */
    public function getButtonClasses(): string
    {
        $isIconButton = method_exists($this, 'isIconButton') && $this->isIconButton();
        $size = $this->getSize();

        return self::BUTTON_BASE_CLASSES." {$this->resolveButtonSizeClasses($isIconButton, $size)} {$this->getButtonColorClasses()}";
    }

    /**
     * Canonical ghost/menu-item color classes (dropdown items).
     *
     * Delegates to the shared Foundation resolver. Used by the row-action
     * dropdown-item Blade partial.
     */
    public function getMenuItemColorClasses(?string $color = null): string
    {
        return $this->getGhostColorClasses($color);
    }

    /**
     * Resolve button size classes.
     */
    protected function resolveButtonSizeClasses(bool $isIconButton, string $size): string
    {
        return HasSize::getButtonSizeClasses($size, $isIconButton);
    }

    public function toHtml(): string
    {
        return '';
    }
}
