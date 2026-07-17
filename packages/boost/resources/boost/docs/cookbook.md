---
order: 80
---

# Cookbook

Task-oriented recipes built from the public API. Each one is self-contained —
copy it into a Livewire component using `WithForms` (or `WithTable`) and adapt.

| I want to… | Recipe |
|------------|--------|
| Show a field only when another field has a value | [Conditional fields](#conditional-fields) |
| Transform data before it is saved | [Hash a password on save](#hash-a-password-on-save) |
| Load an existing record into a form | [Edit an existing record](#edit-an-existing-record) |
| Replace the default persistence | [Custom save logic](#custom-save-logic) |
| Run side effects after a successful save | [Side effects after save](#side-effects-after-save) |
| Scope a table to the current user/tenant | [Scope a table query](#scope-a-table-query) |
| Pre-fill defaults from the request or auth user | [Dynamic defaults](#dynamic-defaults) |

---

## Conditional Fields

Make the controlling field `live()` so Livewire re-renders on change, then read
the form state in a `visible()` closure. State lives on the component property
bound by `statePath`, so a closure defined in `form()` can read `$this->data`.

```php
public ?array $data = [];

public function form(Form $form): Form
{
    return $form->statePath('data')->schema([
        Select::make('account_type')
            ->options(['personal' => 'Personal', 'business' => 'Business'])
            ->live()
            ->required(),

        TextInput::make('company_name')
            ->label('Company name')
            ->visible(fn () => ($this->data['account_type'] ?? null) === 'business')
            ->required(),
    ]);
}
```

The closure is an arrow function declared inside `form()`, so its `$this` is the
Livewire component. Use `hidden()` for the inverse, or return any boolean
expression for more complex rules.

---

## Hash a Password on Save

`mutateDataBeforeSave()` transforms the validated data array immediately before
persistence — the right place for hashing, normalizing, or injecting fields.

```php
use Illuminate\Support\Facades\Hash;

$form
    ->model(User::class)
    ->schema([
        TextInput::make('email')->email()->required(),
        TextInput::make('password')->password()->required()->rules(['confirmed']),
    ])
    ->mutateDataBeforeSave(fn (array $data): array => [
        ...$data,
        'password' => Hash::make($data['password']),
    ]);
```

To apply the same rule to **every** form in the app, use the `form.saving`
plugin hook instead — see [Core Plugins](core/plugins.md#hook-system).

---

## Edit an Existing Record

Pass a model **instance** (not a class string) and fill the form from it. In edit
mode `save()` calls `update()` on the record.

```php
public User $user;

public ?array $data = [];

public function mount(User $user): void
{
    $this->user = $user;
    $this->form->fill($user->toArray());
}

public function form(Form $form): Form
{
    return $form
        ->statePath('data')
        ->model($this->user)        // instance ⇒ edit mode
        ->schema([
            TextInput::make('name')->required(),
            TextInput::make('email')->email()->required(),
        ]);
}
```

`isEditing()` returns `true` here; it would be `false` if you passed
`User::class`.

---

## Custom Save Logic

When the default create/update is not what you need — saving through a service,
an API, or a non-Eloquent target — replace persistence with `using()`. It
receives the validated data and its return value becomes the save result.

```php
$form
    ->schema([
        TextInput::make('name')->required(),
        TextInput::make('email')->email()->required(),
    ])
    ->using(function (array $data) {
        return app(UserProvisioner::class)->create($data);
    });
```

Validation, `mutateDataBeforeSave()`, `beforeSave()`, and `afterSave()` still
run; only the persistence step is replaced.

---

## Side Effects After Save

`afterSave()` receives the saved record — use it for notifications, events, or
relationship work that needs the persisted model.

```php
$form
    ->model(Order::class)
    ->schema([/* … */])
    ->afterSave(function ($record): void {
        OrderPlaced::dispatch($record);
        $record->customer->notify(new OrderConfirmation($record));
    });
```

See [Save Lifecycle](forms/save-lifecycle.md) for the full order of callbacks.

---

## Scope a Table Query

Constrain a single table to the current user or tenant with
`modifyQueryUsing()`. It runs as part of the table's query pipeline.

```php
use Illuminate\Database\Eloquent\Builder;

public function table(Table $table): Table
{
    return $table
        ->model(Order::class)
        ->modifyQueryUsing(fn (Builder $query) => $query->where('user_id', auth()->id()))
        ->columns([/* … */]);
}
```

To apply the same scope across **many** tables, move it into a plugin query pipe
or the `table.querying` hook — see
[Core Plugins → Query Pipes](core/plugins.md#query-pipes).

---

## Dynamic Defaults

`default()` accepts a closure resolved at render time, so a new (create-mode)
form can seed values from auth, the request, or config.

```php
$form
    ->model(Post::class)
    ->schema([
        TextInput::make('title')->required(),
        Hidden::make('author_id')->default(fn () => auth()->id()),
        Select::make('status')->options(Status::options())->default('draft'),
    ]);
```

Defaults apply only when the field has no value yet, so they do not overwrite an
edited record.

---

## See Also

- [Forms overview](forms/overview.md) — the full Form API
- [Save Lifecycle](forms/save-lifecycle.md) — hooks in order
- [Extending Forms](forms/custom-fields.md) — when a recipe needs a new field
- [Table overview](table/overview.md) — columns, filters, actions
- [Core Plugins](core/plugins.md) — app-wide rules via hooks and pipes
