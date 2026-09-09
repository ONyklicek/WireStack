---
order: 10
summary: "Čtyři třídy akcí, co dostane který callback a fluent povrch, který mají všechny společný."
---

# Akce

Akce je deklarované tlačítko s callbackem za sebou: `Action` pro jeden záznam,
`BulkAction` pro výběr, `HeaderAction` pro ani jedno a `ActionGroup` na složení
několika do rozbalovacího menu. Všechny dědí z `BaseAction`, takže to, co se
naučíš tady, platí všude, kde se kreslí tlačítko — v řádku tabulky, v hlavičce,
v infolistu, na stránce i ve vlastní komponentě.

## Typy akcí

| Třída | Případ užití | Callback dostane |
|-------|----------|-------------------|
| `Action` | Řádková akce — jeden záznam | `fn (Model $record, array $data)` |
| `BulkAction` | Vybrané záznamy | `fn (Collection $records, array $data)` |
| `HeaderAction` | Hlavička tabulky — bez kontextu záznamu | `fn (array $data)` |
| `ActionGroup` | Seskupuje akce do dropdownu | — |

Všechny rozšiřují `BaseAction` a sdílejí stejné fluent API pro label, ikonu, barvu, velikost, modal, životní cyklus.

## Předpřipravené akce

| Třída | Popis |
|-------|-------------|
| `DeleteAction` | Smazání jednoho záznamu s potvrzením |
| `DeleteBulkAction` | Hromadné smazání s potvrzením |
| `RestoreBulkAction` | Hromadné obnovení soft-smazaných záznamů, s potvrzením |
| `ForceDeleteBulkAction` | Hromadné trvalé smazání soft-smazaných záznamů, s potvrzením |
| `EditAction` | Otevře edit modal/formulář |
| `ViewAction` | Otevře view modal |

```php
use NyonCode\WireCore\Actions\DeleteAction;
use NyonCode\WireCore\Actions\DeleteBulkAction;

$table->actions([DeleteAction::make()])
      ->bulkActions([DeleteBulkAction::make()]);
```

Každý preset dodává label, ikonu, barvu a potvrzovací modal; chování
dodáte pomocí `->action()`. Soft-delete presety se párují s tabulkou zúženou na
trashed záznamy (např. `->query(User::onlyTrashed())`):

```php
use NyonCode\WireCore\Actions\ForceDeleteBulkAction;
use NyonCode\WireCore\Actions\RestoreBulkAction;

$table->bulkActions([
    RestoreBulkAction::make()->action(fn ($records) => $records->each->restore()),
    ForceDeleteBulkAction::make()->action(fn ($records) => $records->each->forceDelete()),
]);
```

## Základní použití

```php
use NyonCode\WireCore\Actions\Action;
use NyonCode\WireCore\Actions\BulkAction;
use NyonCode\WireCore\Actions\HeaderAction;

// Řádková akce
Action::make('edit')
    ->label('Edit')
    ->icon('pencil')
    ->color('primary')
    ->url(fn (User $record) => route('users.edit', $record)) // [tl! focus]

// Řádková akce s callbackem
Action::make('archive')
    ->label('Archive')
    ->icon('archive')
    ->action(fn (User $record) => $record->update(['archived' => true])) // [tl! focus]
    ->successNotification('Archived!')

// Hromadná akce
BulkAction::make('export')
    ->label('Export Selected')
    ->icon('download')
    ->action(fn (Collection $records) => Excel::download($records)) // [tl! focus:start]
    ->deselectRecordsAfterCompletion() // [tl! focus:end]

// Hlavičková akce
HeaderAction::make('create')
    ->label('New User')
    ->icon('plus')
    ->url(route('users.create'))
    ->badge(fn () => User::whereNull('verified_at')->count()) // [tl! focus:start]
    ->badgeColor('danger') // [tl! focus:end]
```

## Skupiny akcí

Sbalte sekundární akce do dropdown menu. Na telefonu se menu otevře jako
bottom sheet — přepište pomocí `->sheetOnMobile(false)` / `->mobileBreakpoint('md')`;
viz [mobilní prezentace](../../start/configuration.md#mobil).

```php
use NyonCode\WireCore\Actions\ActionGroup;

$table->actions([
    Action::make('edit')->icon('pencil'),

    ActionGroup::make('more', [
        Action::make('duplicate')
            ->icon('copy')
            ->action(fn ($record) => $record->replicate()->save()),
        Action::make('archive')
            ->icon('archive')
            ->action(fn ($record) => $record->archive()),
        Action::divider(),                    // vizuální oddělovač
        Action::make('delete')
            ->icon('trash')
            ->color('danger')
            ->requiresConfirmation()
            ->action(fn ($record) => $record->delete()),
    ])->divided(),                            // auto-vložit oddělovače mezi položky
]);
```

Skupiny podporují `badge()` a `badgeColor()` stejně jako HeaderAction a k tomu
tři vlastní nastavení:

```php
->dropdownPosition(string|Placement $position)   // 'bottom-start'|'bottom-end'|'top-start'|'top-end' — výchozí 'bottom-end'
->dropdownWidth(string $width)                   // Tailwind třída šířky panelu, např. 'w-56'
->lazyMenu(bool $lazy = true)                    // menu se postaví v prohlížeči při prvním otevření
```

Za větu stojí `lazyMenu()`. Ve výchozím stavu vykreslí skupina celé menu do
každého řádku; s ním nese řádek jen spouštěč a JSON popis položek a menu se
postaví při prvním otevření. To vymění malou cenu při otevření za velký pokles
práce na vykreslení každého řádku — vyplatí se na dlouhé tabulce, kde má skupinu
každý řádek, a nevyplatí se na tabulce o třech řádcích.

## Dynamické vlastnosti

Všechny vlastnosti podporují Closury — vyhodnocené per-záznam v čase renderu:

```php
Action::make('toggle')
    ->label(fn (User $record) => $record->is_active ? 'Deactivate' : 'Activate')
    ->color(fn (User $record) => $record->is_active ? 'danger' : 'success')
    ->icon(fn (User $record) => $record->is_active ? 'x' : 'check')
    ->hidden(fn (User $record) => $record->trashed())
```

## Reference BaseAction API

Sdílené napříč Action, BulkAction, HeaderAction:

```php
->label(string|Closure $label)
->icon(string|Closure $icon, ?string $position = null)   // pozice: 'before' | 'after'
->color(string|Closure $color)          // primary, danger, success, warning, info, gray
->size(string $size)                    // xs, sm, md, lg
->outlined(bool $outlined = true)
->tooltip(string|Closure $tooltip)
->action(Closure $callback)
->hidden(bool|Closure $hidden = true)
->visible(bool|Closure $visible = true)
->disabled(bool|Closure $disabled = true)
->requiresConfirmation()
->modalHeading(string $heading)
->modalDescription(string $description)
->modalIcon(string $icon, ?string $color)
->modalWidth(string $width)
->modalSubmitActionLabel(string $label)
->modalCancelActionLabel(string $label)
->slideOver()
->form(array $components)
->fillFormUsing(Closure $fn)
->steps(array $steps)
->modal(ModalContract $modal)        // Modal | SlideOver | ConfirmationDialog | Wizard
->before(Closure $fn)
->after(Closure $fn)
->successNotification(string $message)
->failureNotification(string $message)
->keyboardShortcut(string $keys)
->extraAttributes(array $attrs)
```

Override prezentace řádkové akce (`Action`), respektované pod `Table::actionsStyle('quiet')`:

```php
->quiet(bool $quiet = true)   // neutrální v klidu, barva na hoveru/focusu (obvykle nastaveno pro celou tabulku)
->solid(bool $solid = true)   // vynutí plnou výplň i pod tichou tabulkou
```

## Blade komponenty

```blade
<x-wire-actions::button :action="$action" />
<x-wire-actions::group :group="$group" />
<x-wire-actions::modal-host :component="$this" />  {{-- pro WithActions hostitele --}}
<x-wire-actions::halt-host :component="$this" />   {{-- jen pro halt bez runtimu --}}
```

## V této sekci

| Stránka | Co pokrývá |
| --- | --- |
| [Modály akcí](modals.md) | Potvrzení, slide-over, modály s formulářem a infolistem, wizardy a jejich vrstvení |
| [Lifecycle a fronty](lifecycle.md) | Hooky kolem běhu, zastavení běhu zevnitř — i na komponentě úplně bez akcí — a předání práce frontě |
| [Tlačítka a vzhled](appearance.md) | Icon buttony, odkazy, zkratky, velikosti a tichá řádková varianta |
| [Mimo tabulku](standalone.md) | `WithActions` na libovolné Livewire komponentě |
| [Workflow a přechody](workflow.md) | Které přechody stavů jsou legální, deklarované na jednom místě |

## Související

- [Modály](../modals.md) — třídy modálů, které akce otevírá
- [Akce v tabulce](../../table/actions.md) — vlastní řádkové, hromadné a hlavičkové akce tabulky
- [Akce nad záznamem](../../table/record-actions.md) — celý řádek jako ovládací prvek
- [Notifikace](../notifications/index.md) — co akce řekne, když doběhne
- [Autorizace](../../start/authorization.md) — gate, na kterou se ptá každá akce
