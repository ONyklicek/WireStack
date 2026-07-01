<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Actions\Concerns;

use Closure;
use Illuminate\Support\Str;
use NyonCode\WireCore\Foundation\Colors\Color;
use NyonCode\WireCore\Foundation\Icons\Icon;
use NyonCode\WireCore\Foundation\Support\EnumResolver;

/**
 * Trait HasDynamicProperties
 *
 * Adds Closure support to label, color, tooltip, icon, and size properties.
 * Allows per-record dynamic values for all visual Action properties.
 *
 * Usage:
 *   Action::make('publish')
 *       ->label(fn ($record) => $record->is_urgent ? 'Publikovat ihned!' : 'Publikovat')
 *       ->color(fn ($record) => $record->is_urgent ? 'danger' : 'primary')
 *       ->icon(fn ($record) => $record->is_draft ? 'pencil' : 'check')
 *       ->tooltip(fn ($record) => "Naposledy upraveno: {$record->updated_at->diffForHumans()}")
 *       ->size(fn ($record) => $record->is_featured ? 'lg' : 'sm')
 *       ->extraAttributes(fn ($record) => [
 *           'data-id' => $record->id,
 *           'data-status' => $record->status,
 *       ])
 */
trait HasDynamicProperties
{
    /**
     * Decide whether a dynamic-property Closure should be invoked.
     *
     * With a record context the closure always runs. Without one (header/bulk
     * actions carry no record), only context-free closures such as
     * `fn () => 'Label'` run; record-scoped closures (`fn ($record) => …`) still
     * fall back to the static value instead of erroring on a null record.
     */
    protected function shouldInvokeDynamicCallback(?Closure $callback, mixed $context): bool
    {
        if ($callback === null) {
            return false;
        }

        if ($context !== null) {
            return true;
        }

        return (new \ReflectionFunction($callback))->getNumberOfRequiredParameters() === 0;
    }

    // Callbacks for dynamic resolution
    protected ?Closure $labelCallback = null;

    protected ?Closure $colorCallback = null;

    protected ?Closure $tooltipCallback = null;

    protected ?Closure $iconCallback = null;

    protected ?Closure $sizeCallback = null;

    protected ?Closure $extraAttributesCallback = null;

    /**
     * Set label as string or Closure.
     * Closure signature: fn(Model $record): string
     */
    public function label(string|Closure|null $label): static
    {
        if ($label instanceof Closure) {
            $this->labelCallback = $label;
        } else {
            $this->label = $label;
            $this->labelCallback = null;
        }

        return $this;
    }

    /**
     * Resolve label - evaluates Closure if set.
     */
    public function getLabel(mixed $context = null): string
    {
        if ($this->shouldInvokeDynamicCallback($this->labelCallback, $context)) {
            return ($this->labelCallback)($context);
        }

        return $this->label ?? Str::headline($this->name);
    }

    /**
     * Set color as string, Color enum, or Closure.
     * Closure signature: fn(Model $record): string|Color
     */
    public function color(string|Color|Closure|null $color): static
    {
        if ($color instanceof Closure) {
            $this->colorCallback = $color;
        } else {
            $this->color = $color instanceof Color ? $color->value : $color;
            $this->colorCallback = null;
        }

        return $this;
    }

    /**
     * Resolve color - evaluates Closure if set.
     */
    public function getColor(mixed $context = null): string
    {
        if ($this->shouldInvokeDynamicCallback($this->colorCallback, $context)) {
            $value = ($this->colorCallback)($context);

            // A callback may return a Color, a palette-carrying enum, or a plain string.
            $value = EnumResolver::color($value) ?? $value;

            return $value instanceof Color ? $value->value : (string) EnumResolver::scalar($value);
        }

        return $this->color ?? Color::Primary->value;
    }

    /**
     * Set tooltip as string or Closure.
     * Closure signature: fn(Model $record): ?string
     */
    public function tooltip(string|Closure|null $tooltip): static
    {
        if ($tooltip instanceof Closure) {
            $this->tooltipCallback = $tooltip;
        } else {
            $this->tooltip = $tooltip;
            $this->tooltipCallback = null;
        }

        return $this;
    }

    /**
     * Resolve tooltip - evaluates Closure if set.
     */
    public function getTooltip(mixed $context = null): ?string
    {
        if ($this->shouldInvokeDynamicCallback($this->tooltipCallback, $context)) {
            return ($this->tooltipCallback)($context);
        }

        return $this->tooltip;
    }

    /**
     * Set icon as string, Icon enum, or Closure.
     * Closure signature: fn(Model $record): string|Icon|null
     */
    public function icon(string|Icon|Closure|null $icon, ?string $position = 'before'): static
    {
        if ($icon instanceof Closure) {
            $this->iconCallback = $icon;
        } else {
            $this->icon = $icon instanceof Icon ? $icon->value() : $icon;
            $this->iconCallback = null;
        }
        $this->iconPosition = $position;

        return $this;
    }

    /**
     * Resolve icon - evaluates Closure if set.
     */
    public function getIcon(mixed $context = null): ?string
    {
        if ($this->shouldInvokeDynamicCallback($this->iconCallback, $context)) {
            $value = ($this->iconCallback)($context);

            // A callback may return an Icon, an icon-carrying enum, or a plain string.
            $value = EnumResolver::icon($value) ?? $value;

            if ($value instanceof Icon) {
                return $value->value();
            }

            return $value !== null ? (string) EnumResolver::scalar($value) : null;
        }

        return $this->icon;
    }

    /**
     * Set size as string or Closure.
     * Closure signature: fn(Model $record): string
     */
    public function size(string|Closure|null $size): static
    {
        if ($size instanceof Closure) {
            $this->sizeCallback = $size;
        } else {
            $this->size = $size;
            $this->sizeCallback = null;
        }

        return $this;
    }

    /**
     * Resolve size - evaluates Closure if set.
     */
    public function getSize(mixed $context = null): string
    {
        if ($this->shouldInvokeDynamicCallback($this->sizeCallback, $context)) {
            return ($this->sizeCallback)($context);
        }

        return $this->size ?? 'sm';
    }

    /**
     * Set extra attributes as array or Closure.
     * Closure signature: fn(Model $record): array
     *
     * @param  array<string, string>|Closure  $attributes
     */
    public function extraAttributes(array|Closure $attributes): static
    {
        if ($attributes instanceof Closure) {
            $this->extraAttributesCallback = $attributes;
        } else {
            $this->extraAttributes = $attributes;
            $this->extraAttributesCallback = null;
        }

        return $this;
    }

    /**
     * Resolve extra attributes - evaluates Closure if set.
     *
     * @return array<string, string>
     */
    public function getExtraAttributes(mixed $context = null): array
    {
        if ($this->shouldInvokeDynamicCallback($this->extraAttributesCallback, $context)) {
            return ($this->extraAttributesCallback)($context);
        }

        return $this->extraAttributes;
    }
}
