---
order: 40
summary: "Táž akce jako plné tlačítko, ikona, odkaz nebo sotva znatelný prvek v řádku — mění se vykreslení, nikdy chování."
---

# Tlačítka a vzhled

Jedna akce, několik podob: plné tlačítko v hlavičce, ikona v hustém řádku,
prostý odkaz jinam, klávesová zkratka úplně bez tlačítka. Co se tady mění, je to,
jak je akce nakreslená — nikdy to, co dělá — a slovník barev, velikostí a ikon je
ten kanonický, přes který si své hodnoty řeší každý další povrch frameworku.

## Icon button

```php
Action::make('edit')
    ->icon('pencil')
    ->iconButton()          // vykreslí jako tlačítko jen s ikonou
    ->tooltip('Edit record');

// Nebo skrýt jen label
Action::make('edit')
    ->icon('pencil')
    ->hideLabel();          // onlyIcon() je totéž volání pod jménem, které se tu čte líp
```

`iconButton()` a `hideLabel()` nejsou totéž: první dá tlačítku i čtvercový tvar
icon buttonu a vlastní resolver barev, druhé jen odebere slova. `onlyIcon()` je
alias pro `hideLabel(true)` — zůstal, protože přesně tohle to volání na místě
řádkové akce znamená.

## URL akce

```php
Action::make('view')
    ->url(fn ($record) => route('users.show', $record), openInNewTab: true);

// Řetězcová URL
Action::make('docs')
    ->url('/docs', openInNewTab: true);
```

## Klávesové zkratky

```php
Action::make('save')->keyboardShortcut('mod+s');
Action::make('delete')->keyboardShortcut('Delete');
```

Používá pod kapotou Alpine.js `@keydown`.

## Outlined a velikost

```php
Action::make('cancel')
    ->outlined()                    // outline varianta místo výchozí solid výplně
    ->color('gray')
    ->size('sm');                   // xs, sm, md, lg
```

## Tiché řádkové akce

Ve výchozím stavu se řádkové akce tabulky vykreslují jako plná, stále barevná
tlačítka. Nastavte styl akcí tabulky na `quiet` pro klidnější, profesionálnější
vzhled — akce v klidu vypadají jako neutrální text a barvu odhalí až na hoveru
nebo klávesovém focusu, takže řádek plný akcí přestane soupeřit s daty.

```php
$table->actionsStyle('quiet'); // výchozí je 'solid'
```

Chování tichého stylu:

- Nedestruktivní akce jsou v klidu neutrálně šedé a svou `->color()` získají na hoveru/focusu.
- **Destruktivní akce zůstávají čitelné i v klidu** (červený text), protože dotyková
  zařízení nemají hover — `DeleteAction` tak čte jako nebezpečná i bez interakce.
- Každá akce si drží viditelný focus ring pro klávesnici.

Jednu akci necháte výraznou tím, že ji vrátíte do plné výplně přes `->solid()`:

```php
$table
    ->actions([
        Action::make('view')->icon('outline:eye'),
        Action::make('edit')->icon('pencil')->color('primary'),
        Action::make('approve')->icon('check')->color('success')->solid(), // zůstane plné tlačítko
        DeleteAction::make(),                                              // čitelně červené v klidu
    ])
    ->actionsStyle('quiet');
```

Tichý styl je opt-in; existující tabulky zůstávají beze změny. `->solid()` a
`->outlined()` zůstávají dostupné jako override u jednotlivých akcí.

## Extra atributy

```php
Action::make('custom')
    ->extraAttributes([
        'data-testid' => 'custom-action',
        'x-on:click' => 'console.log("clicked")',
    ]);
```

## Související

- [Akce](index.md) — třídy, které se kreslí
- [Foundation: Barvy](../foundation/colors.md) a [Ikony](../foundation/icons.md) — slovník, kterým tahle nastavení mluví
- [Tiché řádkové akce](#tiche-radkove-akce) — varianta pro husté tabulky, výše
- [Vrstva gest](../../table/gestures.md) — klávesnice nad celou tabulkou
