---
summary: Víceřádkový text, s počtem řádků a chováním při změně velikosti.
---

# Textarea

Víceřádkový text. Sáhni po `Textarea`, kdykoli je odpovědí věta a víc — poznámka,
popis, adresa. Na jeden řádek použijte [`TextInput`](text-input.md); na text, který
má jít *formátovat*, [rich](rich-editor.md) nebo [markdownový](markdown-editor.md)
editor.

```php
use NyonCode\WireForms\Components\Textarea;
```

## Jak to funguje

Textarea je **pole**: řetězec na své state path, validovaný jako kterékoli jiné.
Vykreslí obyčejný `<textarea>` — žádný editor, žádný toolbar — a přesně proto je
to správná výchozí volba na volný text, který nechcete mít označkovaný.

**`rows()` nastavuje počáteční výšku, ne limit.** Vypíše HTML atribut `rows`
a výchozí hodnota je `3`. Uživatel si roh vždycky může přetáhnout a zvětšit si to;
nic mu v tom nebrání.

**`autosize()` výšku převezme.** Přidá drobný Alpine handler, který při každém
vstupu nastaví `style.height` na `scrollHeight` obsahu — a protože běží i na
`x-init`, tak už od prvního vykreslení. Inline výška přebije atribut `rows`, takže
při zapnutém autosize řídí `rows()` jen ten okamžik, než naběhne Alpine. Box roste
*i* se zmenšuje podle textu.

**`cols()` skoro nikdy není to, co chcete.** Vypíše atribut `cols`, jenže element
nese `w-full` a CSS šířka přebije HTML počet sloupců. Na zúžení textarey použijte
`columnSpan()` v [`Gridu`](../../core/schema/layout/grid.md), ne `cols()`.

**`spellcheck()` má tři hodnoty.** `null` — výchozí — atribut vůbec nevypíše
a nechá rozhodnutí na prohlížeči a OS. `true` a `false` ho vynutí. To je rozdíl
mezi „nemám názor“ a „vypnout“ a jen to druhé zastaví prohlížeč v podtrhávání pole
plného produktových kódů.

**`minLength()` / `maxLength()` dělají dvě věci naráz.** Vypíšou HTML atributy
*a* přidají validační pravidla `min` / `max`, takže se limit vynucuje na serveru,
ne jen naznačuje v prohlížeči. A kontrolují se už při skládání: záporná délka nebo
`minLength` nad `maxLength` vyhodí `FormConfigurationException` při sestavení
formuláře, místo aby to tiše spadlo až při validaci.

## Základní použití

```php
Textarea::make('description')
    ->label('Popis')
    ->rows(5)
    ->maxLength(1000)
```

## Růst s textem

```php
Textarea::make('notes')
    ->autosize()    // [tl! focus]
    ->rows(3)       // výška, než to převezme Alpine
```

## Vypnutí kontroly pravopisu

```php
Textarea::make('sku_list')
    ->label('SKU, jedno na řádek')
    ->spellcheck(false)   // [tl! focus]
    ->autosize()
```

## Živé aktualizace

Textarea na `live()` posílá round trip na každý stisk klávesy, takže jí dejte
debounce:

```php
Textarea::make('bio')
    ->live()
    ->debounce(500)   // [tl! focus]
```

## Rozšířený příklad

Formulář článku ve skutečném Livewire hostu — jednořádkový titulek, krátké shrnutí
s tvrdým limitem a tělo, které roste, jak se píše:

```php
use Livewire\Component;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireForms\Components\Textarea;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WireForms\Forms\WithForms;

class EditArticle extends Component
{
    use WithForms;

    public ?array $data = [];

    public function form(Form $form): Form
    {
        return $form
            ->statePath('data')
            ->model(Article::class)
            ->schema([
                TextInput::make('title')->required(),

                Textarea::make('summary')                            // [tl! focus:start]
                    ->label('Shrnutí')
                    ->helperText('Zobrazuje se ve výpisech a ve výsledcích hledání.')
                    ->rows(2)
                    ->maxLength(160)
                    ->required(),

                Textarea::make('body')
                    ->label('Tělo')
                    ->autosize()
                    ->rows(8)
                    ->minLength(50),                                  // [tl! focus:end]
            ])
            ->successMessage('Článek uložen');
    }

    public function save(): void
    {
        $this->form->save();
    }
}
```

Shrnutí je omezené na 160 znaků v prohlížeči *i* na serveru; tělo nesmí být kratší
než 50 znaků a roste, jak se píše.

## Textarea API

```php
->rows(int $rows)                     // počáteční výška v řádcích — výchozí 3
->cols(?int $cols)                    // HTML atribut cols; obvykle vyhraje w-full — použij radši columnSpan()
->autosize(bool $condition = true)    // růst a zmenšování podle obsahu — výchozí false
->spellcheck(?bool $condition = true) // true|false to vynutí; null (výchozí) nechá na prohlížeči
->minLength(?int $length)             // HTML atribut A validační pravidlo `min`
->maxLength(?int $length)             // HTML atribut A validační pravidlo `max`
->getRows(): int
->getCols(): ?int
->isAutosize(): bool
->getSpellcheck(): ?bool
->getMinLength(): ?int
->getMaxLength(): ?int
```

Popisky, nápovědu, placeholder, prefixy, viditelnost, výchozí hodnoty, validaci
a `live()` sdílí každé pole — viz [Společné API pole](index.md#spolecne-api-pole).

## Související

- [TextInput](text-input.md) — týž řetězec na jednom řádku
- [RichEditor](rich-editor.md) a [MarkdownEditor](markdown-editor.md) — když text nese formátování
- [Grid](../../core/schema/layout/grid.md) — kde o šířce rozhoduje `columnSpan()`
- [Reaktivní pole](../reactive-fields.md) — `live()` a jeho debounce
