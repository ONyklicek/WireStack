---
order: 23
nav: false
---

# ButtonColumn

Fully-featured interactive button with actions, confirmation, loading states, and multiple variants.

```php
use NyonCode\WireTable\Columns\ButtonColumn;
```

## Link Button

```php
ButtonColumn::make('view')
    ->buttonLabel('View')
    ->buttonIcon('eye')
    ->buttonColor('primary')
    ->actionUrl(fn ($record) => route('users.show', $record), openInNewTab: true)
```

## Action Button (Livewire)

```php
ButtonColumn::make('approve')
    ->buttonLabel('Approve')
    ->buttonIcon('check')
    ->buttonColor('success')
    ->action(fn ($record) => $record->approve())
```

## Livewire Method Call

```php
ButtonColumn::make('download')
    ->buttonLabel('Download')
    ->buttonIcon('download')
    ->livewireAction('downloadPdf')  // calls $this->downloadPdf($recordKey)
```

## With Confirmation

```php
ButtonColumn::make('delete')
    ->buttonLabel('Delete')
    ->buttonIcon('trash')
    ->buttonColor('danger')
    ->requiresConfirmation(
        title: 'Delete this record?',
        description: 'This action cannot be undone.',
        confirmText: 'Yes, delete',
        cancelText: 'Cancel',
    )
    ->action(fn ($record) => $record->delete())
```

## Button Variants

```php
// Solid (default)
ButtonColumn::make('save')->buttonColor('primary')

// Outlined
ButtonColumn::make('cancel')->buttonColor('gray')->outlined()

// Link style
ButtonColumn::make('details')->link()

// Danger shortcut
ButtonColumn::make('remove')->danger()

// Success shortcut
ButtonColumn::make('confirm')->success()
```

## Icon Only

```php
ButtonColumn::make('edit')
    ->buttonIcon('pencil')
    ->iconOnly()                     // no label, just icon
    ->tooltip('Edit record')
```

## Sizes

```php
ButtonColumn::make('action')
    ->buttonSize('xs')   // xs, sm, md, lg
```

## Conditional State

```php
ButtonColumn::make('publish')
    ->buttonLabel(fn ($r) => $r->is_published ? 'Unpublish' : 'Publish')
    ->buttonColor(fn ($r) => $r->is_published ? 'gray' : 'success')
    ->buttonIcon(fn ($r) => $r->is_published ? 'x' : 'check')
    ->visibleWhen(fn ($r) => $r->status !== 'draft')
    ->disabled(fn ($r) => $r->is_locked, 'Record is locked')
```

## Loading State

```php
ButtonColumn::make('process')
    ->buttonLabel('Process')
    ->loading(true, 'Processing...')  // show spinner + text during execution
```

## ButtonColumn API

```php
->buttonLabel(string|Closure $label)
->buttonIcon(string|Closure $icon, ?string $position = 'before')  // 'before' | 'after'
->buttonColor(string|Closure $color)       // 'primary', 'danger', 'success', 'gray', …
->buttonSize(string|Closure $size)         // 'xs', 'sm', 'md', 'lg'
->buttonVariant(string|Closure $variant)   // 'solid', 'outlined', 'link'
->iconOnly(bool $iconOnly = true)
->outlined()                               // shortcut for variant('outlined')
->link()                                   // shortcut for variant('link')
->danger()                                 // shortcut for color('danger')
->success()                                // shortcut for color('success')
->action(Closure $fn)                      // inline action callback
->livewireAction(string $method)           // call Livewire method
->actionUrl(Closure $url, bool $openInNewTab = false)  // render a link instead
->requiresConfirmation(
    bool|Closure $requires = true,
    string|Closure|null $title = null,
    string|Closure|null $description = null,
    string|Closure|null $confirmText = null,
    string|Closure|null $cancelText = null,
)
->disabled(bool|Closure $disabled = true, string|Closure|null $tooltip = null)
->visibleWhen(Closure $fn)
->enabledWhen(Closure $fn)
->loading(bool|Closure $show = true, string|Closure|null $text = null)
->extraButtonAttributes(array|Closure $attrs)
```
