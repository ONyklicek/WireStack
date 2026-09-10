---
summary: A code typed into separate boxes, stored as one plain string.
---

# OtpInput

A one-time code or a PIN, typed into separate boxes that advance themselves.
Reach for it when the value is a *code of known length* — a verification code, a
PIN, a backup code — where the box count tells the user how much is expected
before they start typing.

```php
use NyonCode\WireForms\Components\OtpInput;
```

## How It Works

**The boxes are a presentation; the value is one string.** N single-character
inputs are joined in the browser and written to state as `'283041'`, so
validation, storage and every consumer downstream see an ordinary string.

**Focus moves with the code.** Typing advances to the next box, Backspace on an
empty box steps back into the previous one, and the arrow keys walk the row —
the code is never trapped in a box the user cannot leave with the keyboard.

**A pasted code fills the row.** Whitespace is stripped, the code is cut to the
field's length and the caret lands on the last filled box, which is what makes
"copy from the SMS, paste" work in one gesture instead of six.

**On a form the browser posts itself, the boxes are the enhancement.** They
carry no `name` — six inputs would post six values — so in
[native-submit mode](../overview.md#rendering) the field renders one real text
input beside them, carrying the field's name and the value the boxes join.
Alpine hides that input (`x-show="false"`, not `type="hidden"`) and cloaks the
boxes until it has booted, which means a browser with no JavaScript shows the
plain input and posts from it. That is what keeps a two-factor challenge
answerable when Alpine never arrives, and it is how the
[auth module](../../modules/auth.md#the-fields) renders the code from an
authenticator app. The first box carries `autocomplete="one-time-code"`, so the
platform offers the code straight from the message.

**The length is structural, not a validation rule.** `length()` decides how many
boxes render, and the field refuses a length below 1 — a zero-length OTP renders
no boxes at all, which looks like a styling bug rather than a configuration one.
The same guard covers `separator()`.

**`numericOnly()` filters, and refuses without deleting.** It sets
`inputmode="numeric"` so a phone opens the digit pad, and drops non-digits as
they arrive — typed, or hidden inside a pasted string, where the digits are kept
and the rest falls away. A rejected keystroke leaves the digit that was already
in the box alone: a box is selected on focus, so a typo would otherwise wipe a
valid digit. What counts as a *valid code* is still a rule you write
(`->rules(['digits:6'])`).

## Basic Usage

```php
OtpInput::make('code')
    ->length(6)
```

The stored value is a plain string: `'283041'`.

## Digits Only

```php
OtpInput::make('pin')
    ->length(4)
    ->numericOnly()
```

## Masked

```php
OtpInput::make('pin')
    ->length(4)
    ->masked()      // the characters render like a password field
```

## A Visual Separator

```php
OtpInput::make('code')
    ->length(6)
    ->separator(3)  // [x][x][x] — [x][x][x]
```

## Extended Example

```php
use Livewire\Component;
use NyonCode\WireForms\Components\OtpInput;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WireForms\Forms\WithForms;

class ConfirmLogin extends Component
{
    use WithForms;

    public array $data = [];

    public function form(Form $form): Form
    {
        return $form
            ->statePath('data')
            ->schema([
                OtpInput::make('verification_code')      // [tl! focus:start]
                    ->label('Code from the SMS')
                    ->length(6)
                    ->numericOnly()
                    ->separator(3)
                    ->required()
                    ->rules(['digits:6'])
                    ->live(),                            // [tl! focus:end]
            ]);
    }

    public function confirm(): void
    {
        $data = $this->form->validate();

        // $data['verification_code'] === '283041'
    }
}
```

`live()` is what lets a host act on the last keystroke — check the code as soon
as the row is full, instead of waiting for a submit button.

## OtpInput API

The code surface. Label, hint, `required()`, `rules()`, `disabled()`, `live()`
and the rest are the shared field API, documented in [Form Fields](index.md).

```php
->length(int $length)                    // number of boxes — default 6, at least 1
->numericOnly(bool $condition = true)    // inputmode="numeric", digits only
->masked(bool $condition = true)         // render the characters like a password
->separator(int $after)                  // a dash after every N boxes, at least 1
->getLength(): int
->isNumericOnly(): bool
->isMasked(): bool
->getSeparator(): ?int
```

## Related

- [Form Fields](index.md) — the shared field API
- [TextInput](text-input.md) — for a code whose length is not fixed
- [Validation](../validation.md) — where `digits:6` and friends belong
