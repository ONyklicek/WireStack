# Select

Dropdown select with static or dynamic options, search, and multi-select.

```php
use NyonCode\WireForms\Components\Select;
```

## Basic Usage

```php
Select::make('role')
    ->options([
        'admin' => 'Administrator',
        'editor' => 'Editor',
        'user' => 'User',
    ])
```

## Dynamic Options

```php
Select::make('category_id')
    ->options(fn () => Category::pluck('name', 'id')->toArray())
    ->placeholder('Choose category')
```

## Enum Options

Pass a PHP enum class directly instead of an array — the cases are expanded to a
`value => label` map. The key is the backing value (or the case name for unit enums),
and the label comes from the enum's `getLabel()` when it implements the
`Foundation\Contracts\Enum\HasLabel` contract, falling back to a headline of the case name.

```php
use NyonCode\WireCore\Foundation\Contracts\Enum\HasLabel;

enum Status: string implements HasLabel
{
    case Draft = 'draft';
    case Published = 'published';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Published => 'Published',
        };
    }
}

Select::make('status')->options(Status::class)
// → ['draft' => 'Draft', 'published' => 'Published']
```

An enum without `HasLabel` still works — the case name is headlined for the label
(`LowPriority` → `Low Priority`). A closure returning an enum class is expanded too.

**Automatic validation.** A single-value `Select` (or [`Radio`](radio.md)) whose options come
from an enum is automatically constrained to those values with an `in:` rule — a submission
outside the enum is rejected without you restating it. It is skipped for `multiple()` selects
(array state) and when you declare your own `in:` / `Rule::in()` / `Rule::enum()` rule.

> The same `->options(Enum::class)` shorthand works on [`Radio`](radio.md),
> [`CheckboxList`](checkbox-list.md), table `SelectColumn`, and the table
> [`SelectFilter`](../../table/filters.md).

## Searchable

```php
Select::make('user_id')
    ->options(fn () => User::pluck('name', 'id')->toArray())
    ->searchable()
    ->noSearchResultsMessage('No users found')
    ->searchPrompt('Type to search...')
    ->loadingMessage('Loading...')
```

## Multi-Select

```php
Select::make('tags')
    ->multiple()
    ->maxItems(5)
    ->minItems(1)
    ->options([...])
```

## Relationship

```php
Select::make('author_id')
    ->relationship('author', 'name')
    ->searchable()
```

## Native vs Custom

```php
Select::make('country')
    ->native()          // browser-native <select>
    ->native(false)     // custom styled dropdown (default)
```

## Boolean Select

```php
Select::make('active')
    ->boolean()         // Yes/No options
```

## Disabled Options

Render specific options as non-selectable:

```php
Select::make('status')
    ->options([
        'draft'     => 'Draft',
        'review'    => 'In Review',
        'published' => 'Published',
        'archived'  => 'Archived',
    ])
    ->disabledOptions(['archived'])
```

Dynamic disabled options:

```php
Select::make('tier')
    ->options(Plan::pluck('name', 'id')->toArray())
    ->disabledOptions(fn () => Plan::unavailable()->pluck('id')->toArray())
```

## HTML in Options

```php
Select::make('color')
    ->allowHtml()
    ->options([
        'red' => '<span class="text-red-500">Red</span>',
    ])
```

## Methods

| Method | Type | Description |
|--------|------|-------------|
| `options(array\|string\|Closure)` | array | Static, dynamic, or enum-class options (`value => label`) |
| `searchable()` | bool | Enable option search |
| `multiple()` | bool | Allow multiple selections |
| `native()` | bool | Use the browser-native `<select>` element |
| `maxItems(int\|null)` | int | Maximum selected items (multi-select) |
| `minItems(int\|null)` | int | Minimum selected items (multi-select) |
| `disabledOptions(array\|Closure)` | array | Option keys that are rendered as disabled |
| `noSearchResultsMessage(string\|null)` | string | Message when search finds nothing |
| `loadingMessage(string\|null)` | string | Message while options are loading |
| `searchPrompt(string\|null)` | string | Prompt shown in the search box |
| `allowHtml()` | bool | Render option labels as HTML |
| `boolean()` | — | Shorthand for Yes/No options |
| `relationship(string, string)` | — | Load options from a relationship |
| `placeholder(string\|Closure)` | string | Empty/blank option label |
| `disabled(bool\|Closure)` | bool | Disable the select |
| `required()` | — | Mark as required |
| `live()` | — | Trigger Livewire update on change |

See [Common Field API](index.md#common-field-api) for label, hint, tooltip, and other shared methods.
