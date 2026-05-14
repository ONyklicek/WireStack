# Save Lifecycle

The `Form::save()` method executes a strict 6-step pipeline. Each step is clearly defined with hooks for customization.

See [ADR 0010](../decisions/0010-form-save-notifications-integration.md) for the notification integration decision.

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
├── 3. BEFORE SAVE
│   └── beforeSave(Closure $fn)
│       Void hook — side effects, external calls
│
├── 4. PERSIST
│   ├── Default: Model::create($data) or $model->update($data)
│   └── Custom: using(Closure $fn)
│
├── 5. AFTER SAVE
│   └── afterSave(Closure $fn)
│       Void hook — side effects, cache clear, events
│
└── 6. NOTIFY
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

If validation fails, `Illuminate\Validation\ValidationException` is thrown. Steps 2-6 are skipped entirely.

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

## Step 3: Before Save

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

If this hook throws an exception, persistence (step 4) is skipped.

---

## Step 4: Persist

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
$form->using(function (array $data, ?Model $model) {
    if ($model) {
        // Edit
        $model->update($data);
        $model->syncRelations($data);
        return $model;
    }

    // Create
    $user = User::create($data);
    $user->assignRole($data['role']);
    return $user;
});
```

The `using()` callback replaces the entire default create/update logic. It receives:
- `$data` — mutated data array
- `$model` — Model instance (edit mode) or `null` (create mode)

The return value becomes the result of `save()`.

### No Model

If `model(null)` is set and no `using()` callback is provided, `save()` throws a `RuntimeException`.

---

## Step 5: After Save

A void hook that runs after successful persistence:

```php
$form->afterSave(function (array $data, ?Model $model): void {
    // Clear cache
    Cache::forget("user:{$model->id}");

    // Dispatch event
    event(new UserSaved($model));

    // Sync related data
    $model->tags()->sync($data['tag_ids'] ?? []);

    // Send notification
    $model->notify(new WelcomeNotification());
});
```

Receives:
- `$data` — the mutated data
- `$model` — the persisted model instance (or `using()` return value)

---

## Step 6: Notify

Sends a success notification via the Notifications module ([ADR 0010](../decisions/0010-form-save-notifications-integration.md)):

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
        $this->form()->fill($user->toArray());
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
            ->mutateDataBeforeSave(function (array $data): array {
                $data['updated_by'] = auth()->id();
                return $data;
            })
            ->beforeSave(function (array $data): void {
                Log::info('Updating user', ['id' => $this->user->id]);
            })
            ->afterSave(function (array $data, Model $model): void {
                Cache::forget("user:{$model->id}");
                event(new UserUpdated($model));
            })
            ->successMessage('User updated.');
    }

    public function save(): void
    {
        $this->form()->save();
        $this->redirect(route('users.index'));
    }
}
```

---

## Error Handling

| Exception | When | Effect |
|-----------|------|--------|
| `ValidationException` | Step 1 fails | Steps 2-6 skipped, errors shown in UI |
| Any `Throwable` | Steps 2-5 throw | Pipeline aborts, no notification sent |
| `RuntimeException` | No model + no `using()` | Step 4 fails |

The save pipeline does **not** wrap in a database transaction by default. If you need atomicity, wrap in `DB::transaction()`:

```php
public function save(): void
{
    DB::transaction(fn () => $this->form()->save());
}
```
