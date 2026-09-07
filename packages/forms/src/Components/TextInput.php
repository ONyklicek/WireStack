<?php

declare(strict_types=1);

namespace NyonCode\WireForms\Components;

use Closure;
use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Foundation\Concerns\CanBeNullable;
use NyonCode\WireCore\Foundation\Concerns\HasExtraInputAttributes;
use NyonCode\WireCore\Foundation\Contracts\DehydratesState;
use NyonCode\WireCore\Foundation\Support\EnumResolver;
use NyonCode\WireForms\Concerns\HasCharacterLimits;
use NyonCode\WireForms\Exceptions\FormConfigurationException;
use NyonCode\WireForms\Support\FieldBounds;

class TextInput extends Field implements DehydratesState
{
    use CanBeNullable;
    use HasCharacterLimits;
    use HasExtraInputAttributes;

    protected string $inputType = 'text';

    protected int|float|string|Closure|null $minValue = null;

    protected int|float|string|Closure|null $maxValue = null;

    protected int|float|string|null $step = null;

    protected ?string $mask = null;

    protected ?string $dynamicMask = null;

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

    // ─── Constraints ───────────────────────────────────────────────

    /**
     * Set the minimum numeric value (a value or a `$get`-aware Closure).
     *
     * @throws FormConfigurationException When it exceeds a literal maxValue().
     */
    public function minValue(int|float|string|Closure|null $value): static
    {
        // Only a pair of literal numbers can be compared here. A Closure needs a
        // record to evaluate against and a string may be a date or a datetime-local
        // bound, which this does not try to parse — those stay the caller's to keep
        // consistent, and the browser rejects the obvious cases anyway.
        FieldBounds::assertOrdered(
            static::class,
            'minValue',
            self::comparableBound($value),
            'maxValue',
            self::comparableBound($this->maxValue),
        );

        $this->minValue = $value;

        return $this;
    }

    /**
     * Set the maximum numeric value (a value or a `$get`-aware Closure).
     *
     * @throws FormConfigurationException When it falls below a literal minValue().
     */
    public function maxValue(int|float|string|Closure|null $value): static
    {
        FieldBounds::assertOrdered(
            static::class,
            'minValue',
            self::comparableBound($this->minValue),
            'maxValue',
            self::comparableBound($value),
        );

        $this->maxValue = $value;

        return $this;
    }

    /**
     * Set the numeric step increment.
     *
     * @throws FormConfigurationException When a numeric step is not greater than 0.
     */
    public function step(int|float|string|null $step): static
    {
        // `'any'` is the one non-numeric step HTML defines, and it is the reason
        // this setter takes a string at all — so only a numeric one is checked.
        if (is_int($step) || is_float($step)) {
            FieldBounds::assertPositive(static::class, 'step', $step);
        }

        $this->step = $step;

        return $this;
    }

    /**
     * The bound as a number, or null when it is not one this can compare.
     */
    private static function comparableBound(int|float|string|Closure|null $value): int|float|null
    {
        if (is_int($value) || is_float($value)) {
            return $value;
        }

        return null;
    }

    // ─── Extras ────────────────────────────────────────────────────

    /** Apply an input mask pattern to the field. */
    public function mask(?string $mask): static
    {
        $this->mask = $mask;

        return $this;
    }

    /**
     * Apply a mask that is recomputed on every keystroke, written as the Alpine
     * expression `x-mask:dynamic` evaluates — `$money($input, ',', ' ')`,
     * `$input.startsWith('34') ? '9999 999999 99999' : '9999 9999 9999 9999'`.
     *
     * A pattern that never changes belongs in {@see mask()}; this one costs an
     * evaluation per keypress and takes precedence when both are set.
     */
    public function dynamicMask(?string $expression): static
    {
        $this->dynamicMask = $expression;

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

    public function getDynamicMask(): ?string
    {
        return $this->dynamicMask;
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

    // ─── Save path ─────────────────────────────────────────────────

    /**
     * A cleared input reaches the record as `null` when `''` cannot be what the
     * author meant.
     *
     * A `<input type=number>` submits `''` when it is emptied — there is no
     * other value a browser can send — and `''` is not a figure. Postgres and
     * strict-mode MySQL reject it on a numeric column; SQLite stores an empty
     * string next to the decimals. So a number input nullifies without being
     * asked, exactly as {@see Select} does for its empty option.
     *
     * Every other type has to be told with {@see nullable()}, because on a text
     * column `''` is a value an author may well mean, and a `NOT NULL` column
     * would reject the null a blanket rule wrote for them.
     */
    public function dehydrateState(mixed $state, ?Model $record = null): mixed
    {
        if ($state === '' && $this->inputType === 'number') {
            return null;
        }

        return $this->nullifyEmptyState($state);
    }

    protected function viewName(): string
    {
        return 'wire-forms::components.text-input';
    }
}
