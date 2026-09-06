---
summary: Částka v měně psaná tak, jak se píše, uložená jako číslo.
---

# MoneyInput

Peněžní částka. Pole ukazuje číslo seskupené tak, jak ho člověk píše —
`1 234,50` — zatímco sloupec za ním drží číslo. Sáhněte po něm vždy, když se
edituje cena, součet nebo zůstatek; `TextInput::make()->numeric()` uloží stejné
číslo, ale uživatele nutí číst neseskupené.

```php
use NyonCode\WireForms\Components\MoneyInput;
```

## Jak to funguje

**Měna není nikdy součástí hodnoty.** Vykresluje se v affixu pole — ve výchozím
stavu za částkou, před ní s `currencyBefore()` — takže vstup drží číslo
a nic než číslo. Právě to dělá zpětné přečtení jednoznačným.

**Stav je napsaná částka a převod se děje na hranici stavu.**
`hydrateState()` zapíše uložené číslo ve formátu tohoto pole, `dehydrateState()`
přečte napsaný text zpět na číslo zaokrouhlené na přesnost pole. Nic za
formulářem oddělovač nikdy neuvidí.

**Seskupení se aplikuje při psaní** Alpine maskou `$money`, nastavenou ze
stejného formátu. Žádný roundtrip: maska běží v prohlížeči a hodnota, kterou
vytvoří, je přesně ta, kterou parser očekává.

**Přesnost plyne z měny, podle toho, jak je zapsaná.** `'Kč'` je hovorový český
zápis a píše se v celých korunách; ISO kód `'CZK'` si haléře nechává, stejně
jako každá jiná měna. `decimals()` obojí přebije. Stejné pravidlo používá i
`MoneyColumn` — obojí čte
`Foundation\ValueObjects\MoneyFormat`, takže se částka píše stejně ve formuláři
i v tabulce.

**Zpětné čtení čísla je záměrně shovívavé.** Zahodí se vše, co není číslice nebo
desetinný oddělovač, takže seskupení psané kterýmkoli způsobem (`1 234,50`,
`1.234,50`) se přečte stejně. Jedna výjimka: v čárkovém formátu se osamocená `.`
bez jediné čárky čte jako desetinná tečka — to totiž posílá numerická klávesnice
a její zahození by z `1234.50` tiše udělalo stokrát větší částku.

**Validuje se částka, ne text.** Laravelí `numeric` by odmítlo seskupení, které
pole záměrně píše, takže pole přidává pravidlo `MoneyAmount`: nejdřív parsuje,
pak porovnává. Právě proto tu `minValue()` a `maxValue()` něco znamenají — na
textovém vstupu by to byly HTML atributy, které prohlížeč ignoruje.

**Aplikace si měnu nastaví jednou.** Měna i oba oddělovače spadají zpět na
`config('wire-forms.money.*')`, takže pole říká jen to, čím se od zbytku aplikace liší.
`currency(null)` zůstává volbou, ne opomenutím: znamená holé číslo a výchozí hodnotu nečte.

## Základní použití

```php
MoneyInput::make('price')
```

České koruny s haléři a měnou za částkou: `1 234,50 CZK`.

## Měna

```php
MoneyInput::make('price')
    ->currency('EUR')          // 1 234,50 EUR
```

```php
MoneyInput::make('price')
    ->currency('$')
    ->currencyBefore()         // $ 1 234,50
```

Umístění se říká, neháduje se — třípísmenný kód nic nevypovídá o konvenci země,
která ho píše. Explicitní `prefix()` nebo `suffix()` měnu přebije, což je způsob,
jak dát jednotku na druhou stranu:

```php
MoneyInput::make('rate')
    ->currency('EUR')
    ->suffix('/ hour')         // měna pak vede: EUR 1 234,50 / hour
    ->currencyBefore()
```

## Přesnost a oddělovače

```php
MoneyInput::make('price')
    ->currency('Kč')           // celé koruny, podle konvence
    ->decimals(2)              // …pokud neřeknete jinak
```

```php
MoneyInput::make('price')
    ->currency('USD')
    ->separators('.', ',')     // 1,234.50 USD
```

## Minor units

```php
MoneyInput::make('price_in_cents')
    ->currency('EUR')
    ->storeAsMinorUnits()      // uživatel napíše 12,34 — sloupec dostane 1234
```

Uživatel pořád čte a píše částku; mění se jen sloupec. Zaokrouhluje se na
přesnost pole, takže vložené třetí desetinné místo se do celočíselného sloupce
nedostane jako useknutá hodnota.

## Meze

```php
MoneyInput::make('price')
    ->minValue(0)              // žádné záporné ceny
    ->maxValue(1_000_000)
```

Obojí se validuje proti naparsované částce a chyba hlásí mez ve formátu, ve
kterém byla nastavena: *„Pole price musí být alespoň 0,00 CZK."*

## Rozšířený příklad

```php
use Livewire\Component;
use NyonCode\WireForms\Components\MoneyInput;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WireForms\Forms\WithForms;

class EditProduct extends Component
{
    use WithForms;

    public array $data = [];

    public function mount(Product $product): void
    {
        $this->form->fill($product->attributesToArray());
    }

    public function form(Form $form): Form
    {
        return $form
            ->model(Product::class)
            ->statePath('data')
            ->schema([
                TextInput::make('name')->required(),
                MoneyInput::make('price')          // [tl! focus:start]
                    ->currency('CZK')
                    ->minValue(0)
                    ->required(),
                MoneyInput::make('cost_in_cents')
                    ->label('Nákupní cena')
                    ->currency('EUR')
                    ->currencyBefore()
                    ->storeAsMinorUnits(),         // [tl! focus:end]
            ]);
    }
}
```

## API MoneyInput

Měnová část. Všechno ostatní patří `TextInputu` — `->minValue()`,
`->maxValue()`, `->prefix()`, `->suffixAction()`, `->placeholder()` — a je
zdokumentované v [TextInput](text-input.md); sdílené API pole pak ve
[Formulářová pole](index.md).

```php
->currency(?string $currency, ?int $decimals = null)  // 'CZK'|'Kč'|'EUR'|'$'|null — výchozí: config('wire-forms.money.currency')
->decimals(int $decimals)                             // přebije konvenci měny
->separators(string $decimal, string $thousands)      // výchozí: config('wire-forms.money.*_separator')
->currencyBefore(bool $before = true)                 // '$ 1 234,50' místo '1 234,50 $'
->storeAsMinorUnits(bool $condition = true)           // sloupec drží haléře/centy
->getCurrency(): ?string
->getDecimals(): int
->storesMinorUnits(): bool
->getMoneyFormat(): MoneyFormat                       // slovník, kterým pole píše a čte
```

## Související

- [TextInput](text-input.md) — pole, ze kterého toto vychází, včetně `dynamicMask()`
- [Formulářová pole](index.md) — sdílené API pole
- [MoneyColumn](../../table/columns/money.md) — stejná částka, jen zobrazená
- [Validace](../validation.md) — jak se implicitní pravidla přidávají k vašim
