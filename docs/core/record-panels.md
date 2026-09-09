---
order: 56
---

# Editable panels

A **panel** is an editable "record panel": it looks like an [infolist](infolists.md) — the same declarative schema of sections, grids, and entries — but alongside read-only entries it can host **editable** entries (switch, checkbox, select, text input) that write **straight back to the record**. Each change commits on its own with optimistic UI and optimistic locking, the same write path as [editable table columns](../table/columns/editing.md) — no Save button, no form buffer.

Infolists stay read-only by contract; a panel is the surface for "read *and* edit this one record in place".

```php
use NyonCode\WireCore\Panels\Panel;
use NyonCode\WireCore\Panels\Components\ToggleEntry;
use NyonCode\WireCore\Panels\Components\SelectEntry;
use NyonCode\WireCore\Panels\Components\TextInputEntry;
use NyonCode\WireCore\Infolists\Components\TextEntry; // read-only entries mix in freely
use NyonCode\WireCore\Foundation\Schema\Section;

Panel::make()
    ->record($user)
    ->columns(2)
    ->schema([
        Section::make('Account')->icon('user')->columns(2)->schema([
            TextInputEntry::make('name')->rules(['required', 'min:2']),
            SelectEntry::make('role')->options([
                'viewer' => 'Viewer',
                'editor' => 'Editor',
                'admin'  => 'Admin',
            ]),
            ToggleEntry::make('is_active')->label('Active')->onColor('success'),
            TextEntry::make('email')->icon('envelope'), // read-only
        ]),
    ]);
```

> **New to this?** A panel is an infolist you can edit. You build it in PHP, hand it a record, and every switch/select/field commits itself the moment you change it.

## Installation

Panels ship with `wire-core` — nothing extra to install. Make sure the package views are in your Tailwind content paths so the styles (and the inline-edit controls) are generated:

```js
export default {
    content: [
        // ...your app paths
        './vendor/nyoncode/wire-core/resources/views/**/*.blade.php',
    ],
}
```

Editable entries commit through the shared `wireEditableCell` Alpine engine, which is delivered by the pre-built `wire-core` JS bundle and injected automatically via Livewire's `@assets` — you do not wire up any JavaScript yourself.

## Quick start

A panel needs a Livewire host so its edits have somewhere to commit. The quickest path is to extend `PanelComponent`: hold your record, return the schema from `panel()`, and you're done — the base component renders it and provides the write endpoint.

```php
use NyonCode\WireCore\Panels\Panel;
use NyonCode\WireCore\Panels\PanelComponent;
use NyonCode\WireCore\Panels\Components\ToggleEntry;
use NyonCode\WireCore\Panels\Components\SelectEntry;
use NyonCode\WireCore\Panels\Components\TextInputEntry;

class EditOrderPanel extends PanelComponent // [tl! focus:start]
{
    public Order $order;                    // Livewire re-hydrates this from the DB each request

    public function panel(): Panel
    {
        return Panel::make()
            ->record($this->order)          // 1. the record every entry reads & writes
            ->columns(2)
            ->schema([                       // 2. what to show / edit
                TextInputEntry::make('reference')->rules(['required']),
                SelectEntry::make('status')->options(OrderStatus::class),
                ToggleEntry::make('is_paid')->label('Paid'),
            ]);
    }
} // [tl! focus:end]
```

Drop it on a page like any Livewire component:

```blade
<livewire:edit-order-panel :order="$order" />
```

That's the whole thing. Flip the `is_paid` switch and the `orders.is_paid` column updates immediately; no form, no submit.

> **Why a component, not just `{{ $panel }}`?** Read-only infolists are stateless, so you can echo them anywhere. A panel writes, so it needs a Livewire host to receive the commit. `PanelComponent` is that host; if you already have a component, use the [`WithEditablePanel`](#using-the-trait-directly) trait instead of extending.

## Editable entries

Every editable entry is bound to a record attribute by its name (`ToggleEntry::make('is_active')` reads and writes `is_active`). They share the fluent Foundation vocabulary (label, icon, color, column span, visibility) with infolist entries and form fields.

| Entry | Control | Writes |
| --- | --- | --- |
| `ToggleEntry` | Switch | boolean |
| `CheckboxEntry` | Checkbox | boolean |
| `SelectEntry` | Dropdown | the chosen value |
| `TextInputEntry` | Text field (save on blur / Enter) | string |

### ToggleEntry & CheckboxEntry

Two renderings of the same boolean write. Toggles take on/off track colors from the canonical palette; both cast the value to a boolean before saving.

```php
use NyonCode\WireCore\Panels\Components\ToggleEntry;
use NyonCode\WireCore\Panels\Components\CheckboxEntry;

ToggleEntry::make('is_active')
    ->label('Active')
    ->onColor('success')     // canonical HasColor palette
    ->offColor('gray');

CheckboxEntry::make('accepts_marketing')
    ->label('Marketing emails')
    ->color('primary');      // accent color
```

### SelectEntry

Options accept a plain `value => label` map or a backed-enum class name (resolved through the same `EnumResolver` as `Select` fields and `SelectColumn`):

```php
use NyonCode\WireCore\Panels\Components\SelectEntry;

SelectEntry::make('status')
    ->options(OrderStatus::class)   // or ['open' => 'Open', 'closed' => 'Closed']
    ->placeholder('Choose a status');
```

### TextInputEntry

An inline text field that saves on blur and Enter, with escape-to-revert. Pick the HTML input type with `type()`:

```php
use NyonCode\WireCore\Panels\Components\TextInputEntry;

TextInputEntry::make('name')->rules(['required', 'min:2']);
TextInputEntry::make('price')->type('number')->rules(['numeric', 'min:0']);
```

## Validation

Rules run on the server **before** the write; a failing value is rejected and the control shows the error inline without persisting.

```php
TextInputEntry::make('email')->rules(['required', 'email']);
```

## Guarding edits

`disabled()` renders the control non-interactive **and** rejects the write server-side (the client state is only cosmetic — a forged request is still blocked). It accepts a closure that receives the record:

```php
ToggleEntry::make('is_published')
    ->disabled(fn (Post $record) => $record->is_locked);
```

For authorization, `permission()` rejects the write unless the current user passes `hasPermissionTo()`:

```php
SelectEntry::make('role')
    ->options(Role::class)
    ->permission('assign-roles');
```

Only entries you declare as editable in the schema can ever be written — a read-only `TextEntry` name, or any attribute not in the schema, is refused by the host. This is the write whitelist.

## Custom persistence & side effects

By default an entry writes its own attribute. Override that with `saveUsing()`, and run a side effect after a successful write with `afterStateUpdated()`:

```php
ToggleEntry::make('is_active')
    ->saveUsing(fn (User $record, $value) => $record->forceFill(['is_active' => $value])->save())
    ->afterStateUpdated(fn (User $record, $value) => activity()->log("toggled {$record->id}"));
```

## Optimistic UI & locking

Each commit updates the control immediately, then reconciles with the server:

- **Success** — the value sticks and the record's `updated_at` version advances.
- **Failure** — the control rolls back to the last confirmed value and shows the message inline.
- **Conflict** — if the record changed elsewhere since the panel loaded (its `updated_at` no longer matches), the write is refused, the control adopts the server's current value, and the user sees a "changed elsewhere" note. No lost updates.

The record is always resolved server-side from the component's own bound record inside a locked transaction — the client never chooses which row or column is written.

## Using the trait directly

If you already have a Livewire component, compose `WithEditablePanel` instead of extending `PanelComponent`. Implement `panel()`, render the panel in your view, and include the shared assets partial:

```php
use Livewire\Component;
use NyonCode\WireCore\Panels\Concerns\WithEditablePanel;
use NyonCode\WireCore\Panels\Panel;

class Dashboard extends Component
{
    use WithEditablePanel;

    public Account $account;

    public function panel(): Panel
    {
        return Panel::make()->record($this->account)->schema([
            ToggleEntry::make('two_factor_enabled')->label('Two-factor auth'),
        ]);
    }
}
```

```blade
<div>
    @include('wire-core::partials.floating-assets') {{-- loads the wireEditableCell engine --}}
    {{ $this->panel() }}
</div>
```

## Panels vs. infolists vs. forms

| | Infolist | Panel | Form |
| --- | --- | --- | --- |
| Purpose | Display one record | Edit one record in place | Edit with a submit step |
| Writes | Never | Per-change, direct to the record | On save, from a state buffer |
| Host | Any (stateless) | Livewire (`PanelComponent` / trait) | Livewire |
| Use when | Read-only detail | Quick inline edits, settings screens | Multi-field forms, wizards, validation-heavy flows |

Reach for a panel when editing a single record should feel like flipping switches on a settings page; reach for a [form](../forms/overview.md) when you want a deliberate submit.
