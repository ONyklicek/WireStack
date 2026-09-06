<?php

declare(strict_types=1);

namespace NyonCode\WireForms\Components;

use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Foundation\Concerns\HasExtraInputAttributes;
use NyonCode\WireCore\Foundation\Contracts\DehydratesState;
use NyonCode\WireCore\Foundation\Contracts\HydratesState;
use NyonCode\WireCore\Foundation\ValueObjects\DialingCode;
use NyonCode\WireCore\Foundation\ValueObjects\DialingCodes;
use NyonCode\WireForms\Contracts\ProvidesImplicitValidationRules;
use NyonCode\WireForms\Validation\Rules\PhoneNumber;

/**
 * An international phone number: a dialling-code select beside the national
 * number, over a single state key.
 *
 * The two controls are one value. State holds the written international number
 * — `+420 123 456 789` — and the controller splits it for display and puts it
 * back together on every keystroke, so nothing downstream has to know the field
 * had two inputs. What is stored is that same number without the spacing,
 * which is E.164: `+420123456789`.
 *
 * Keeping the prefix *inside* the value is what makes this work over one
 * column. A field that kept the country separately would need a second column
 * to persist it, or would silently lose it — a number whose prefix is only in
 * the UI cannot be dialled from the database.
 *
 * `PhoneInput::make('phone')->countries(['CZ', 'SK', 'DE'])`
 */
class PhoneInput extends Field implements DehydratesState, HydratesState, ProvidesImplicitValidationRules
{
    use HasExtraInputAttributes;

    /** @var array<int, string> */
    protected array $countries = [];

    protected ?string $defaultCountry = null;

    /**
     * The countries the select offers, as ISO 3166-1 alpha-2 codes, in the
     * order they are listed. Unlisted ones are dropped; leaving this unset
     * offers the whole {@see DialingCodes} table.
     *
     * @param  array<int, string>  $countries
     */
    public function countries(array $countries): static
    {
        $this->countries = $countries;

        return $this;
    }

    /**
     * The country the select opens on while the field is still empty (the first
     * offered country by default).
     */
    public function defaultCountry(string $country): static
    {
        $this->defaultCountry = strtoupper($country);

        return $this;
    }

    // ─── Getters ───────────────────────────────────────────────────

    /**
     * The dialling codes this field offers.
     *
     * @return array<int, DialingCode>
     */
    public function getCountries(): array
    {
        $countries = $this->countries !== []
            ? $this->countries
            : (array) config('wire-forms.phone.countries', []);

        return $countries === []
            ? DialingCodes::all()
            : DialingCodes::only($countries);
    }

    /**
     * The country an empty field starts on: the stated one, else the
     * application's (`wire-forms.phone.default_country`), else the first
     * offered. A default the field does not offer is ignored rather than
     * silently added to the list.
     */
    public function getDefaultCountry(): ?DialingCode
    {
        $offered = $this->getCountries();
        $wanted = $this->defaultCountry ?? config('wire-forms.phone.default_country');

        if (is_string($wanted)) {
            foreach ($offered as $code) {
                if ($code->country === strtoupper($wanted)) {
                    return $code;
                }
            }
        }

        return $offered[0] ?? null;
    }

    /**
     * The select's options as the controller reads them — country, prefix and
     * the label PHP already resolved, so the browser never rebuilds a flag.
     *
     * @return array<int, array{country: string, dialingCode: string, label: string}>
     */
    public function getCountryOptions(): array
    {
        return array_map(static fn (DialingCode $code): array => [
            'country' => $code->country,
            'dialingCode' => $code->dialingCode,
            'label' => $code->label(),
        ], $this->getCountries());
    }

    // ─── State ─────────────────────────────────────────────────────

    /** A stored E.164 number → the spaced form the field writes. */
    public function hydrateState(mixed $value, ?Model $record = null): mixed
    {
        $number = is_scalar($value) ? trim((string) $value) : '';

        return $number === '' ? null : DialingCodes::written($number);
    }

    /** The written number → E.164, which is the number without its spacing. */
    public function dehydrateState(mixed $state, ?Model $record = null): mixed
    {
        $number = is_scalar($state) ? trim((string) $state) : '';
        $digits = (string) preg_replace('/\D/', '', $number);

        if ($digits === '') {
            return null;
        }

        return '+'.$digits;
    }

    /**
     * A phone number is text with a shape, and the shape is the validation:
     * {@see PhoneNumber} checks the prefix against what this field offers and
     * the national part against the digit count that country issues.
     *
     * @return array<int, mixed>
     */
    public function implicitValidationRules(): array
    {
        $rule = new PhoneNumber($this->getCountries());

        return $this->isRequired() ? [$rule] : ['nullable', $rule];
    }

    protected function viewName(): string
    {
        return 'wire-forms::components.phone-input';
    }
}
