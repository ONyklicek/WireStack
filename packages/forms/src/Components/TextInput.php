<?php

declare(strict_types=1);

namespace NyonCode\WireForms\Components;

use Closure;
use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Foundation\Concerns\HasExtraInputAttributes;
use NyonCode\WireCore\Foundation\Contracts\DehydratesState;
use NyonCode\WireCore\Foundation\Support\EnumResolver;
/**
 * Text input field with type variants (email, password, tel, url, numeric, integer).
 *
 * Supports prefix/suffix, mask, datalist, input mode, autocomplete.
 */
use NyonCode\WireForms\Concerns\HasCharacterLimits;

class TextInput extends Field implements DehydratesState
{
    use HasCharacterLimits;
    use HasExtraInputAttributes;

    protected string $inputType = 'text';

    protected int|float|string|Closure|null $minValue = null;

    protected int|float|string|Closure|null $maxValue = null;

    protected int|float|string|null $step = null;

    protected ?string $mask = null;

    protected ?string $inputMode = null;

    protected ?string $autocomplete = null;

    /** @var array<int, string>|null */
    protected ?array $datalistOptions = null;

    protected bool $isRevealable = false;

    // ─── Type variants ─────────────────────────────────────────────

    /** Set the HTML input type directly. */
    public function type(string $type): static
    {
        $this->inputType = $type;

        return $this;
    }

    /** Type preset: email (sets the matching inputmode). */
    public function email(): static
    {
        $this->inputType = 'email';
        $this->inputMode = 'email';

        return $this;
    }

    /** Type preset: password (see `revealable()` for a show/hide toggle). */
    public function password(): static
    {
        $this->inputType = 'password';

        return $this;
    }

    /** Type preset: tel (sets the matching inputmode). */
    public function tel(): static
    {
        $this->inputType = 'tel';
        $this->inputMode = 'tel';

        return $this;
    }

    /** Type preset: url (sets the matching inputmode). */
    public function url(): static
    {
        $this->inputType = 'url';
        $this->inputMode = 'url';

        return $this;
    }

    /** Type preset: decimal number (type=number, decimal inputmode). */
    public function numeric(): static
    {
        $this->inputType = 'number';
        $this->inputMode = 'decimal';

        return $this;
    }

    /** Type preset: whole number (type=number, numeric inputmode, step 1). */
    public function integer(): static
    {
        $this->inputType = 'number';
        $this->inputMode = 'numeric';
        $this->step = 1;

        return $this;
    }

    /** Type preset: search. */
    public function search(): static
    {
        $this->inputType = 'search';

        return $this;
    }

    // ─── State ─────────────────────────────────────────────────────

    /**
     * A cleared number input stores null, not an empty string.
     *
     * An emptied `<input type="number">` submits `''`, and no numeric column can
     * hold that: MySQL in strict mode rejects the write outright ("Incorrect
     * decimal value: ''") and a lenient driver silently stores 0. Neither is
     * what an author who left an optional amount blank asked for. This is the
     * same rule Select already applies to its placeholder choice.
     *
     * Text is deliberately untouched: `''` is a legitimate string value, and
     * turning it into null would break a non-nullable column that holds one.
     */
    public function dehydrateState(mixed $state, ?Model $record = null): mixed
    {
        if ($this->inputType !== 'number') {
            return $state;
        }

        return (is_string($state) && trim($state) === '') ? null : $state;
    }

    // ─── Constraints ───────────────────────────────────────────────

    /** Set the minimum numeric value (a value or a `$get`-aware Closure). */
    public function minValue(int|float|string|Closure|null $value): static
    {
        $this->minValue = $value;

        return $this;
    }

    /** Set the maximum numeric value (a value or a `$get`-aware Closure). */
    public function maxValue(int|float|string|Closure|null $value): static
    {
        $this->maxValue = $value;

        return $this;
    }

    /** Set the numeric step increment. */
    public function step(int|float|string|null $step): static
    {
        $this->step = $step;

        return $this;
    }

    // ─── Extras ────────────────────────────────────────────────────

    /** Apply an input mask pattern to the field. */
    public function mask(?string $mask): static
    {
        $this->mask = $mask;

        return $this;
    }

    /** Set the HTML `inputmode` (the virtual-keyboard hint on mobile). */
    public function inputMode(?string $mode): static
    {
        $this->inputMode = $mode;

        return $this;
    }

    /** Set the HTML `autocomplete` attribute. */
    public function autocomplete(?string $value): static
    {
        $this->autocomplete = $value;

        return $this;
    }

    /**
     * Suggestion list for the input.
     *
     * Accepts a plain list of suggestion strings, or a backed/unit enum class — in which
     * case the enum's case labels (see {@see EnumResolver::options()}) become the suggestions.
     *
     * @param  array<int, string>|class-string  $options
     */
    public function datalist(array|string $options): static
    {
        if (EnumResolver::isEnumClass($options)) {
            $options = array_values(EnumResolver::options($options));
        }

        $this->datalistOptions = $options;

        return $this;
    }

    /** Add a show/hide toggle to the field (for `password()` inputs). */
    public function revealable(bool $condition = true): static
    {
        $this->isRevealable = $condition;

        return $this;
    }

    // ─── Getters ───────────────────────────────────────────────────

    public function getInputType(): string
    {
        return $this->inputType;
    }

    public function getMinValue(): int|float|string|null
    {
        return $this->evaluate($this->minValue);
    }

    public function getMaxValue(): int|float|string|null
    {
        return $this->evaluate($this->maxValue);
    }

    public function getStep(): int|float|string|null
    {
        return $this->step;
    }

    public function getMask(): ?string
    {
        return $this->mask;
    }

    public function getInputMode(): ?string
    {
        return $this->inputMode;
    }

    public function getAutocomplete(): ?string
    {
        return $this->autocomplete;
    }

    /**
     * @return array<int, string>|null
     */
    public function getDatalistOptions(): ?array
    {
        return $this->datalistOptions;
    }

    public function isRevealable(): bool
    {
        return $this->isRevealable;
    }

    protected function viewName(): string
    {
        return 'wire-forms::components.text-input';
    }
}
