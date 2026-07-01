<?php

declare(strict_types=1);

namespace NyonCode\WireForms\Components;

use Closure;
use NyonCode\WireCore\Foundation\Colors\Color;
use NyonCode\WireCore\Foundation\Concerns\HasColor;
use NyonCode\WireCore\Foundation\Concerns\HasSize;
use NyonCode\WireCore\Foundation\Support\EnumResolver;
use NyonCode\WireForms\Concerns\HasOptions;
use NyonCode\WireForms\Contracts\ProvidesImplicitValidationRules;

/**
 * Radio button group field.
 */
class Radio extends Field implements ProvidesImplicitValidationRules
{
    use HasOptions;
    use HasSize;

    /**
     * Visual variant of the choice group.
     *
     * - `default`: classic radio buttons with a label (and optional description).
     * - `cards`: each option is a selectable card (FluxUI-style). Combine with
     *   {@see inline()} for a horizontal row of cards or leave it off for a
     *   vertical stack. Pair with {@see icons()} for card icons and
     *   {@see hideIndicator()} for cards without the radio dot.
     * - `segmented`: a compact segmented control — a pill highlight slides over a
     *   shared track (FluxUI `segmented`).
     * - `buttons`: separate outlined buttons; the selected one is filled with the
     *   accent color. Stacks vertically by default, {@see inline()} for a row.
     *
     * Every variant renders per-option {@see icons()} when provided and tints the
     * selected option with {@see self::color()}.
     */
    protected string $variant = 'default';

    /** @var array<string, string>|Closure */
    protected array|Closure $descriptions = [];

    /** @var array<string|int, string>|Closure */
    protected array|Closure $icons = [];

    protected bool $indicator = true;

    protected bool $inline = false;

    protected bool $boolean = false;

    /** Accent color of the selected option across every variant. */
    protected string $color = 'primary';

    /** @var array<string|int, string>|Closure */
    protected array|Closure $colors = [];

    /**
     * @param  array<string, string>|Closure  $descriptions
     */
    public function descriptions(array|Closure $descriptions): static
    {
        $this->descriptions = $descriptions;

        return $this;
    }

    /**
     * Render each option as a selectable card (FluxUI-style).
     *
     * Cards stack vertically by default; call {@see inline()} for a horizontal
     * row. Combine with {@see icons()} and {@see hideIndicator()} to match the
     * "cards with icons" and "cards without indicators" variants.
     */
    public function cards(bool $condition = true): static
    {
        $this->variant = $condition ? 'cards' : 'default';

        return $this;
    }

    /**
     * Render the options as a compact segmented control (pill over a shared track).
     */
    public function segmented(bool $condition = true): static
    {
        $this->variant = $condition ? 'segmented' : 'default';

        return $this;
    }

    /**
     * Render the options as separate buttons; the selected one is filled with the
     * accent color. Stacks vertically by default; call {@see inline()} for a row.
     */
    public function buttons(bool $condition = true): static
    {
        $this->variant = $condition ? 'buttons' : 'default';

        return $this;
    }

    /**
     * Per-option icons keyed by option value (`[value => iconName]`).
     *
     * Icons render inside the `cards`, `segmented`, and `buttons` variants and alongside labels in
     * the default variant.
     *
     * @param  array<string|int, string>|Closure  $icons
     */
    public function icons(array|Closure $icons): static
    {
        $this->icons = $icons;

        return $this;
    }

    /**
     * Toggle the radio indicator (the dot) shown on card options.
     */
    public function indicator(bool $condition = true): static
    {
        $this->indicator = $condition;

        return $this;
    }

    /**
     * Hide the radio indicator on card options ("cards without indicators").
     */
    public function hideIndicator(): static
    {
        return $this->indicator(false);
    }

    public function inline(bool $condition = true): static
    {
        $this->inline = $condition;

        return $this;
    }

    public function boolean(bool $condition = true): static
    {
        $this->boolean = $condition;

        if ($condition) {
            $this->options([
                true => trans('wire-forms::fields.yes'),
                false => trans('wire-forms::fields.no'),
            ]);
        }

        return $this;
    }

    /**
     * @return array<string, string>
     */
    public function getDescriptions(): array
    {
        return $this->evaluate($this->descriptions);
    }

    /**
     * Per-option icons keyed by option value.
     *
     * When {@see options()} is an enum class implementing the opt-in `HasIcon`
     * contract, each case's icon is derived automatically (Filament-style) through
     * the canonical {@see EnumResolver::icons()} owner. Any icon set explicitly via
     * {@see icons()} overrides the enum-derived one for that value.
     *
     * @return array<string|int, string>
     */
    public function getIcons(): array
    {
        $options = $this->evaluate($this->options);

        $enumIcons = EnumResolver::isEnumClass($options)
            ? EnumResolver::icons($options)
            : [];

        return array_merge($enumIcons, $this->evaluate($this->icons));
    }

    public function getVariant(): string
    {
        return $this->variant;
    }

    public function isCards(): bool
    {
        return $this->variant === 'cards';
    }

    public function isSegmented(): bool
    {
        return $this->variant === 'segmented';
    }

    public function isButtons(): bool
    {
        return $this->variant === 'buttons';
    }

    public function hasIndicator(): bool
    {
        return $this->indicator;
    }

    /**
     * Canonical padding/font/gap classes for the button-like variants (segmented,
     * buttons), matched to {@see getSize()} through the shared {@see HasSize} owner.
     */
    public function getSizeClasses(): string
    {
        return self::getButtonSizeClasses($this->getSize());
    }

    /**
     * Tailwind icon-dimension classes matched to the field size, delegated to the
     * canonical {@see HasSize::getButtonIconSizeClasses()} owner.
     */
    public function getIconSizeClass(): string
    {
        return self::getButtonIconSizeClasses($this->getSize());
    }

    /**
     * Accent color of the selected option, applied across every variant.
     */
    public function color(string|Color $color): static
    {
        $this->color = $color instanceof Color ? $color->value : $color;

        return $this;
    }

    public function getColor(): string
    {
        return $this->color;
    }

    /**
     * Per-option accent colors keyed by option value (`[value => color]`).
     *
     * Each option's selected state uses its own color instead of the single group
     * {@see self::color()} — Filament-style, mirroring {@see icons()}. When {@see options()}
     * is an enum class implementing `HasColor`, per-case colors are derived automatically;
     * a value present here overrides the enum-derived one.
     *
     * @param  array<string|int, string>|Closure  $colors
     */
    public function colors(array|Closure $colors): static
    {
        $this->colors = $colors;

        return $this;
    }

    /**
     * Resolved per-option color map (enum `HasColor` derivation merged under any explicit
     * {@see colors()} entries, which win).
     *
     * @return array<string|int, string>
     */
    public function getColors(): array
    {
        $options = $this->evaluate($this->options);

        $enumColors = EnumResolver::isEnumClass($options)
            ? EnumResolver::colors($options)
            : [];

        return array_merge($enumColors, $this->evaluate($this->colors));
    }

    /**
     * Peer-checked class bundle for the selected option, keyed by sub-surface, from
     * the canonical {@see HasColor::getChoiceColorClasses()} owner. Uses the group
     * {@see Color()} accent.
     *
     * @return array{input:string, solid:string, text:string, card:string, indicator:string}
     */
    public function getColorClasses(): array
    {
        return HasColor::getChoiceColorClasses($this->color);
    }

    /**
     * Peer-checked class bundle for one option: its per-option {@see colors()} entry
     * (or enum-derived color) when set, otherwise the group {@see Color()} accent.
     *
     * @return array{input:string, solid:string, text:string, card:string, indicator:string}
     */
    public function getColorClassesFor(string|int $value): array
    {
        $color = $this->getColors()[$value] ?? $this->color;

        return HasColor::getChoiceColorClasses($color);
    }

    public function isInline(): bool
    {
        return $this->inline;
    }

    public function isBoolean(): bool
    {
        return $this->boolean;
    }

    protected function viewName(): string
    {
        return 'wire-forms::components.radio';
    }
}
