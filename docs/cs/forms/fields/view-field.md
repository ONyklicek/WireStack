---
summary: Tvůj vlastní Blade view vykreslený v toku schématu, se stavem pole předaným dovnitř.
---

# ViewField

Tvůj vlastní Blade partial, vykreslený ve schématu tam, kde by bylo pole. Sáhni
po `ViewFieldu`, když je to, co potřebuješ ukázat, víc než řádek textu — náhled
právě editované objednávky, graf, mapa, vlastní malá komponenta — a radši to
napíšeš v Blade, než abys stavěl [vlastní pole](../custom-fields.md).

```php
use NyonCode\WireForms\Components\Display\ViewField;
```

## Jak to funguje

ViewField je **zobrazovací komponenta**: vykresluje výstup a nikdy se neváže na
stav formuláře. Žádná state path, žádná validace, nic se neodesílá.

**View vyhrává nad content.** Vykreslený markup se nejdřív ptá na `getView()`
a na `getContent()` spadne, jen když žádný view nastavený není. Komponenta, která
má obojí, tedy ukáže view a řetězec tiše ignoruje — nastav jedno, nebo druhé.

**Partial se `@include`uje, nevykresluje se izolovaně.** Plynou z toho dvě věci
a ta druhá je ta užitečná:

- Cokoli drží `viewData()`, přijde jako proměnné, takže `['total' => 120]` je
  v partialu `$total`.
- Blade `@include` navíc předává **obklopující scope**, takže `$field` — samotný
  `ViewField` — je k dispozici, aniž bys ho předával. Tak se partial dostane
  k `$field->getLabel()` nebo k metodám tvé vlastní podtřídy.

`viewData()` může být closure, vyhodnocovaná při každém čtení, a právě to dovolí
partialu ukázat něco, co závisí na aktuálním stavu, ne na tom, co platilo při
deklaraci schématu.

**`escape()` se čte pozpátku a stojí za to přečíst to dvakrát.** Nastavuje
„vykreslit jako HTML" na *opak* svého argumentu a ta property začíná na `false`:

- Obsah se **ve výchozím stavu escapuje**. Nic se volat nemusí.
- `->escape()` — argument má výchozí `true` — tedy nedělá vůbec nic.
- `->escape(false)` je to, co surové HTML **zapne**.

Týká se to jen fallbacku přes `content()`. Partial vykreslený přes `view()` je
Blade a escapuje si, co si escapuje sám.

## Základní použití

```php
ViewField::make('preview')
    ->label('Náhled')
    ->view('forms.order-preview')
```

```blade
{{-- resources/views/forms/order-preview.blade.php --}}
<div class="rounded border border-gray-200 p-3 text-sm dark:border-gray-700">
    <p class="font-medium">{{ $field->getLabel() }}</p>
    <p>{{ $lines }} položek · {{ $total }}</p>
</div>
```

## Předání dat

```php
ViewField::make('preview')
    ->view('forms.order-preview')
    ->viewData(fn (): array => [                        // [tl! focus]
        'lines' => count($this->data['items'] ?? []),
        'total' => number_format($this->orderTotal(), 2),
    ])
```

Closure místo pole, aby ta čísla byla ta, co jsou zrovna na obrazovce. Aby se
přepočítávala, jak uživatel píše, potřebují pole, na kterých závisí, `live()`.

## Bez view

`ViewField` s `content()` a bez `view()` je [`Placeholder`](placeholder.md)
s obráceným přepínačem escapování. Na tohle použij radši `Placeholder`;
`ViewField` sáhni tehdy, když je tam partial.

## Rozšířený příklad

Formulář objednávky ve skutečném Livewire hostu, kde partial ukazuje náhled toho,
co se právě staví:

```php
use Livewire\Component;
use NyonCode\WireCore\Foundation\Schema\Grid;
use NyonCode\WireForms\Components\Display\ViewField;
use NyonCode\WireForms\Components\Select;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WireForms\Forms\WithForms;

class BuildOrder extends Component
{
    use WithForms;

    public ?array $data = [];

    public function form(Form $form): Form
    {
        return $form
            ->statePath('data')
            ->model(Order::class)
            ->schema([
                Grid::make()->columns(2)->schema([
                    Select::make('product_id')
                        ->options(Product::pluck('name', 'id'))
                        ->live()
                        ->required(),

                    TextInput::make('quantity')
                        ->numeric()
                        ->default(1)
                        ->live()
                        ->required(),

                    ViewField::make('preview')                       // [tl! focus:start]
                        ->label('Náhled objednávky')
                        ->view('forms.order-preview')
                        ->viewData(fn (): array => [
                            'product' => Product::find($this->data['product_id'] ?? null),
                            'quantity' => (int) ($this->data['quantity'] ?? 0),
                        ])
                        ->columnSpanFull(),                           // [tl! focus:end]
                ]),
            ])
            ->successMessage('Objednávka odeslána');
    }

    public function save(): void
    {
        $this->form->save();
    }
}
```

Oba vstupy jsou `live()`, a právě to nechá náhled jít za nimi: view field nemá čím
reagovat sám — překreslí se, když se překreslí formulář.

## ViewField API

```php
->view(string $view)                      // Blade view; vyhrává nad content()
->viewData(array|Closure $data)           // proměnné pro view, vyhodnocené při čtení
->content(string|Closure|null $content)   // fallback, když není view; ve výchozím stavu escapovaný
->escape(bool $condition = true)          // OBRÁCENĚ: escape(false) zapne surové HTML, escape() nedělá nic
->getView(): ?string
->getViewData(): array
->getContent(): ?string
->isHtmlContent(): bool
```

Labely, pomocný text, viditelnost a `columnSpan()` jsou sdílený povrch komponent —
viz [Společné API pole](index.md#spolecne-api-pole). Validace, `live()` a výchozí
hodnoty neplatí: view field žádný stav nedrží.

## Související

- [Placeholder](placeholder.md) — řádek textu, ve výchozím stavu escapovaný
- [Html](html.md) — řetězec markupu bez partialu za sebou
- [Vlastní pole](../custom-fields.md) — když ta věc hodnotu držet potřebuje
- [Reaktivní pole](../reactive-fields.md) — proč pole, která sleduje, potřebují `live()`
