<?php

declare(strict_types=1);

namespace NyonCode\WireForms\Components;

use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Foundation\Contracts\DehydratesState;
use NyonCode\WireCore\Foundation\Contracts\HydratesState;
use NyonCode\WireCore\Foundation\ValueObjects\MoneyFormat;
use NyonCode\WireForms\Contracts\ProvidesImplicitValidationRules;
use NyonCode\WireForms\Validation\Rules\MoneyAmount;

/**
 * An amount of money, typed the way it is written.
 *
 * The field holds the amount as the user reads it — `1 234,50` — and converts
 * at the state boundary: `hydrateState()` writes a stored number out in this
 * format, `dehydrateState()` reads the typed text back into a number (or into
 * whole minor units, for an integer column). Nothing downstream of the form
 * sees the grouping separators.
 *
 * The currency itself is never part of the text. It renders in the field's
 * affix — leading or trailing, per {@see currencyBefore()} — so the input holds
 * a figure and only a figure, which is what makes reading it back unambiguous.
 *
 * `MoneyInput::make('total')->currency('EUR')->currencyBefore()`
 *
 * @see MoneyFormat for the shared currency vocabulary, and `MoneyColumn` for
 * the display surface that reads from the same owner.
 */
class MoneyInput extends TextInput implements DehydratesState, HydratesState, ProvidesImplicitValidationRules
{
    protected ?string $currency = null;

    /**
     * Whether `currency()` was called at all.
     *
     * Separate from the value because `currency(null)` is a real choice — "a
     * bare figure, no currency" — and must not be mistaken for "unset", which
     * reads the application's default instead.
     */
    protected bool $currencyStated = false;

    protected ?int $moneyDecimals = null;

    protected ?string $decimalSeparator = null;

    protected ?string $thousandsSeparator = null;

    protected bool $currencyBefore = false;

    protected bool $storesMinorUnits = false;

    public function __construct(string $name)
    {
        parent::__construct($name);

        // Deliberately not type=number: it refuses a grouped value, and the
        // browsers that accept one do it per their own locale rather than this
        // field's. `decimal` still asks a phone for the right keypad.
        $this->inputMode = 'decimal';
    }

    /**
     * The currency the amount is written in — rendered in the field's affix,
     * and the source of the default precision.
     */
    public function currency(?string $currency, ?int $decimals = null): static
    {
        $this->currency = $currency;
        $this->currencyStated = true;

        if ($decimals !== null) {
            $this->moneyDecimals = $decimals;
        }

        return $this;
    }

    /** Write the amount with this many decimal places, whatever the currency's convention is. */
    public function decimals(int $decimals): static
    {
        $this->moneyDecimals = $decimals;

        return $this;
    }

    /** The characters that mark the decimals and group the thousands (`','` and a thin space by default). */
    public function separators(string $decimal, string $thousands): static
    {
        $this->decimalSeparator = $decimal;
        $this->thousandsSeparator = $thousands;

        return $this;
    }

    /** Read the currency before the amount (`$ 1 234,50`) rather than after it. */
    public function currencyBefore(bool $before = true): static
    {
        $this->currencyBefore = $before;

        return $this;
    }

    /**
     * Store the amount as whole minor units — hellers, cents — instead of a
     * decimal, for an integer column that avoids float rounding entirely.
     */
    public function storeAsMinorUnits(bool $condition = true): static
    {
        $this->storesMinorUnits = $condition;

        return $this;
    }

    // ─── Getters ───────────────────────────────────────────────────

    /** The stated currency, or the application's default (`wire-forms.money.currency`). */
    public function getCurrency(): ?string
    {
        return $this->currencyStated
            ? $this->currency
            : config('wire-forms.money.currency', 'CZK');
    }

    /** The stated decimal separator, or the application's (`wire-forms.money.decimal_separator`). */
    public function getDecimalSeparator(): string
    {
        return $this->decimalSeparator ?? config('wire-forms.money.decimal_separator', ',');
    }

    /** The stated thousands separator, or the application's (`wire-forms.money.thousands_separator`). */
    public function getThousandsSeparator(): string
    {
        return $this->thousandsSeparator ?? config('wire-forms.money.thousands_separator', ' ');
    }

    public function getDecimals(): int
    {
        return $this->getMoneyFormat()->getDecimals();
    }

    public function storesMinorUnits(): bool
    {
        return $this->storesMinorUnits;
    }

    /** This field's currency vocabulary — the one owner of how the amount is written and read. */
    public function getMoneyFormat(): MoneyFormat
    {
        return new MoneyFormat(
            $this->getCurrency(),
            $this->moneyDecimals,
            $this->getDecimalSeparator(),
            $this->getThousandsSeparator(),
            $this->currencyBefore,
        );
    }

    /**
     * The currency renders as the affix, so `prefix()` and `suffix()` stay the
     * owner's: an explicit one wins, and the currency fills whichever side is
     * still free.
     */
    public function getPrefix(): ?string
    {
        return parent::getPrefix() ?? ($this->currencyBefore ? $this->affixCurrency() : null);
    }

    public function getSuffix(): ?string
    {
        return parent::getSuffix() ?? ($this->currencyBefore ? null : $this->affixCurrency());
    }

    /**
     * Alpine's `$money` magic, configured from this field's format — the mask
     * groups the thousands as the user types, so the state is already written
     * the way {@see MoneyFormat::parse()} reads it back.
     */
    public function getDynamicMask(): ?string
    {
        return parent::getDynamicMask() ?? sprintf(
            "\$money(\$input, '%s', '%s', %d)",
            $this->getDecimalSeparator(),
            $this->getThousandsSeparator(),
            $this->getDecimals(),
        );
    }

    // ─── State ─────────────────────────────────────────────────────

    /**
     * Stored number → the written amount the input shows.
     *
     * The currency is left out: it is the affix, not part of the value.
     */
    public function hydrateState(mixed $value, ?Model $record = null): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        $format = $this->getMoneyFormat();

        $amount = $this->storesMinorUnits
            ? $format->fromMinorUnits((float) $value)
            : $format->parse($value);

        return $amount === null ? null : $format->amount($amount);
    }

    /** The written amount → the number to persist, in whichever unit the column holds. */
    public function dehydrateState(mixed $state, ?Model $record = null): mixed
    {
        $format = $this->getMoneyFormat();
        $amount = $format->parse(is_scalar($state) ? $state : null);

        if ($amount === null) {
            return null;
        }

        return $this->storesMinorUnits
            ? $format->toMinorUnits($amount)
            : round($amount, $format->getDecimals());
    }

    /**
     * The state is text, so `numeric` would reject the very grouping this field
     * writes. {@see MoneyAmount} validates the amount behind the text instead,
     * and carries `minValue()` / `maxValue()` — which on a text input would
     * otherwise be an HTML attribute the browser ignores.
     *
     * @return array<int, mixed>
     */
    public function implicitValidationRules(): array
    {
        $rule = new MoneyAmount(
            $this->getMoneyFormat(),
            $this->numericBound($this->getMinValue()),
            $this->numericBound($this->getMaxValue()),
        );

        return $this->isRequired() ? [$rule] : ['nullable', $rule];
    }

    /** A bound the rule can compare against, or null when it is not a number. */
    private function numericBound(int|float|string|null $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }

    /** The currency as an affix, or nothing when the field renders a bare figure. */
    private function affixCurrency(): ?string
    {
        $currency = $this->getCurrency();

        return (string) $currency === '' ? null : $currency;
    }
}
