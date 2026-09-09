---
order: 30
summary: The nine steps between `save()` and a persisted record, and the hook that sits at each one.
---

# Save Lifecycle

The `Form::save()` method executes a strict 9-step pipeline. Each step is clearly defined with hooks for customization.

This page describes what happens when a form is saved.

---

## Pipeline Overview

```
Form::save()
│
├── 1. VALIDATE
│   ├── Collect rules from all fields
│   ├── Merge form-level rules
│   ├── Run through ValidationPipeline
│   └── Throw ValidationException on failure ← STOP
│
├── 2. MUTATE
│   ├── mutateDataBeforeSave(Closure $fn)
│   │   Transform validated data before persistence
│   └── Each field's own dehydration, then its dehydrateStateUsing(Closure $fn)
│
├── 3. PLUGIN HOOK: form.saving
│   └── Plugins may inspect or modify $data
│
├── 4. BEFORE SAVE
│   └── beforeSave(Closure $fn)
│       Void hook — side effects, external calls
│
├── 5. PERSIST
│   ├── Drop what is not a column: dehydrated(false), relations, morph pairs
│   ├── Default: Model::create($data) or $model->update($data)
│   └── Custom: using(Closure $fn)
│
├── 6. SAVE RELATIONSHIPS
│   └── RelationshipSaveHandler cascades Repeater data to model relations
│
├── 7. AFTER SAVE
│   └── afterSave(Closure $fn)
│       Void hook — side effects, cache clear, events
│
├── 8. PLUGIN HOOK: form.saved
│   └── Plugins observe the persisted $record
│
└── 9. NOTIFY
    ├── Send success notification via Notifications module
    └── Skip if disableSuccessNotification()
```

---

## Step 1: Validate

Collects rules from all field components and validates the current state.

```php
// Automatic in save()
// Can also be called standalone:
$data = $form->validate();
```

If validation fails, `Illuminate\Validation\ValidationException` is thrown. Steps 2-9 are skipped entirely.

See [Validation](validation.md) for details on field rules, custom messages, and the ValidationPipeline.

---

## Step 2: Mutate Data

Transform the validated data before it reaches the model:

```php
$form->mutateDataBeforeSave(function (array $data): array {
    // Slugify the title
    $data['slug'] = Str::slug($data['title']);

    // Remove temporary fields
    unset($data['agree_to_terms']);

    // Encrypt sensitive data
    $data['ssn'] = encrypt($data['ssn']);

    return $data; // MUST return the array
});
```

The Closure receives the validated data array and **must** return the modified array.

### Multiple Mutations

```php
$form
    ->mutateDataBeforeSave(fn (array $data) => array_merge($data, [
        'updated_by' => auth()->id(),
    ]));
```

---

## The Other Direction: form.filling

This page is the way *out*. The way in has a hook of its own: `Form::fill()`
dispatches [`form.filling`](../core/plugins/hooks.md), so an installed package can
change what a field arrives holding — the counterpart of the `form.saving` step
below.

```php
$manager->hook(Hook::FormFilling, function (FormFillingPayload $payload) {
    $payload->data['currency'] ??= auth()->user()->currency;   // [tl! focus]

    return $payload;
}, for: Invoice::class);
```

It is deliberately **not** dispatched from `getInitialState()`: that answers what a
control needs before anything is bound, and an edit page calls both, so a hook on
each would fire twice per page.

---

## Step 3: Plugin Hook — form.saving

Fires automatically when plugins are registered via `PluginManager`. Plugins may inspect or modify `$data` before persistence. User code does not interact with this step directly.

---

## Step 4: Before Save

A void hook that runs after mutation but before persistence:

```php
$form->beforeSave(function (array $data): void {
    // Validate external service availability
    if (! ExternalApi::isAvailable()) {
        throw new \RuntimeException('External service is down');
    }

    // Dispatch a pre-save event
    event(new UserSaving($data));
});
```

The Closure receives the mutated data but does **not** return it.

If this hook throws an exception, persistence (step 5) is skipped.

---

## Step 5: Persist

### Default Behavior

The persistence logic depends on the model mode:

```php
// Create mode — model is a class string
$form->model(User::class);
// → User::create($data)

// Edit mode — model is an instance
$form->model($user);
// → $user->update($data)
```

### What Reaches The Record

Between the validated data and the write, each field says whether its value is a
column at all, and what that value should be. Two hooks, both on any field:

```php
TextInput::make('password_confirmation')->dehydrated(false);
TextInput::make('password')->dehydrateStateUsing(fn (string $state) => Hash::make($state));
```

- **`dehydrated(false)`** keeps the key out of the write. A field with a rule and
  no column behind it — a password confirmation, a "same as billing" toggle, a
  value that only drives a sibling — would otherwise be set as an attribute and
  fail on the missing column.
- **`dehydrateStateUsing()`** replaces the value on its way out. It receives
  `$state` and the record (`null` in create mode).

Order matters and is fixed: the field type shapes the value first — a
`FileUpload` moves its temporary upload to permanent storage, a `DateTimePicker`
applies its storage format and timezone — and your callback then sees that
result, not the raw value from the browser. Both hooks also apply to fields
inside a `Repeater`, per item.

The condition may be a Closure over live sibling state, resolved at save time:

```php
Toggle::make('has_nickname'),
TextInput::make('nickname')->dehydrated(fn (callable $get) => (bool) $get('has_nickname')),
```

Some fields answer this question for themselves, because their name is not a
column to begin with: a `Repeater` or `Tags` bound with `->relationship()`, and
a `MorphToSelect` (whose value becomes the `{name}_type` / `{name}_id` pair).
They are dropped from the parent write and handled by their own path — nothing
to configure.

Everything is dropped **only at the write**. `mutateDataBeforeSave()`, the
`form.saving` hook and `beforeSave()` all still see the full data array, and so
does the relationship cascade in Step 6.

### The Same Transforms In An Action Modal

A form's state leaves through two doors. `save()` writes it to a record; an
[action](../core/actions/index.md) with a `->form()` hands it to a callback instead:

```php
Action::make('publish')
    ->form([
        Select::make('status')->options(Status::class)->placeholder('None'),
        TextInput::make('price')->numeric(),
        DateTimePicker::make('published_at'),
    ])
    ->action(fn (array $data) => $record->update($data)); // [tl! focus]
```

Both doors apply the same dehydration, so `$data` here holds exactly what a save
would have written: `null` for the cleared select and the emptied number field,
the storage format and timezone for the date, a stored path for an upload, and
your own `dehydrateStateUsing()` applied last. A wizard's steps share one data
bag, and every step is dehydrated — not just the one on screen when it is
submitted.

It happens once, where the data is handed over, and after validation. The live
state the modal is bound to keeps the raw value — a field does not move under an
open modal, and a resubmit dehydrates from the same starting point rather than
from an already-transformed one.

The same holds for the third door, a [halt](../core/actions/lifecycle.md#halt-execution)
that carries a form: confirming it re-executes the action, and the values the
halt collected are dehydrated by that form's own fields on the way in. Keys the
halt carried over from the first attempt are left alone — only what the halt
form declares is its to shape.

### Custom Persistence

Override the default with `using()`:

```php
$form->using(function (array $data): mixed {
    // Create
    $user = User::create($data);
    $user->assignRole($data['role']);
    return $user;
});
```

The `using()` callback replaces the entire default create/update logic. It receives `$data` (the mutated data array). The return value becomes the result of `save()`.

**Relationship repeaters and `using()`.** `$data` contains every field's value, **including relationship `Repeater` arrays** (e.g. a `children` key for `Repeater::make('children')->relationship('children')`). The default persistence path strips those keys before writing the parent; `using()` does not. So:

- Do **not** mass-assign `$data` wholesale — `User::create($data)` would try to write `children` as a column. Assign only the parent's own attributes.
- **Return the persisted `Model`** and the relationship cascade (Step 6) still runs, saving the repeater rows to the relation for you:

```php
$form->using(fn (array $data) => User::create(['name' => $data['name']]));
// → children repeater rows are cascaded onto $user->children()
```

- Return **anything other than a `Model`** (an id, a DTO, a command result) and the cascade is skipped — your callback owns persistence entirely, relations included.

### No Model

If `model(null)` is set and no `using()` callback is provided, `save()` throws an `InvalidArgumentException`.

### Optimistic Locking

Concurrent edits can silently overwrite each other: two users open the same record, both save, and the second write clobbers the first. Enable a version check with `optimisticLock()`:

```php
$form->model($order)->optimisticLock();          // defaults to the 'updated_at' column
$form->model($order)->optimisticLock('version'); // or any integer/version column
```

When enabled, the lock column's value is captured as the form is filled from the record and carried through the Livewire round trip. On save the **current database value is re-read**; if it no longer matches the captured baseline — someone else saved, or deleted, the record in the meantime — the save is aborted with a `NyonCode\WireForms\Forms\Runtime\StaleModelException` and a conflict notification (`wire-forms::messages.stale`), leaving the newer data intact.

- Opt-in and backwards compatible — without `optimisticLock()` nothing changes.
- Runs only in **update** mode (an existing model).
- Set `->model($record)` **before** `->fill()` so the baseline can be captured; if no baseline is present the check fails open (does not block the save).
- Catch `StaleModelException` if you want to handle the conflict yourself (e.g. reload and re-present the form):

```php
use NyonCode\WireForms\Forms\Runtime\StaleModelException;

try {
    $this->form->save();
} catch (StaleModelException $e) {
    // $e->model, $e->lockColumn — reload and let the user retry
}
```

---

## Step 6: Save Relationships

After the model is persisted, `RelationshipSaveHandler` cascades any Repeater field data to the model's relations. This step only runs when the persist result is an Eloquent `Model` instance — which includes a `Model` returned from a custom `using()` callback (see [Custom Persistence](#custom-persistence)).

User code does not interact with this step directly; it is handled automatically for Repeater fields with `->relationship()` configured.

---

## Step 7: After Save

A void hook that runs after successful persistence:

```php
$form->afterSave(function (mixed $record): void {
    // $record is the created/updated Model (or using() return value)
    Cache::forget("user:{$record->id}");

    // Dispatch event
    event(new UserSaved($record));

    // Send notification
    $record->notify(new WelcomeNotification());
});
```

Receives `$record` — the return value of the persist step (typically the Model instance).

---

## Step 8: Plugin Hook — form.saved

Fires after `afterSave` to let plugins observe the persisted record. User code does not interact with this step directly.

---

## Step 9: Notify

Sends a success notification via the Notifications module:

```php
// Custom message
$form->successMessage('User saved successfully!');

// Disable entirely
$form->disableSuccessNotification();
```

The notification is sent through `NotificationManager` using the active driver (session, Livewire, Flasher, etc.).

This step only fires if:
1. The Notifications module is available (`app()->bound()` check)
2. `disableSuccessNotification()` was NOT called
3. The save completed without exceptions

---

## Complete Example

```php
class EditUser extends Component
{
    use WithForms;

    public User $user;
    public array $data = [];

    public function mount(User $user): void
    {
        $this->user = $user;
        $this->form->fill($user->toArray());
    }

    public function form(Form $form): Form
    {
        return $form
            ->statePath('data')
            ->model($this->user)
            ->schema([
                TextInput::make('name')->required()->maxLength(255),
                TextInput::make('email')->email()->required(),
                Select::make('role')
                    ->options(['admin' => 'Admin', 'editor' => 'Editor'])
                    ->required(),
                Toggle::make('active'),
            ])
            ->mutateDataBeforeSave(function (array $data): array { // [tl! focus:start]
                $data['updated_by'] = auth()->id();
                return $data;
            })
            ->beforeSave(function (array $data): void {
                Log::info('Updating user', ['id' => $this->user->id]);
            })
            ->afterSave(function (mixed $record): void {
                Cache::forget("user:{$record->id}");
                event(new UserUpdated($record));
            })
            ->successMessage('User updated.'); // [tl! focus:end]
    }

    public function save(): void
    {
        $this->form->save();
        $this->redirect(route('users.index'));
    }
}
```

---

## Error Handling

| Exception | When | Effect |
|-----------|------|--------|
| `ValidationException` | Step 1 fails | Steps 2-9 skipped, errors shown in UI |
| Any `Throwable` | Steps 2-8 throw | Pipeline aborts, no notification sent |
| `InvalidArgumentException` | No model + no `using()` | Step 5 fails |

The save pipeline does **not** wrap in a database transaction by default. If you need atomicity, wrap in `DB::transaction()`:

```php
public function save(): void
{
    DB::transaction(fn () => $this->form->save());
}
```
