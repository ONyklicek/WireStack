---
summary: Surový markup uvnitř schématu a statické pomocníky, které z něj staví běžné kusy.
---

# Html

Markup vložený rovnou do schématu. Sáhni po `Html`, když formulář potřebuje něco,
co není pole ani věta — linku mezi dvěma skupinami, nadpis, který ti layoutové
komponenty nedají, blok vlastního markupu. Na hodnotu s labelem nad ní použij
[`Placeholder`](placeholder.md), na celý partial [`ViewField`](view-field.md).

```php
use NyonCode\WireForms\Components\Display\Html;
```

## Jak to funguje

`Html` je **zobrazovací komponenta**: vykresluje výstup a nikdy se neváže na stav
formuláře. Žádná state path, žádná validace, nic se neodesílá.

**Jeho obsah se vykresluje neescapovaný, vždycky.** Žádný přepínač `escape()` tu
není — na rozdíl od [`Placeholderu`](placeholder.md), který ve výchozím stavu
escapuje, a od [`ViewFieldu`](view-field.md), který nechává volbu na tobě. Co
nastavíš, to se dostane na stránku:

```php
Html::make()->content($userSuppliedString)   // tohle nikdy nedělej
```

Cokoli, v čem je uživatelský vstup, musí být sanitizované dřív, než se to sem
dostane, nebo to patří do komponenty, která escapuje.

**Jméno je volitelné**, což je neobvyklé — `Html::make()` bez argumentu si samo
vygeneruje `html_<uniqid>`, protože komponenta, která nedrží stav, nemá čím být
adresovaná. Jméno předej, jen když ho chceš pro vlastní potřebu.

**Statické továrny jsou ta bezpečná cesta.** `divider()`, `spacer()`, `heading()`
a `paragraph()` nespojují řetězce: každá nastaví `content()` na closure, která
vykreslí Blade partial, a ten partial text escapuje přes `{{ }}`. Takže
`Html::heading($zDatabaze)` je bezpečné způsobem, jakým
`Html::make()->content(...)` není.

Z toho, že je to closure a ne vykreslený řetězec, plynou dvě věci: markup se
staví jen pro komponentu, která se opravdu ukáže, a ty partialy —
`wire-forms::components.html.*` — jsou místo pro publish a override, jako každý
jiný view, který tenhle balíček veze.

**`heading()` si typografickou škálu vybírá sám** podle úrovně: `1` je `text-2xl
font-bold`, `2` (výchozí) `text-xl font-semibold`, `3` `text-lg font-medium`
a cokoli jiného `text-base font-medium`. Úroveň rozhoduje i o tagu, takže
`heading('Fakturace', 3)` je `<h3>`.

## Základní použití

```php
Html::make()
    ->content('<div class="rounded bg-gray-50 p-3 text-sm">Cokoli chceš.</div>')
```

## Statické pomocníky

```php
Html::divider();                       // <hr> se svislým odsazením
Html::spacer('8');                     // prázdný div, Tailwindí krok výšky
Html::heading('Fakturační údaje');     // <h2>, výchozí úroveň
Html::heading('Karta', 3);             // <h3>
Html::paragraph('Čísla karet neukládáme.');
```

Po těchhle sahej ve výchozím případě: escapují svůj text a jejich markup je jeden
publikovatelný partial místo řetězce ve tvém schématu.

## Rozdělení dlouhého formuláře

```php
Html::heading('Kontakt'),              // [tl! focus]
TextInput::make('email')->email(),
TextInput::make('phone'),

Html::divider(),                       // [tl! focus]

Html::heading('Fakturace'),            // [tl! focus]
TextInput::make('vat_number'),
```

[`Section`](../../core/schema/layout/section.md) tohle dělá s kartou, skládáním
a hlavičkovými akcemi; `Html::heading()` plus `Html::divider()` je plochá verze,
pro formulář, který chce ten rytmus bez boxů.

## Rozšířený příklad

Formulář nastavení ve skutečném Livewire hostu, který používá továrny na strukturu
a jeden surový blok na něco, co framework nedodává:

```php
use Livewire\Component;
use NyonCode\WireForms\Components\Display\Html;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireForms\Components\Toggle;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WireForms\Forms\WithForms;

class WorkspaceSettings extends Component
{
    use WithForms;

    public ?array $data = [];

    public function form(Form $form): Form
    {
        return $form
            ->statePath('data')
            ->model(Workspace::class)
            ->schema([
                Html::heading('Obecné'),                             // [tl! focus:start]
                Html::paragraph('Tohle vidí každý ve workspace.'),

                TextInput::make('name')->required(),
                TextInput::make('slug')->required(),

                Html::divider(),

                Html::heading('Nebezpečná zóna', 3),
                Html::make()->content(
                    '<p class="text-sm text-red-600">Smazání workspace nejde vzít zpět.</p>'
                ),                                                    // [tl! focus:end]

                Toggle::make('scheduled_for_deletion')
                    ->label('Naplánovat smazání tohohle workspace'),
            ])
            ->successMessage('Nastavení uloženo');
    }

    public function save(): void
    {
        $this->form->save();
    }
}
```

Nadpisy a odstavec vezou text, který továrny escapují; ten jeden surový blok je
literál, který napsal vývojář — a to je jediný druh řetězce, který do `content()`
patří.

## Html API

```php
->content(string|Closure|null $content)       // vykreslí se NEESCAPOVANĚ — viz Jak to funguje
->getContent(): ?string
Html::make(?string $name = null): static      // jméno je volitelné
Html::divider(): static                       // <hr>
Html::spacer(string $size = '4'): static      // prázdný div na Tailwindím kroku výšky
Html::heading(string $text, int $level = 2)   // <h1>–<h3>, escapované, s odpovídající škálou
Html::paragraph(string $text): static         // <p>, escapovaný
```

Viditelnost a `columnSpan()` jsou sdílený povrch komponent — viz
[Společné API pole](index.md#spolecne-api-pole). Validace, `live()` a výchozí
hodnoty neplatí: `Html` žádný stav nedrží.

## Související

- [Placeholder](placeholder.md) — text s labelem, ve výchozím stavu escapovaný
- [ViewField](view-field.md) — celý Blade partial místo řetězce
- [Alert](alert.md) — barevný box na zprávu
- [Section](../../core/schema/layout/section.md) — nadpisy s kartou kolem
