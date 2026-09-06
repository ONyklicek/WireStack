---
summary: A dialling-code select beside a national number, stored as one E.164 string.
---

# PhoneInput

An international phone number: the country prefix is picked from a list, the
rest is typed, and the two are one value. Reach for it when the number will be
dialled, exported or matched later — a `TextInput::make()->tel()` accepts
anything a person types, including a national number nobody outside that country
can call.

```php
use NyonCode\WireForms\Components\PhoneInput;
```

## How It Works

**The prefix lives inside the value, not beside it.** State holds the written
international number — `+420 123 456 789` — and the controller splits it for
display on every render, putting it back together on every keystroke. A field
that kept the country separately would need a second column to persist it, or
would lose it: a number whose prefix exists only in the UI cannot be dialled
from the database.

**What is stored is the same number without its spacing**, which is E.164:
`+420123456789`. The spacing is presentation, applied by `hydrateState()` on the
way in and stripped by `dehydrateState()` on the way out.

**Grouping is applied in threes, and a trailing single digit joins the group
before it** — `+1 212 555 1234`, not `+1 212 555 123 4`. The controller writes
the same grouping in the browser as PHP does on the server.

**The national part is not reformatted while it is being typed.** Regrouping on
every keystroke moves the caret to the end of the input, which makes editing the
middle of a number impossible.

**The country list is a curated table** (`Support\DialingCodes`), not a copy of
the ITU register: each entry carries the dialling code and the digit range that
country issues, which is what makes a typed number checkable at all. Options
read as a flag and a prefix — `🇨🇿 +420` — and the flag is computed from the ISO
code rather than stored, so it cannot fall out of step with it.

**A prefix is not unique.** `+1` is the whole North American plan, so matching a
number to a country answers with the first entry holding that prefix. The choice
is cosmetic: it decides which flag the select shows, never whether the number is
valid, because countries sharing a prefix share its digit range.

**Validation asks three questions in order**, and each has its own message: is
the number international at all (a leading `+`), is its prefix one this field
offers, and does the national part have as many digits as that country issues. A
country outside the table is held to E.164's own bounds — 4 to 15 digits — which
is the most a table-free check can say.

**The offer can be set once, for the whole application.** `config('wire-forms.phone.countries')`
and `default_country` are what a field falls back to; naming them per field is for the exceptions.

## Basic Usage

```php
PhoneInput::make('phone')
```

Every country in the table, opening on Czechia.

## Restricting The Countries

```php
PhoneInput::make('phone')
    ->countries(['CZ', 'SK', 'DE', 'AT'])   // in the order they are listed
```

The list is also the validation: a `+49` number is rejected by a field that
offers only `CZ` and `SK`. An ISO code the table does not carry is dropped
rather than offered as a dead option.

## Choosing Where It Opens

```php
PhoneInput::make('phone')
    ->countries(['CZ', 'SK', 'DE'])
    ->defaultCountry('DE')      // the first offered country by default
```

The default applies while the field is empty; a field holding a number opens on
that number's country.

## Extended Example

```php
use Livewire\Component;
use NyonCode\WireForms\Components\PhoneInput;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WireForms\Forms\WithForms;

class EditContact extends Component
{
    use WithForms;

    public array $data = [];

    public function mount(Contact $contact): void
    {
        $this->form->fill($contact->attributesToArray());
    }

    public function form(Form $form): Form
    {
        return $form
            ->model(Contact::class)
            ->statePath('data')
            ->schema([
                TextInput::make('name')->required(),
                PhoneInput::make('phone')                  // [tl! focus:start]
                    ->countries(['CZ', 'SK', 'PL', 'DE'])
                    ->defaultCountry('CZ')
                    ->required(),                          // [tl! focus:end]
            ]);
    }
}
```

The column holds `+420123456789`, ready to be dialled, exported or matched
against another system's number without normalising it first.

## PhoneInput API

The phone surface. Label, hint, placeholder, visibility and the rest are the
shared field API, documented in [Form Fields](index.md).

```php
->countries(array $countries)       // ISO 3166-1 alpha-2 codes — default: config('wire-forms.phone.countries')
->defaultCountry(string $country)   // where an empty field opens — default: config('wire-forms.phone.default_country')
->getCountries(): array             // array<int, DialingCode>
->getDefaultCountry(): ?DialingCode
->getCountryOptions(): array        // array<int, array{country: string, dialingCode: string, label: string}>
```

## Related

- [Form Fields](index.md) — the shared field API
- [TextInput](text-input.md) — for a number that is never dialled programmatically
- [Validation](../validation.md) — how implicit rules join the ones you write
