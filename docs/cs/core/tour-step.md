---
order: 83
summary: Jedna zastávka průvodce — element hook, na který ukazuje, jak se zúží na jeden prvek z mnoha, a co říká panel vedle něj.
---

# TourStep

Jedna zastávka [průvodce](tours.md): prvek, na který ukazuje, a co říká panel
vedle něj. Krok jmenuje **element hook**, tedy jméno `data-wire`, které framework
píše do svého markupu, nikdy CSS selektor.

```php
use NyonCode\WireCore\Tours\TourStep;
```

## Jak to funguje

**Na co krok ukazuje.** Z `TourStep::make('table-search')` se stane selektor
`[data-wire="table-search"]`. Prohlížeč ho vyhledá při startu průvodce a znovu
před zobrazením každého kroku. Panel ukazuje na první prvek, který selektoru
odpovídá, **pokud je vidět**. Velká část markupu frameworku se vykreslí a pak
zůstane skrytá, dokud není potřeba: lišta hromadných akcí, dokud nejsou vybrané
řádky, dropdown, dokud se neotevře. Takový prvek na stránce je, ale nemá žádnou
velikost, takže se s krokem zachází, jako by prvek chyběl.

**Proč hook a ne selektor.** Jméno hooku je veřejné API. Průvodce theming slibuje,
že jméno může přibýt a v minor vydání se nepřejmenuje ani neodstraní, a
`npm run hooks:verify` to na frameworku vymáhá. Třída, id nebo strukturní
selektor jako `.mt-4 > div` takový slib nemají, takže průvodce napsaný proti nim
by se mohl rozbít ve vydání, o kterém nikdo netušil, že něco rozbíjí. Není to ani
`data-testid`. Tam, kde jsou obě, nesou stejné jméno, ale test id se měnit smí.

**Kontroluje se při vytvoření.** `make()` vyhodí `TourDefinitionException` pro
jméno, které není správně utvořené jméno hooku (kebab-case: malá písmena a
číslice, jednoduché pomlčky, na začátku písmeno). Kontrola existuje proto, že
prohlížeč **krok, jehož prvek chybí nebo je skrytý, přeskočí**. To je záměr, aby
průvodce fungoval i tehdy, když aplikace nějaký ovládací prvek schová. Bez
kontroly by se stejně přeskočil i překlep a průvodce by se zkrátil, aniž by si
toho kdo všiml. Kontrola zachytí jen špatně utvořené jméno. Správně utvořené
jméno, které na stránce nic nevykresluje, se přeskočí jako každý chybějící prvek.

**Kde běží.** Po vykreslení stránky se na server z kroku nic nedostane. Jeho
selektor, titulek, text a umístění přijdou se stránkou a zbytek udělá prohlížeč.

**Výchozí stav.** Žádný titulek, žádný text, umístění `bottom`, žádné zúžení.

## Základní použití

```php
TourStep::make('table-search')
    ->heading('Najděte řádek')
    ->text('Pište sem a tabulka se zúží.');
```

## Obsah

`heading()` je tučný řádek nahoře v panelu a `text()` jsou jedna nebo dvě věty
pod ním. Obojí můžete vynechat nebo vymazat pomocí `null`. Jde o prostý text, ne
HTML:

```php
TourStep::make('table-column-toggle')
    ->heading('Vaše sloupce')
    ->text('Skryjte ty, které nikdy nečtete. Tabulka si vaši volbu zapamatuje.');
```

Překládejte je jako jakýkoli jiný text:

```php
TourStep::make('admin-sidebar')->text(__('tours.sidebar'));
```

## Umístění

Kde panel sedí vzhledem ke svému prvku. Hodnota jde do Floating UI beze změny:
`top`, `right`, `bottom` nebo `left`, volitelně s příponou `-start` nebo `-end`.
Když na požadované straně není místo, Floating UI přesune panel na opačnou
stranu.

```php
TourStep::make('admin-sidebar')->placement('right-start');   // vedle vysokého prvku, zarovnaný nahoru
TourStep::make('admin-user')->placement('bottom-end');       // pod něčím v pravém horním rohu
```

Hodnota se nekontroluje. Jen prázdná hodnota se nahradí za `bottom`. Cokoli
jiného projde tak, jak je napsané.

## Zúžení na jeden prvek

Jedno jméno hooku může být na mnoha prvcích naráz. Každá položka sidebaru je
`admin-nav-item`. `where()` přidá druhý atribut, který jednu z nich vybere:

```php
TourStep::make('admin-nav-item')->where('resource', 'orders');
// [data-wire="admin-nav-item"][data-resource="orders"]
```

Jméno atributu se píše bez předpony `data-` a musí mít tvar jména hooku, jinak
`where()` vyhodí výjimku. Hodnota se escapuje, takže klíč s uvozovkou selektor
nerozbije. Víc volání `where()` se kombinuje přes AND, v pořadí, ve kterém jste
je napsali.

U `admin-nav-item` to funguje, protože jeho view píše `data-resource` hned vedle
hooku. Zužujte jen podle atributů, které sedí na stejném prvku jako hook.

## Rozšířený příklad

Průvodce při prvním spuštění, jehož kroky jdou po stránce zleva doprava, včetně
kroku, který ukazuje na jednu položku z mnoha v sidebaru:

```php
<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use NyonCode\WireCore\Tours\Tour;
use NyonCode\WireCore\Tours\Tours;
use NyonCode\WireCore\Tours\TourStep;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->app->make(Tours::class)->register(
            Tour::make('orders-first-run')
                ->resource('orders')
                ->page('index')
                ->steps([
                    TourStep::make('admin-nav-item')             // [tl! focus:start]
                        ->where('resource', 'orders')
                        ->heading('Objednávky')
                        ->text('Jste tady. V tomto seznamu je každá objednávka, kterou obchod přijal.')
                        ->placement('right'),

                    TourStep::make('table-search')
                        ->heading('Najděte jednu')
                        ->text('Hledejte podle zákazníka, čísla nebo e-mailu.')
                        ->placement('bottom-start'),

                    TourStep::make('table-bulk-bar')             // skrytá, dokud nejsou vybrané řádky — přeskočí se
                        ->text('Vyberte víc řádků a pracujte s nimi najednou.'),

                    TourStep::make('table-column-toggle')
                        ->heading('Vaše sloupce')
                        ->text('Skryjte ty, které nikdy nečtete.')
                        ->placement('bottom-end'),               // [tl! focus:end]
                ]),
        );
    }
}
```

`table-bulk-bar` na stránce je, ale zůstává skrytá, dokud nejsou vybrané řádky,
takže se krok při první návštěvě přeskočí a počítadlo ukáže tři kroky, ne čtyři.

## API TourStep

```php
TourStep::make(string $anchor)             // jméno element hooku; vyhodí výjimku, když není správně utvořené
->heading(?string $heading)                // tučný řádek nahoře v panelu — výchozí žádný
->text(?string $text)                      // text kroku, prostý text — výchozí žádný
->placement(string $placement)             // 'top'|'right'|'bottom'|'left', volitelně '-start'|'-end' — výchozí 'bottom'
->where(string $attribute, string $value)  // zúžení podle data-<attribute>="<value>"; jméno musí mít tvar hooku
->getAnchor(): string
->getHeading(): ?string
->getText(): ?string
->getPlacement(): string
->getSelector(): string                    // CSS selektor, který prohlížeč vyhledá, i se zúžením
```

## Související

- [Tour](tours.md) — kdo průvodce uvidí, kde běží a jak se pamatuje
- [Theming](../start/theming.md#stylovaci-hooky) — odkud se berou jména hooků a co slibují
