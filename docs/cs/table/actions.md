---
order: 40
---

# Akce tabulky

Akce používejte pro operace na úrovni záznamu, hromadné operace a příkazy toolbaru.

## Typy akcí

| Typ | Použití pro |
|------|---------|
| Řádkové akce | Jeden záznam po druhém |
| Hromadné akce | Aktuálně vybrané záznamy |
| Hlavičkové akce | Globální příkazy tabulky |
| Skupiny akcí | Kompaktní dropdowny pro více řádkových akcí |

## Řádkové akce

```php
use NyonCode\WireCore\Actions\Action;
use NyonCode\WireCore\Actions\DeleteAction;

->actions([
    Action::make('edit')
        ->label('Edit')
        ->icon('pencil')
        ->url(fn (User $record) => route('users.edit', $record)),

    DeleteAction::make(),
])
```

Řádkovou akci použijte, když uživatel pracuje s jedním záznamem a záměr je z kontextu řádku zřejmý.

### Vykonat PHP logiku

```php
Action::make('activate')
    ->label('Activate')
    ->color('success')
    ->action(function (User $record) {
        $record->update(['active' => true]);
    })
```

### Otevřít URL

```php
Action::make('view')
    ->icon('eye')
    ->url(fn (User $record) => route('users.show', $record), openInNewTab: true)
```

### Akce jen s ikonou

```php
Action::make('edit')
    ->icon('pencil')
    ->iconButton()
    ->tooltip('Edit')
```

## Hromadné akce

Hromadné akce se objeví, když má tabulka vybíratelné řádky.

```php
use NyonCode\WireCore\Actions\BulkAction;
use NyonCode\WireCore\Actions\DeleteBulkAction;

->bulkActions([
    BulkAction::make('export')
        ->label('Export selected')
        ->icon('download')
        ->action(fn (array $records) => $this->exportUsers($records)),

    DeleteBulkAction::make(),
])
```

Hromadné akce používejte pro destruktivní nebo opakující se operace, které by se neměly opakovat řádek po řádku.

## Hlavičkové akce

Hlavičkové akce žijí nad tabulkou a nejsou vázané na konkrétní záznam.

```php
use NyonCode\WireCore\Actions\HeaderAction;

->headerActions([
    HeaderAction::make('create')
        ->label('New user')
        ->icon('plus')
        ->url(route('users.create')),

    HeaderAction::make('export')
        ->label('Export all')
        ->icon('download')
        ->action(fn () => $this->exportAll()),
])
```

## Potvrzovací modály

Vyžadujte potvrzení pro destruktivní nebo vysoce dopadové akce.

```php
Action::make('delete')
    ->color('danger')
    ->requiresConfirmation()
    ->modalHeading('Delete user')
    ->modalDescription('This action cannot be undone.')
    ->action(fn (User $record) => $record->delete())
```

## Akce s formulářem

Připojte k akci schéma Wire Formu, když uživatel musí před vykonáním poskytnout vstup.

```php
use NyonCode\WireForms\Components\Select;
use NyonCode\WireForms\Components\TextInput;

Action::make('edit')
    ->form([
        TextInput::make('name')->required(),
        Select::make('role')
            ->options([
                'admin' => 'Admin',
                'editor' => 'Editor',
                'viewer' => 'Viewer',
            ])
            ->required(),
    ])
    ->fillFormUsing(fn (User $record) => [
        'name' => $record->name,
        'role' => $record->role,
    ])
    ->action(function (User $record, array $data) {
        $record->update($data);
    })
```

Kompletní API formuláře viz [Přehled formulářů](../forms/overview.md) a [Pole formulářů](../forms/fields/index.md).

## Viditelnost, stav a oprávnění

Všechny typy akcí podporují podmíněnou viditelnost a autorizaci.

```php
Action::make('approve')
    ->visible(fn (User $record) => $record->status === 'pending')
    ->disabled(fn (User $record) => $record->is_locked)
    ->permission('approve-users')
```

Udržujte UI poctivé: skryjte akce, které uživatelé nemají vidět, znepřístupněte akce, které vidí, ale zatím nemohou použít.

## Skupiny akcí

Skupiny akcí použijte, když máte pro jeden řádek příliš mnoho řádkových akcí.

```php
use NyonCode\WireCore\Actions\ActionGroup;

->actions([
    ActionGroup::make([
        Action::make('view')->icon('eye')->url(fn (User $record) => route('users.show', $record)),
        Action::make('edit')->icon('pencil')->url(fn (User $record) => route('users.edit', $record)),
        Action::divider(),
        DeleteAction::make(),
    ])->tooltip('More actions'),
])
```

## Související dokumentace

- [Přehled tabulek](overview.md)
- [Sloupce](columns/index.md)
- [Filtry](filters/index.md)
- [Notifikace](notifications.md)
