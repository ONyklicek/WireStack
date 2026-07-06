---
order: 23
nav: false
---

# ButtonColumn

Plnohodnotné interaktivní tlačítko s akcemi, potvrzením, loading stavy a více variantami.

```php
use NyonCode\WireTable\Columns\ButtonColumn;
```

## Tlačítko s odkazem

```php
ButtonColumn::make('view')
    ->buttonLabel('View')
    ->buttonIcon('eye')
    ->buttonColor('primary')
    ->actionUrl(fn ($record) => route('users.show', $record), openInNewTab: true)
```

## Akční tlačítko (Livewire)

```php
ButtonColumn::make('approve')
    ->buttonLabel('Approve')
    ->buttonIcon('check')
    ->buttonColor('success')
    ->action(fn ($record) => $record->approve())
```

## Volání Livewire metody

```php
ButtonColumn::make('download')
    ->buttonLabel('Download')
    ->buttonIcon('download')
    ->livewireAction('downloadPdf')  // volá $this->downloadPdf($recordKey)
```

## S potvrzením

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

## Varianty tlačítek

```php
// Solid (výchozí)
ButtonColumn::make('save')->buttonColor('primary')

// Outlined
ButtonColumn::make('cancel')->buttonColor('gray')->outlined()

// Link styl
ButtonColumn::make('details')->link()

// Danger zkratka
ButtonColumn::make('remove')->danger()

// Success zkratka
ButtonColumn::make('confirm')->success()
```

## Jen ikona

```php
ButtonColumn::make('edit')
    ->buttonIcon('pencil')
    ->iconOnly()                     // bez popisku, jen ikona
    ->tooltip('Edit record')
```

## Velikosti

```php
ButtonColumn::make('action')
    ->buttonSize('xs')   // xs, sm, md, lg
```

## Podmíněný stav

```php
ButtonColumn::make('publish')
    ->buttonLabel(fn ($r) => $r->is_published ? 'Unpublish' : 'Publish')
    ->buttonColor(fn ($r) => $r->is_published ? 'gray' : 'success')
    ->buttonIcon(fn ($r) => $r->is_published ? 'x' : 'check')
    ->visibleWhen(fn ($r) => $r->status !== 'draft')
    ->disabled(fn ($r) => $r->is_locked, 'Record is locked')
```

## Loading stav

```php
ButtonColumn::make('process')
    ->buttonLabel('Process')
    ->loading(true, 'Processing...')  // zobrazit spinner + text během vykonání
```

## API ButtonColumn

```php
->buttonLabel(string|Closure $label)
->buttonIcon(string|Closure $icon, ?string $position = 'before')  // 'before' | 'after'
->buttonColor(string|Closure $color)       // 'primary', 'danger', 'success', 'gray', …
->buttonSize(string|Closure $size)         // 'xs', 'sm', 'md', 'lg'
->buttonVariant(string|Closure $variant)   // 'solid', 'outlined', 'link'
->iconOnly(bool $iconOnly = true)
->outlined()                               // zkratka pro variant('outlined')
->link()                                   // zkratka pro variant('link')
->danger()                                 // zkratka pro color('danger')
->success()                                // zkratka pro color('success')
->action(Closure $fn)                      // inline akční callback
->livewireAction(string $method)           // volat Livewire metodu
->actionUrl(Closure $url, bool $openInNewTab = false)  // vykreslit odkaz místo toho
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
