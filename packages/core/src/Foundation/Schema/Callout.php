<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Schema;

use Closure;
use NyonCode\WireCore\Foundation\Colors\Color;
use NyonCode\WireCore\Foundation\Components\LayoutComponent;
use NyonCode\WireCore\Foundation\Concerns\HasColor;
use NyonCode\WireCore\Foundation\Icons\Icon;

/**
 * Canonical callout — a soft, colored notice box with an optional heading, icon
 * and dismiss button. Body content comes from child components (schema) or a
 * plain string via {@see content()}.
 *
 * Shared vocabulary consumed by forms, infolists and the standalone
 * <x-wire::callout> tag. Color hues delegate to the canonical
 * {@see HasColor::getAlertColorClasses()} palette; markup lives in the shared
 * `wire-core::partials.callout` view. The forms Alert display field is the
 * field-style alias of this component.
 */
class Callout extends LayoutComponent
{
    protected string $color = 'info';

    protected ?string $icon = null;

    protected string|Closure|null $heading = null;

    protected string|Closure|null $content = null;

    protected bool $dismissible = false;

    public function heading(string|Closure|null $heading): static
    {
        $this->heading = $heading;

        return $this;
    }

    public function title(string|Closure|null $title): static
    {
        return $this->heading($title);
    }

    public function content(string|Closure|null $content): static
    {
        $this->content = $content;

        return $this;
    }

    public function color(string|Color $color): static
    {
        $this->color = $color instanceof Color ? $color->value : $color;

        return $this;
    }

    public function info(): static
    {
        return $this->color(Color::Info);
    }

    public function success(): static
    {
        return $this->color(Color::Success);
    }

    public function warning(): static
    {
        return $this->color(Color::Warning);
    }

    public function danger(): static
    {
        return $this->color(Color::Danger);
    }

    public function icon(string|Icon|null $icon): static
    {
        $this->icon = $icon instanceof Icon ? $icon->value() : $icon;

        return $this;
    }

    public function dismissible(bool $condition = true): static
    {
        $this->dismissible = $condition;

        return $this;
    }

    public function getHeading(): ?string
    {
        return $this->evaluate($this->heading);
    }

    public function getContent(): ?string
    {
        return $this->evaluate($this->content);
    }

    public function getColor(): string
    {
        return $this->color;
    }

    /**
     * Soft surface color classes (background + border + text), delegated to the
     * canonical alert palette.
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
        return 'wire-core::schema.callout';
    }
}
