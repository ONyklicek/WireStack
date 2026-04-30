<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Actions\Concerns;

use Closure;
use Illuminate\Support\Str;

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
        if ($this->labelCallback && $context) {
            return call_user_func($this->labelCallback, $context);
        }

        return $this->label ?? Str::headline($this->name);
    }

    /**
     * Set color as string or Closure.
     * Closure signature: fn(Model $record): string
     */
    public function color(string|Closure|null $color): static
    {
        if ($color instanceof Closure) {
            $this->colorCallback = $color;
        } else {
            $this->color = $color;
            $this->colorCallback = null;
        }

        return $this;
    }

    /**
     * Resolve color - evaluates Closure if set.
     */
    public function getColor(mixed $context = null): string
    {
        if ($this->colorCallback && $context) {
            return call_user_func($this->colorCallback, $context);
        }

        return $this->color ?? 'primary';
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
        if ($this->tooltipCallback && $context) {
            return call_user_func($this->tooltipCallback, $context);
        }

        return $this->tooltip;
    }

    /**
     * Set icon as string or Closure.
     * Closure signature: fn(Model $record): ?string
     */
    public function icon(string|Closure|null $icon, ?string $position = 'before'): static
    {
        if ($icon instanceof Closure) {
            $this->iconCallback = $icon;
        } else {
            $this->icon = $icon;
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
        if ($this->iconCallback && $context) {
            return call_user_func($this->iconCallback, $context);
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
        if ($this->sizeCallback && $context) {
            return call_user_func($this->sizeCallback, $context);
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
        if ($this->extraAttributesCallback && $context) {
            return call_user_func($this->extraAttributesCallback, $context);
        }

        return $this->extraAttributes ?? [];
    }
}
