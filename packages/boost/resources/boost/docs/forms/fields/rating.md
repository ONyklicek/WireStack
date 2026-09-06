---
summary: A star rating with optional half-star precision, stored as a number.
---

# Rating

A star rating. Reach for it when the value is a *score a person gives* — a
satisfaction, a priority, a review — and the row of stars is what makes it
answerable at a glance. For a score that is computed rather than given, a
`TextColumn::make()->numeric()` reads better than a control nobody may click.

```php
use NyonCode\WireForms\Components\Rating;
```

## How It Works

**The state is a number, and its type follows the precision.** A whole-star
field hydrates as `int`, a half-star one as `float` — `getStateType()` answers
`'int'` or `'float'` depending on `allowHalf()`, so a column typed `integer`
never receives `4.5` from a field that cannot produce it.

**Which half a click lands in is decided in the browser**, from the pointer's
offset inside the star: past the midpoint is the whole star, before it is the
half. That is why half-star precision needs no extra markup — the same star
element answers for both.

**Clicking the active star clears the rating**, unless `clearable(false)` says
otherwise. Without it a rating given by accident can only be changed, never
withdrawn, which is the one interaction a star row cannot express any other way.

**The colour is resolved in PHP, not in the view.** `getColorClasses()` maps the
name to the bright end of the canonical palette (`success` is emerald, not
green), and the default is the classic amber star rather than the theme's
primary — a rating reads as a rating in every theme.

**Zero is a real value, and it is not "empty".** An untouched field is `null`,
which `required()` refuses — but a *cleared* rating stores `0`, and Laravel's
`required` accepts a zero. A field that must actually hold a star wants
`->rules(['min:1'])` alongside it.

## Basic Usage

```php
Rating::make('score')
```

Five whole stars, amber, clearable.

## Half Stars

```php
Rating::make('rating')
    ->allowHalf()      // 0.5 increments: 1, 1.5, 2, 2.5 …
```

The state becomes a float; a column holding it has to accept one.

## A Different Scale

```php
Rating::make('priority')
    ->max(3)           // a three-star scale
```

## Colour

```php
Rating::make('satisfaction')
    ->color('success')   // 'primary' | 'success' | 'danger' | anything else → amber
```

## Not Clearable

```php
Rating::make('score')
    ->clearable(false)   // clicking the active star no longer resets it
```

## Extended Example

```php
use Livewire\Component;
use NyonCode\WireForms\Components\Rating;
use NyonCode\WireForms\Components\Textarea;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WireForms\Forms\WithForms;

class LeaveReview extends Component
{
    use WithForms;

    public array $data = [];

    public function form(Form $form): Form
    {
        return $form
            ->model(Review::class)
            ->statePath('data')
            ->schema([
                Rating::make('score')            // [tl! focus:start]
                    ->label('How was it?')
                    ->max(5)
                    ->allowHalf()
                    ->color('success')
                    ->required(),                // [tl! focus:end]
                Textarea::make('comment')
                    ->placeholder('Anything you would change?'),
            ]);
    }

    public function save(): void
    {
        $this->form->save();
    }
}
```

## Rating API

The rating surface. Label, hint, helper text, `required()`, `disabled()`,
`live()` and the rest are the shared field API, documented in
[Form Fields](index.md).

```php
->max(int $max)                        // number of stars — default 5, at least 1
->allowHalf(bool $condition = true)    // 0.5 increments; the state becomes a float
->color(string $color)                 // 'primary'|'success'|'danger' — default amber
->clearable(bool $condition = true)    // click the active star to reset to 0 — default true
->getMax(): int
->isAllowHalf(): bool
->getColor(): string
->isClearable(): bool
```

## Related

- [Form Fields](index.md) — the shared field API
- [Slider](slider.md) — the same "pick a number" question over a continuous range
- [RatingColumn](../../table/columns/rating.md) — the same score, displayed in a table
