<?php

declare(strict_types=1);

namespace NyonCode\WireForms\Components;

use Closure;
use NyonCode\WireCore\Foundation\Concerns\HasSize;
use NyonCode\WireForms\Concerns\HasChoiceVariants;
use NyonCode\WireForms\Concerns\HasOptions;
use NyonCode\WireForms\Contracts\ProvidesImplicitValidationRules;

/**
 * Radio button group field.
 *
 * Visual variants:
 *
 * - `default`: classic radio buttons with a label (and optional description).
 * - `cards`: each option is a selectable card (FluxUI-style). Combine with
 *   {@see inline()} for a horizontal row of cards or leave it off for a vertical
 *   stack. Pair with {@see icons()} for card icons and {@see hideIndicator()}
 *   for cards without the radio dot.
 * - `segmented`: a compact segmented control — a pill highlight slides over a
 *   shared track (FluxUI `segmented`).
 * - `buttons`: separate outlined buttons; the selected one is filled with the
 *   accent color. Stacks vertically by default, {@see inline()} for a row.
 *
 * Every variant renders per-option {@see icons()} when provided and tints the
 * selected option with {@see color()}. `segmented` and `buttons` are the shared
 * {@see HasChoiceVariants} vocabulary; `cards` is Radio's own.
 */
class Radio extends Field implements ProvidesImplicitValidationRules
{
    // segmented()/buttons()/inline()/icons()/color()/colors() and their resolvers
    // are the choice vocabulary shared with CheckboxList.
    use HasChoiceVariants;
    use HasOptions;
    use HasSize;

    /** @var array<string, string>|Closure */
    protected array|Closure $descriptions = [];

    protected bool $indicator = true;

    /**
     * Set per-option helper descriptions as a value => description map.
     *
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

    /**
     * Shorthand for a Yes/No radio group.
     *
     * Also wrote a $boolean flag until 2026-07-15 that only its own getter read.
     * Nothing needs it: the options below *are* the boolean-ness, and a flag
     * driving getStateType() would make the state a bool on load and a string
     * after a click — worse than not having it.
     */
    public function boolean(bool $condition = true): static
    {
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

    public function isCards(): bool
    {
        return $this->variant === 'cards';
    }

    public function hasIndicator(): bool
    {
        return $this->indicator;
    }

    protected function viewName(): string
    {
        return 'wire-forms::components.radio';
    }
}
