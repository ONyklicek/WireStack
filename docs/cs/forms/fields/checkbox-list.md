---
summary: Spousta checkboxů z jednoho pole možností, s hledáním, hromadnými přepínači a skupinami, když je seznam dlouhý.
---

# CheckboxList

Více checkboxů z pole možností.

```php
use NyonCode\WireForms\Components\CheckboxList;
```

## Použití

```php
CheckboxList::make('permissions')
    ->options([
        'create' => 'Create',
        'read'   => 'Read',
        'update' => 'Update',
        'delete' => 'Delete',
    ])
    ->columns(2)
    ->searchable()
    ->bulkToggleable()
```

## Dynamické možnosti

```php
CheckboxList::make('roles')
    ->options(fn () => Role::pluck('name', 'id')->toArray())
```

## Možnosti z enumu

Předejte třídu PHP enumu pro rozvinutí jeho case na možnosti `value => label`. Popisky pocházejí z
`getLabel()`, když enum implementuje `Foundation\Contracts\Enum\HasLabel`, jinak se
z názvu case udělá headline. Detaily viz [Select › Možnosti z enumu](select.md#moznosti-z-enumu).

```php
CheckboxList::make('permissions')->options(Permission::class)
```

## Vícesloupcový layout

```php
CheckboxList::make('features')
    ->options([...])
    ->columns(3)
```

## Hledání

```php
CheckboxList::make('permissions')
    ->options([...])
    ->searchable()
    ->searchPrompt('Filter permissions...')
```

## Co je vybrané

`showSelected()` vypíše vybrané položky nad seznamem jako chipy, každý z nich
odebratelný.

```php
CheckboxList::make('permissions')
    ->options([...])
    ->searchable()
    ->showSelected()
```

Tohle je ta polovina, kterou zaškrtávací seznam oproti multi-selectu ztrácí.
Dlouhý seznam ukáže jen řádky kolem místa, kam je odscrollovaný, a vyhledaný jen
shody — takže v obou případech je odpověď na „co jsem vlastně vybral“ mimo
obrazovku. A kliknutí na chip je nejrychlejší způsob, jak vzít zpátky špatné
zaškrtnutí, aniž byste ho hledali ve dvou stech řádcích.

Chipy čtou **stav** pole, ne zaškrtnutá políčka — právě proto přežijí filtr,
který ta políčka ze stránky odstraní. Drží pořadí, ve kterém byly možnosti
deklarované, ne pořadí zaškrtávání: řádek chipů, který se přeskládá pokaždé, když
někdo postupuje seznamem dolů, se čte hůř než seznam, který shrnuje. Když není
vybrané nic, řádek tam není vůbec, a `disabled()` seznam nedostane tlačítka
k odebrání.

Ve výchozím stavu vypnuté — u seznamu pěti možností je to druhá kopie týchž pěti
slov.

## Hromadné přepnutí

```php
CheckboxList::make('permissions')
    ->bulkToggleable()
    ->selectAllLabel('Select All')
    ->deselectAllLabel('Deselect All')
```

**Obě tlačítka pracují s tím, co zbylo po hledání, a zbytku se nedotknou.**
S napsaným `invoices` *Vybrat vše* přidá odpovídající možnosti k tomu, co už
vybrané je, a *Zrušit výběr* odebere jen je — ovládací prvek, který působí mimo
to, na co je namířený, je nejstarší způsob, jak se z hromadné akce stane nehoda,
a u seznamu oprávnění je ta nehoda udělení nebo odebrání všeho.

Když není napsané nic, jsou shodami všechny možnosti, takže nefiltrovaný seznam
se chová přesně, jak čekáte: všechno, nebo nic.

## Seskupené možnosti

Při použití `groups()` je každý klíč nadpisem skupiny a jeho hodnota je pole párů `value => label`.

```php
CheckboxList::make('permissions')
    ->groups([
        'Posts' => ['create_post' => 'Create', 'edit_post' => 'Edit', 'delete_post' => 'Delete'],
        'Users' => ['create_user' => 'Create', 'edit_user' => 'Edit'],
    ])
```

Volání `groups()` automaticky zapne seskupený layout — a prázdná mapa se vykreslí
naplocho, takže *odmítnout* seskupení je totéž volání s ničím uvnitř. Můžete také
zavolat `grouped()` explicitně.

**Se `searchable()` odejde skupina spolu se svými položkami.** Filtrování skrývá
každý řádek zvlášť; nadpis, který zůstane stát nad ničím, se čte jako skupina,
která se nenačetla — proto nese tutéž podmínku jako jeho položky.

## Varianty s přepínacími tlačítky

Když je seznam krátký, čtou se tytéž volby lépe jako řada přepínacích tlačítek
než jako sloupec zaškrtávátek. `segmented()` a `buttons()` vykreslí přesně ten
vzhled jako odpovídající varianty [Radio](radio.md) — jde o vícehodnotovou
polovinu téhož sdíleného slovníku, takže jednovýběrový a vícevýběrový ovládací
prvek vypadají stejně:

```php
CheckboxList::make('days')
    ->options(['mon' => 'Po', 'tue' => 'Út', 'wed' => 'St'])
    ->segmented()

CheckboxList::make('roles')
    ->options(['admin' => 'Admin', 'editor' => 'Editor'])
    ->buttons()
    ->inline()
    ->icons(['admin' => 'shield-check'])
    ->colors(['admin' => 'danger'])
```

Tyto varianty zobrazují jen volby: hledání, hromadné přepnutí, seskupení a
sloupce jsou výbava seznamu a neuplatní se.

## Metody

| Metoda | Typ | Popis |
|--------|------|-------------|
| `options(array\|string\|Closure)` | array | Seznam možností nebo třída enumu (`value => label`) |
| `columns(int)` | int | Počet sloupců (výchozí `1`) |
| `searchable(bool)` | bool | Zapnout vyhledávací pole pro filtrování podle popisku |
| `searchPrompt(string\|null)` | string | Placeholder hledacího inputu |
| `showSelected(bool)` | bool | Zobrazit vybrané položky nad seznamem jako odebratelné chipy |
| `bulkToggleable(bool)` | bool | Zobrazit ovládání „vybrat vše“ / „zrušit výběr“ |
| `selectAllLabel(string\|null)` | string | Popisek tlačítka „vybrat vše“ |
| `deselectAllLabel(string\|null)` | string | Popisek tlačítka „zrušit výběr“ |
| `grouped(bool)` | bool | Zapnout seskupený layout |
| `groups(array\|Closure)` | array | Definice skupin (také zapíná seskupený layout) |
| `default(array\|Closure)` | array | Předvybrané hodnoty |
| `disabled(bool\|Closure)` | bool | Znepřístupnit všechny checkboxy |
| `required()` | — | Označit jako povinné |
| `live()` | — | Spustit Livewire update při změně |

Popisek, hint, tooltip a další sdílené metody viz [Společné API pole](index.md#spolecne-api-pole).
