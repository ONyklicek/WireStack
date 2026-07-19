<?php

declare(strict_types=1);

namespace NyonCode\WireForms\Components\Display;

use Closure;
use NyonCode\WireCore\Foundation\Colors\Color;
use NyonCode\WireCore\Foundation\Concerns\HasColor;
use NyonCode\WireCore\Foundation\Icons\Icon;

/**
 * Alert/notification display component with color, icon, title, and dismissible options.
 */
class Alert extends Display
{
    protected string|Closure|null $content = null;

    protected string $color = 'info';

    protected ?string $icon = null;

    protected string|Closure|null $title = null;

    protected bool $dismissible = false;

    /** Set the alert body text. */
    public function content(string|Closure|null $content): static
    {
        $this->content = $content;

        return $this;
    }

    /** Alias for {@see content()}. */
    public function message(string|Closure|null $message): static
    {
        return $this->content($message);
    }

    /** Set the alert title shown above the body. */
    public function title(string|Closure|null $title): static
    {
        $this->title = $title;

        return $this;
    }

    /** Set the alert color hue. */
    public function color(string|Color $color): static
    {
        $this->color = $color instanceof Color ? $color->value : $color;

        return $this;
    }

    /** Shortcut for the informational (blue) color. */
    public function info(): static
    {
        return $this->color(Color::Info);
    }

    /** Shortcut for the success (green) color. */
    public function success(): static
    {
        return $this->color(Color::Success);
    }

    /** Shortcut for the warning (amber) color. */
    public function warning(): static
    {
        return $this->color(Color::Warning);
    }

    /** Shortcut for the danger (red) color. */
    public function danger(): static
    {
        return $this->color(Color::Danger);
    }

    /** Set the leading icon. */
    public function icon(string|Icon|null $icon): static
    {
        $this->icon = $icon instanceof Icon ? $icon->value() : $icon;

        return $this;
    }

    /** Allow the user to dismiss the alert. */
    public function dismissible(bool $condition = true): static
    {
        $this->dismissible = $condition;

        return $this;
    }

    public function getContent(): ?string
    {
        return $this->evaluate($this->content);
    }

    public function getTitle(): ?string
    {
        return $this->evaluate($this->title);
    }

    public function getColor(): string
    {
        return $this->color;
    }

    /**
     * Soft alert surface color classes (background + border + text).
     *
     * Delegates to the canonical {@see HasColor::getAlertColorClasses()} palette
     * so the view only consumes the result instead of re-encoding the hue map.
     */
    public function getColorClasses(): string
    {
        return HasColor::getAlertColorClasses($this->color);
    }

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    public function isDismissible(): bool
    {
        return $this->dismissible;
    }

    protected function viewName(): string
    {
        return 'wire-forms::components.alert';
    }
}
