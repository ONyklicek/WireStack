---
summary: "A value carried through the form without a control: no label, no wrapper, no way to type in it."
---

# Hidden

A value the form carries but never asks about — the owner of a record, the type
of a polymorphic parent, a token. Reach for `Hidden` when the value belongs in
the saved record and the user has no business choosing it.

```php
use NyonCode\WireForms\Components\Hidden;
```

## How It Works

This is the field with the least going on, and understanding *how* it is invisible
is the whole page.

**It marks itself hidden in its constructor.** `Hidden::make('user_id')` comes
back with `isHidden()` already `true` — nothing else in the schema does that. And
because every render loop in the framework — the form body, `Grid`, `Section`,
`Fieldset`, `Flex`, `Tab`, `Repeater`, `Builder` — skips components whose
`isVisible()` is false, a hidden field is stepped over everywhere. In practice it
emits **no markup at all**, not even the `<input type="hidden">` its view
describes.

**Except on a form the browser posts itself.** In
[native-submit mode](../overview.md#rendering) there is no Livewire snapshot for
a value to travel in — the browser sends what is in the document and nothing
else — so the input stops being decoration and becomes the only way to carry the
value. A `Hidden` in a native form renders
`<input type="hidden" name="…" value="…">`, taking its value from `old()` and
then its `default()`, and nothing else about it changes: it is still invisible,
still not something the user is offered. This is what carries Fortify's reset
token on the [auth module's](../../modules/auth.md#the-fields) set-a-new-password
screen.

**The value lives in form state, not in the DOM**, and that is what actually makes
it work. When the form is filled, every field in the schema is seeded — its
`default()` when set, otherwise a type-correct blank — and that happens in PHP,
before anything is rendered. So the key exists in `$data`, travels in the Livewire
snapshot, is validated, and is written on save, without any element on the page.

Two consequences worth keeping in mind:

- **`->visible()` will not bring it back.** The constructor's `hidden()` is what
  the loops read; a field you want conditionally *shown* is an ordinary field with
  a condition, not a `Hidden`.
- **The user can still change it.** The value sits in the component's public state
  like every other field, so it is as trustworthy as anything else that came from
  the browser. A value that must not be tampered with belongs in `mutateFormDataBeforeSave()`
  or on the model — not in a hidden field.

**It still validates**, and it is the one invisible field that does. Every other
component whose `isVisible()` is false is skipped by the validation resolver, on
purpose: a `required()` on a field a condition has hidden must never block a
submit. A `Hidden` is a different kind of invisible — its value is filled,
carried in a snapshot the browser can edit, and written on save — so its rules
are the only thing between the record and whatever came back. A `Hidden` that
fails validation produces an error message with nowhere to render, so keep its
rules to things that cannot fail for a legitimate user.

## Basic Usage

```php
Hidden::make('user_id')
    ->default(fn () => auth()->id())
```

## A Constant The Record Needs

```php
Hidden::make('type')
    ->default('post')
    ->rules(['in:post,page'])   // [tl! focus]
```

## Extended Example

A comment form in a real Livewire host, where two values are carried and neither
is asked for:

```php
use Livewire\Component;
use NyonCode\WireForms\Components\Hidden;
use NyonCode\WireForms\Components\Textarea;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WireForms\Forms\WithForms;

class AddComment extends Component
{
    use WithForms;

    public Article $article;

    public ?array $data = [];

    public function form(Form $form): Form
    {
        return $form
            ->statePath('data')
            ->model(Comment::class)
            ->schema([
                Hidden::make('article_id')                          // [tl! focus:start]
                    ->default(fn (): int => $this->article->getKey()),

                Hidden::make('author_id')
                    ->default(fn (): int => auth()->id()),           // [tl! focus:end]

                Textarea::make('body')
                    ->label('Your comment')
                    ->rows(4)
                    ->required()
                    ->maxLength(2000),
            ])
            ->successMessage('Comment posted');
    }

    public function save(): void
    {
        $this->form->save();
    }
}
```

Both defaults are closures, so they are resolved when the form is filled rather
than when the schema is declared — which matters for `auth()->id()` in a component
that may be mounted before the user is resolved.

Because the value arrives from the browser like any other, an application that
must not trust `author_id` should set it on the model instead of carrying it here.

## Hidden API

`Hidden` adds no configuration of its own — it is the shared `Field` surface plus
an invisible constructor. Defaults, rules, validation messages and the rest are
in [Common Field API](index.md#common-field-api). The native-submit switch is
the form's, not the field's: `Form::nativeSubmit()` sets it on every field in the
schema.

## Related

- [Common Field API](index.md#common-field-api) — everything this field can do
- [Placeholder](placeholder.md) — the opposite: something shown that holds no value
- [Save lifecycle](../save-lifecycle.md) — where `mutateFormDataBeforeSave()` runs
- [Fields](index.md) — the full list
