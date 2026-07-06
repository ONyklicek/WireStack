---
order: 50
---

# Authorization

Wire uses Laravel Gate and policies. This keeps authorization compatible with native Laravel policies, Spatie Permission, and packages that register abilities into Gate.

## Shared Component Rules

Columns, filters, actions, fields, and widgets can use the shared authorization methods when the component supports visibility or authorization.

```php
Action::make('approve')
    ->label('Approve')
    ->authorize('approve')
    ->action(fn (Order $record) => $record->approve());

TextColumn::make('internal_note')
    ->label('Internal note')
    ->permission('orders.internal-notes.view');

SelectFilter::make('department_id')
    ->authorizeUsing(fn (User $user) => $user->is_admin);
```

Resolution order:

| Rule | Behavior |
|------|----------|
| No authorization configured | Allowed |
| No authenticated user | Denied |
| `authorizeUsing()` | Custom callback has priority |
| `authorize()` | Checks a Laravel Gate ability |
| `permission()` | Checks a permission string through Gate |

### Per-record authorization

The `authorizeUsing()` callback receives the authenticated user and, where the surface has one, the **row's record** — so authorization can be scoped per record:

```php
Action::make('approve')
    ->authorizeUsing(fn (User $user, $record) => $user->id === $record?->manager_id)
    ->action(fn (Order $record) => $record->approve());
```

The record is present for **row actions**; it is `null` for record-less surfaces (structural column/filter visibility, fields, widgets), so a one-argument closure `fn ($user) => …` stays valid everywhere.

This governs whether the whole column/action **exists** structurally (evaluated once). To hide or redact a **single cell per row** — e.g. show `salary` only on records the user may see — use the column's `visibleForRecord()` instead, which runs at cell render with that row's record:

```php
TextColumn::make('salary')
    ->visibleForRecord(fn ($record) => auth()->user()->can('viewSalary', $record));
```

## Table Policies

Enable policy checks on a table with `authorize()`.

```php
use NyonCode\WireTable\Table;

public function table(Table $table): Table
{
    return $table
        ->model(Order::class)
        ->authorize()
        ->columns([
            // ...
        ]);
}
```

Wire checks these policy methods when they are needed:

| Table capability | Policy ability |
|------------------|----------------|
| Create record | `create` |
| Update record | `update` |
| Delete record | `delete` |
| View record | `view` |

## Table Overrides

Use overrides when a table needs rules that are different from the model policy.

```php
return $table
    ->model(Order::class)
    ->authorize()
    ->authorizeCreate(fn () => auth()->user()?->can('create', Order::class) ?? false)
    ->authorizeUpdate(fn (Order $record) => ! $record->is_locked)
    ->authorizeDelete(fn (Order $record) => $record->status === 'draft')
    ->authorizeView(fn (Order $record) => $record->tenant_id === auth()->user()?->tenant_id);
```

Each override accepts a boolean or a closure.

## Inline Editing

Editable columns can require a Gate ability for inline editing.

```php
TextColumn::make('price')
    ->editable()
    ->authorizeInline('orders.update-price');
```

If the user does not pass the ability check, the column remains visible but the inline edit is denied.

## Actions

Actions can be hidden or denied with Gate abilities, permission strings, or custom callbacks.

```php
Action::make('refund')
    ->label('Refund')
    ->authorize('refund')
    ->visible(fn (Order $record) => $record->is_paid)
    ->requiresConfirmation()
    ->action(fn (Order $record) => $record->refund());
```

For simple permission strings:

```php
Action::make('export')
    ->permission('orders.export')
    ->action(fn () => $this->exportTable());
```

## Forms

Forms can use model policies for create and update.

```php
use NyonCode\WireForms\Forms\Form;

public function form(Form $form): Form
{
    return $form
        ->model($this->user ?? User::class)
        ->authorize()
        ->schema([
            // ...
        ]);
}
```

When `authorize()` is enabled:

| Form state | Policy ability |
|------------|----------------|
| Model class or unsaved model | `create` |
| Existing model instance | `update` |

If denied, the form is read-only and cannot be saved.

For custom form rules:

```php
return $form
    ->model($this->user)
    ->authorizeUsing(fn (User $user) => $user->hasRole('editor'))
    ->schema([
        // ...
    ]);
```

## Sortable

Sortable operations should be protected in your Livewire component hooks.

```php
public function beforeRowsReordered(array $orderedIds): void
{
    $this->authorize('reorder', Task::class);
}
```

See [Sortable Row Reordering](sortable/row-sorting.md) for lifecycle hooks.

## Related Docs

| Document | What It Covers |
|----------|----------------|
| [Core Actions](core/actions.md) | Row, bulk, header actions and modal actions |
| [Table Overview](table/overview.md) | Table setup and table-level API |
| [Forms Overview](forms/overview.md) | Form setup and save behavior |
| [Audit Log](core/audit.md) | Recording model changes after authorization succeeds |
