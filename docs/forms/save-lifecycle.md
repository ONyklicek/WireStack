---
order: 30
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
│   └── mutateDataBeforeSave(Closure $fn)
│       Transform validated data before persistence
│
├── 3. PLUGIN HOOK: form.saving
│   └── Plugins may inspect or modify $data
│
├── 4. BEFORE SAVE
│   └── beforeSave(Closure $fn)
│       Void hook — side effects, external calls
│
├── 5. PERSIST
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
