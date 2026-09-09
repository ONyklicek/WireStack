---
order: 30
summary: "Tlačítka na read-only povrchu — kam infolistová akce dosáhne a co se změní, když je infolist uvnitř modalu akce."
---

# Akce v infolistu

I z read-only povrchu je co dělat: schválit, stáhnout, otevřít záznam jinde.
Akce v infolistu je obyčejná [akce](../actions/index.md) vykreslená tady — s
jedním pravidlem, které je dobré znát předem: co může a co nemůže spustit
stránka, která neskládá žádnou hostitelskou traitu.

## Akce

Entries, hlavičky sekcí a repeatable řádky mohou nést interaktivní [`Action`](actions.md) tlačítka — postavená ze stejného fluent `Action` API jako table a modal akce a sdílející field-action dispatch kontrakt (`HasFieldActions`). Názvy akcí **musí být unikátní** v rámci infolistu.

> **Požadavek na hostitele.** Akce infolistu dispatchují přes hostitelův `callInfolistAction()`, poskytovaný core action runtime (`InteractsWithActions`). Fungují hned, když je infolist zobrazen [uvnitř action modalu](#uvnitr-action-modalu) (table / `WithActions` hostitel ho skládá). Samostatný infolist vyechovaný v prosté Livewire komponentě dispatchuje jen když ta komponenta skládá action runtime — a resource `ViewPage` je právě taková komponenta, takže na detailu resource musí mít akce `url()`, aby vůbec něco dělala. Viz [Panely: Stránky](../../panels/pages.md).

**Hlavičkové akce sekce** — vykreslené v hlavičce sekce, dostanou navázaný záznam:

```php
Section::make('Profile')
    ->headerActions([
        Action::make('edit')->icon('pencil')->action(fn ($record) => /* … */),
    ])
    ->schema([ /* entries */ ]);
```

**Akce entry** — vykreslené pod hodnotou, dostanou záznam a `$state` entry:

```php
TextEntry::make('api_token')
    ->actions([
        Action::make('regenerate')->icon('arrow-path')
            ->action(fn ($record) => $record->regenerateToken()),
    ]);
```

**Akce per řádek** — deklarované na `RepeatableEntry`, vykreslené jednou per řádek a vyvolané s **položkou toho řádku** jako `$record` / `$state`:

```php
RepeatableEntry::make('lines')
    ->schema([TextEntry::make('sku'), TextEntry::make('qty')->numeric()])
    ->actions([
        Action::make('viewLine')->icon('eye')
            ->action(fn ($record) => /* $record je položka řádku */),
    ]);
```

<a id="inside-an-action-modal"></a>

## Uvnitř action modalu

`ViewAction` (nebo jakákoli akce) může otevřít read-only modal, který ukáže záznam v infolistu. `infolist()` zrcadlí `form()`: záznam akce se naváže automaticky, modal **není** potvrzení a vykreslí jen tlačítko zavření.

```php
use NyonCode\WireCore\Actions\ViewAction;

ViewAction::make()
    ->slideOver()
    ->infolist([
        TextEntry::make('name')->weight('bold'),
        TextEntry::make('email')->copyable(),
        TextEntry::make('created_at')->dateTime()->since(),
    ]);

// Closure forma dostane záznam:
ViewAction::make()->infolist(fn ($record) => Infolist::make()->schema([
    TextEntry::make('name'),
]));
```

## Související

- [Infolisty](index.md) — povrch, na kterém tyhle akce sedí
- [Akce](../actions/index.md) — třídy, které se vykreslují
- [Modály akcí](../actions/modals.md) — modal s infolistem z druhé strany
- [Panely: Stránky](../../panels/pages.md) — proč stránka detailu neskládá hostitelskou traitu
