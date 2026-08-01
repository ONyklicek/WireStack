<?php

declare(strict_types=1);

namespace NyonCode\WireForms\Concerns;

use Closure;
use NyonCode\WireCore\Foundation\Colors\Color;
use NyonCode\WireCore\Foundation\Concerns\HasColor;
use NyonCode\WireCore\Foundation\Concerns\HasSize;
use NyonCode\WireCore\Foundation\Support\EnumResolver;

/**
 * The shared visual vocabulary of a choice field — the `segmented` / `buttons`
 * variants, per-option icons and colors, and the accent applied to the selected
 * option.
 *
 * Owned once so a single-choice field (Radio) and a multiple-choice field
 * (CheckboxList) offer the *same* API and render the same chrome; only the
 * underlying input type differs. Variants a given field adds on top of these
 * (Radio's `cards`) stay with that field.
 *
 * The consuming field must also use {@see HasSize} — the size classes below are
 * resolved through it — and expose `$options` (from {@see HasOptions}).
 */
trait HasChoiceVariants
{
    protected string $variant = 'default';

    /** @var array<string|int, string>|Closure */
    protected array|Closure $icons = [];

    /** Accent color of the selected option across every variant. */
    protected string $color = 'primary';

    /** @var array<string|int, string>|Closure */
    protected array|Closure $colors = [];

    protected bool $inline = false;

    /** Render the options as a compact segmented control (pill over a shared track). */
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

    /** Lay the options out horizontally instead of stacked. */
    public function inline(bool $condition = true): static
    {
        $this->inline = $condition;

        return $this;
    }

    /**
     * Per-option icons keyed by option value (`[value => iconName]`).
     *
     * @param  array<string|int, string>|Closure  $icons
     */
    public function icons(array|Closure $icons): static
    {
        $this->icons = $icons;

        return $this;
    }

    /** Accent color of the selected option, applied across every variant. */
    public function color(string|Color $color): static
    {
        $this->color = $color instanceof Color ? $color->value : $color;

        return $this;
    }

    /**
     * Per-option accent colors keyed by option value (`[value => color]`).
     *
     * @param  array<string|int, string>|Closure  $colors
     */
    public function colors(array|Closure $colors): static
    {
        $this->colors = $colors;

        return $this;
    }

    public function getVariant(): string
    {
        return $this->variant;
    }

    public function isSegmented(): bool
    {
        return $this->variant === 'segmented';
    }

    public function isButtons(): bool
    {
        return $this->variant === 'buttons';
    }

    public function isInline(): bool
    {
        return $this->inline;
    }

    public function getColor(): string
    {
        return $this->color;
    }

    /**
     * Per-option icons keyed by option value.
     *
     * When {@see HasOptions::options()} is an enum class implementing the opt-in
     * `HasIcon` contract, each case's icon is derived automatically through the
     * canonical {@see EnumResolver::icons()} owner. An icon set explicitly via
     * {@see icons()} overrides the enum-derived one for that value.
     *
     * @return array<string|int, string>
     */
    public function getIcons(): array
    {
        return $this->mergeEnumDerived(EnumResolver::icons(...), $this->icons);
    }

    /**
     * Resolved per-option color map (enum `HasColor` derivation merged under any
     * explicit {@see colors()} entries, which win).
     *
     * @return array<string|int, string>
     */
    public function getColors(): array
    {
        return $this->mergeEnumDerived(EnumResolver::colors(...), $this->colors);
    }

    /**
     * Peer-checked class bundle for the selected option, keyed by sub-surface,
     * from the canonical {@see HasColor::getChoiceColorClasses()} owner.
     *
     * @return array{input:string, solid:string, text:string, card:string, indicator:string}
     */
    public function getColorClasses(): array
    {
        return HasColor::getChoiceColorClasses($this->color);
    }

    /**
     * Peer-checked class bundle for one option: its per-option {@see colors()}
     * entry (or enum-derived color) when set, otherwise the group accent.
     *
     * @return array{input:string, solid:string, text:string, card:string, indicator:string}
     */
    public function getColorClassesFor(string|int $value): array
    {
        return HasColor::getChoiceColorClasses($this->getColors()[$value] ?? $this->color);
    }

    /**
     * Canonical padding/font/gap classes for the button-like variants, matched to
     * `getSize()` through the shared {@see HasSize} owner.
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
     * Merge an enum-derived per-option map under the explicitly configured one.
     *
     * array_replace, not array_merge: the enum maps are keyed by the case's
     * scalar value, which is an integer for an int-backed enum. array_merge
     * renumbers integer keys from 0, breaking the lookup against the option value
     * (which getOptions() keeps as the real int). array_replace keeps the keys
     * and still lets the explicit entries win.
     *
     * @param  callable(class-string): array<string|int, string>  $derive
     * @param  array<string|int, string>|Closure  $explicit
     * @return array<string|int, string>
     */
    private function mergeEnumDerived(callable $derive, array|Closure $explicit): array
    {
        $options = $this->evaluate($this->options);

        $derived = EnumResolver::isEnumClass($options) ? $derive($options) : [];

        return array_replace($derived, $this->evaluate($explicit));
    }
}
