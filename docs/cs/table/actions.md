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

### Výběr přes hranici stránky

Výběr má dvě podoby a ta druhá je to, co vůbec umožní hromadnou akci nad celou
vyfiltrovanou množinou:

- **klíče** — explicitní sada klíčů záznamů, záměrně nezávislá na filtrech a
  řazení. Výběr stránky se **přidává**, takže stránkování nikdy nezahodí práci.
- **vše odpovídající** — všechno, co odpovídá *aktuálnímu filtru*, uložené jako
  režim, ne jako seznam. Odškrtnutí jednoho řádku ze 128 000 uloží jednu výjimku,
  ne 127 999 klíčů, a seznam celé množiny se nikdy nedostane do prohlížeče.

Lišta výběru vždy ukazuje, která z podob je aktivní, a nabízí cestu k té druhé:

```text
[3] vybrané záznamy                                     [Export] [Smazat] [×]
Vybráno 3.  Vybrat všech 1 284
```

Když je vybraná celá vyfiltrovaná množina, stejný řádek nabídne **Jen tuto
stránku** zpět.

**Změna filtru nebo hledání shodí „vše odpovídající“ zpět na explicitní výběr.**
„Všechno“ je definované filtrem, který byl na obrazovce; zúžit ho a nechat výběr
stát by tiše předefinovalo, čeho se příští hromadná akce dotkne. Řazení a
stránkování s ním nehýbou — ani jedno množinu nemění.

### Hromadné akce nad velkými výběry

Callback akce dostává `Collection`, což je problém, když je výběr dotaz nad
statisíci řádky. Z toho plynou dvě věci.

`Table::bulkMaxRecords()` omezuje, kolik smí jedna akce načíst (výchozí 1 000).
Nad limit se akce odmítne a řekne to, místo aby umřela v půlce:

```php
$table->bulkMaxRecords(5000)   // zvýšit
$table->bulkMaxRecords(null)   // zrušit úplně — viz níž
```

Akce, která musí zvládnout jakoukoli velikost, ať výběr projde místo aby ho
přijala. `eachSelectedRecord()` prochází dotaz po dávkách a nikdy nedrží víc než
jednu dávku v paměti:

```php
BulkAction::make('archive')
    ->action(fn () => $this->eachSelectedRecord(
        fn (Invoice $invoice) => $invoice->archive(),
        chunk: 500,
    ))
```

`selectedRecordsQuery()` vrátí tentýž výběr jako query builder — pro hromadný
update nebo export, který streamuje.

### Řazení na telefonu

Skládané karty skrývají hlavičkový řádek a s ním všechna tlačítka řazení. Pod
breakpointem stohování se proto v liště vykreslí ovládání řazení: vypíše
řaditelné sloupce a aktivní pojmenuje přímo na spouštěči. Na malých obrazovkách
se otevře jako bottom sheet. Není co nastavovat — objeví se vždy, když se potká
`stackedOnMobile()` s aspoň jedním řaditelným sloupcem.

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

### Odmítnutí zastaralého záznamu — `optimisticLock()`

Okno modalu je dlouhé: otevře se, uživatel si záznam přečte, píše, případně
odejde a odešle to až za chvíli. `optimisticLock()` akci odmítne, pokud se záznam
mezitím změnil.

```php
Action::make('approve')
    ->optimisticLock()
    ->form(fn () => [/* … */])
    ->action(fn (Invoice $record, array $data) => $record->approve($data))
```

Baseline se zachytí při otevření modalu a porovná při odeslání, přes stejnou
konvenci verzí (`RecordVersion`, `updated_at` modelu), jakou vždycky používá
[inline edit buňky](columns/editing.md#jak-funguji-inline-ulozeni) — jedna
odpověď na „pohnul se ten řádek?", ne dvě, které se můžou rozejít. Když akci
odmítne, modal se zavře a vyskočí varování; nechat formulář otevřený by uživatele
vrátilo před hodnoty, které už neplatí, a nijak by to nepoznal.

Ve výchozím stavu je **vypnutý**, protože posunutý záznam znehodnotí jen akci,
která se podle přečteného rozhodovala. Schválení faktury, které se pod rukama
změnila částka, je ztracený zápis; smazání záznamu, který někdo přejmenoval,
není. Zapnout to všude by koupilo nový způsob, jak selhat, a nic víc.

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
