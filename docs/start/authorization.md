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
TextInputColumn::make('price')
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

## Multi-tenancy

Off by default — most applications have one tenant, and scoping them would be a
`WHERE` clause bought for nothing. Once on it is **strict**.

```php
// config/wire-core.php
'tenancy' => [
    'enabled' => true,
    'column' => 'tenant_id',
],
```

Bind a resolver; the default answers null, which with tenancy on means an empty
page until you do:

```php
use NyonCode\WireCore\Core\Tenancy\Contracts\TenantResolver;

app()->bind(TenantResolver::class, fn () => new class implements TenantResolver {
    public function resolve(): int|string|null
    {
        return auth()->user()?->tenant_id;
    }
});
```

Then mark the models that belong to a tenant:

```php
use NyonCode\WireCore\Core\Tenancy\Concerns\BelongsToTenant;

class Invoice extends Model
{
    use BelongsToTenant;   // [tl! focus]
}
```

Opt-in per model, because the framework cannot know which of your tables are
tenant-owned, and guessing would be a guess about who may see what.

### The fail-safe

**Tenancy on with no tenant resolved returns nothing, never everything.**

That is the whole security story in one line. Every ordinary state produces a
null tenant — before login, on a queue worker, in a console command — so a scope
that read null as "no constraint" would hand every row to every one of them. The
scope constrains to `0 = 1` instead.

It is deliberately not `where tenant_id is null` either: a row nobody owns would
then be visible to *everybody*, which is the same leak wearing a different shape.

### What is scoped

A **global scope**, not a plugin hook — and that choice is the point. A hook
covers one read path, and only while a plugin manager happens to be bound. A
global scope covers every query Eloquent builds:

| | Scoped |
| --- | --- |
| `Invoice::query()`, a table listing, a relation | yes |
| `Invoice::find($id)` in your own controller | yes |
| `->update()` and `->delete()` | yes |
| A queued job resolving records by key | yes |
| `Invoice::create()` | attributed to the current tenant |

A create with no tenant resolved **throws** rather than writing a row with a null
tenant — that row would be invisible to every scoped query afterwards, so the
user's work would be gone with nothing said. An explicitly set tenant column is
left alone, for a seeder or a deliberate cross-tenant move.

The column is qualified, because a scoped model is routinely joined and a joined
table commonly has a `tenant_id` of its own.

### Reading across tenants

```php
Invoice::acrossAllTenants()->count();
```

Verbose on purpose, and easy to grep for: an admin report or a console command
has a real need, and a review should be able to find every place that claimed
one.

### A source Eloquent cannot scope

A non-Eloquent `DataSource` — a `CollectionDataSource`, an API-backed source —
builds no Eloquent query, so no global scope reaches it. Wrap it instead:

```php
use NyonCode\WireCore\Core\Tenancy\TenantScopedDataSource;

$source = new TenantScopedDataSource(new CollectionDataSource($rows), app(Tenancy::class));
```

It constrains the **plan**, not the rows that come back, which is what makes it
safe on every method rather than only on the one that returns rows: `count()`
and `paginate()` are answered by the source without ever handing rows over, and
a source that cannot honour the filter refuses out loud
(`UnsupportedQueryAspectException`) instead of returning rows nobody constrained.

`resolveRecord()` takes a key rather than a plan, so there the record is fetched
and then checked — without which a tenant reaches another tenant's row by typing
its id into a URL.

The fail-safe is the one `TenantScope` has: tenancy on with no tenant resolved
answers with **nothing**. Tenancy off delegates untouched, so wrapping a source
costs a single-tenant application nothing.

Pass a column as the third argument where the source names its tenant something
other than the configured column.
