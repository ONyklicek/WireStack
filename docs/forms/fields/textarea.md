---
summary: Multi-line text, with the row count and the resize behaviour it is given.
---

# Textarea

Multi-line text. Reach for `Textarea` whenever the answer is a sentence or more —
a note, a description, an address. For one line use
[`TextInput`](text-input.md); for text the user should be able to *format*, use
the [rich](rich-editor.md) or [markdown](markdown-editor.md) editor instead.

```php
use NyonCode\WireForms\Components\Textarea;
```

## How It Works

A textarea is a **field**: a string at its state path, validated like any other.
It renders a plain `<textarea>` — no editor, no toolbar — which is exactly why it
is the right default for free text you do not want marked up.

**`rows()` sets the starting height, not a limit.** It emits the HTML `rows`
attribute and defaults to `3`. A user can always drag the corner to make it
bigger; nothing here stops them.

**`autosize()` takes the height over.** It adds a tiny Alpine handler that sets
`style.height` to the content's `scrollHeight` on every input — and, because it
also runs on `x-init`, from the first paint. An inline height beats the `rows`
attribute, so with autosize on, `rows()` only governs the moment before Alpine
boots. The box grows *and* shrinks with the text.

**`cols()` is almost never what you want.** It emits the `cols` attribute, but the
element carries `w-full`, and a CSS width beats an HTML column count. Use
`columnSpan()` on a [`Grid`](../../core/schema/layout/grid.md) to make a textarea
narrower, not `cols()`.

**`spellcheck()` is three-valued.** `null` — the default — omits the attribute
entirely and lets the browser and OS decide. `true` and `false` force it. This is
the difference between "no opinion" and "off", and only the second one stops a
browser underlining a field full of product codes.

**`minLength()` / `maxLength()` do two things at once.** They emit the HTML
attributes *and* add the `min` / `max` validation rules, so the limit is enforced
on the server as well as hinted in the browser. They are also checked at build
time: a negative length, or a `minLength` above the `maxLength`, throws
`FormConfigurationException` when the form is composed rather than failing quietly
at validation.

## Basic Usage

```php
Textarea::make('description')
    ->label('Description')
    ->rows(5)
    ->maxLength(1000)
```

## Growing With The Text

```php
Textarea::make('notes')
    ->autosize()    // [tl! focus]
    ->rows(3)       // the height before Alpine takes over
```

## Turning Spellcheck Off

```php
Textarea::make('sku_list')
    ->label('SKUs, one per line')
    ->spellcheck(false)   // [tl! focus]
    ->autosize()
```

## Live Updates

A textarea on `live()` sends a round trip per keystroke, so give it a debounce:

```php
Textarea::make('bio')
    ->live()
    ->debounce(500)   // [tl! focus]
```

## Extended Example

An article form in a real Livewire host — a one-line title, a short summary with
a hard limit, and a body that grows as it is typed:

```php
use Livewire\Component;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireForms\Components\Textarea;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WireForms\Forms\WithForms;

class EditArticle extends Component
{
    use WithForms;

    public ?array $data = [];

    public function form(Form $form): Form
    {
        return $form
            ->statePath('data')
            ->model(Article::class)
            ->schema([
                TextInput::make('title')->required(),

                Textarea::make('summary')                            // [tl! focus:start]
                    ->label('Summary')
                    ->helperText('Shown in listings and search results.')
                    ->rows(2)
                    ->maxLength(160)
                    ->required(),

                Textarea::make('body')
                    ->label('Body')
                    ->autosize()
                    ->rows(8)
                    ->minLength(50),                                  // [tl! focus:end]
            ])
            ->successMessage('Article saved');
    }

    public function save(): void
    {
        $this->form->save();
    }
}
```

The summary is capped at 160 characters in the browser *and* on the server; the
body cannot be shorter than 50 and grows as it is written.

## Textarea API

```php
->rows(int $rows)                     // starting height in rows — default 3
->cols(?int $cols)                    // the HTML cols attribute; w-full usually wins — prefer columnSpan()
->autosize(bool $condition = true)    // grow and shrink with the content — default false
->spellcheck(?bool $condition = true) // true|false forces it; null (default) leaves it to the browser
->minLength(?int $length)             // HTML attribute AND the `min` validation rule
->maxLength(?int $length)             // HTML attribute AND the `max` validation rule
->getRows(): int
->getCols(): ?int
->isAutosize(): bool
->getSpellcheck(): ?bool
->getMinLength(): ?int
->getMaxLength(): ?int
```

Labels, help text, placeholder, prefixes, visibility, defaults, validation and
`live()` are shared by every field — see
[Common Field API](index.md#common-field-api).

## Related

- [TextInput](text-input.md) — the same string on one line
- [RichEditor](rich-editor.md) and [MarkdownEditor](markdown-editor.md) — when the text carries formatting
- [Grid](../../core/schema/layout/grid.md) — where `columnSpan()` decides the width
- [Reactive fields](../reactive-fields.md) — `live()` and its debounce
