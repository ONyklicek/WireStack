---
summary: Hvězdičkové hodnocení s volitelnou přesností na půl hvězdy, uložené jako číslo.
---

# Rating

Hvězdičkové hodnocení. Sáhněte po něm, když je hodnota *skóre, které člověk
udělí* — spokojenost, priorita, recenze — a řada hvězd je to, co ji dělá
zodpověditelnou na první pohled. Pro skóre, které se počítá, se lépe čte
`TextColumn::make()->numeric()` než ovládací prvek, na který nikdo nesmí klikat.

```php
use NyonCode\WireForms\Components\Rating;
```

## Jak to funguje

**Stav je číslo a jeho typ plyne z přesnosti.** Pole s celými hvězdami se
hydratuje jako `int`, pole s půlhvězdami jako `float` — `getStateType()` vrací
`'int'` nebo `'float'` podle `allowHalf()`, takže sloupec typu `integer` nikdy
nedostane `4.5` z pole, které to ani neumí vytvořit.

**O tom, do které poloviny klik spadl, rozhoduje prohlížeč** podle odsazení
ukazatele uvnitř hvězdy: za polovinou je celá hvězda, před ní půlka. Proto
přesnost na půl hvězdy nepotřebuje žádné další značky — odpovídá tentýž prvek
hvězdy.

**Klik na aktivní hvězdu hodnocení zruší**, pokud `clearable(false)` neřekne
jinak. Bez toho jde omylem udělené hodnocení jen změnit, nikdy vzít zpět — a to
je jediná interakce, kterou řada hvězd jinak vyjádřit neumí.

**Barva se řeší v PHP, ne ve view.** `getColorClasses()` mapuje jméno na světlý
konec kanonické palety (`success` je emerald, ne green) a výchozí je klasická
jantarová hvězda, ne primární barva tématu — hodnocení vypadá jako hodnocení
v každém tématu.

**Nula je skutečná hodnota a není „prázdno“.** Nedotčené pole je `null`, což
`required()` odmítne — ale *zrušené* hodnocení uloží `0` a laravelí `required`
nulu přijme. Pole, které musí opravdu držet hvězdu, chce vedle toho
`->rules(['min:1'])`.

## Základní použití

```php
Rating::make('score')
```

Pět celých hvězd, jantarové, zrušitelné.

## Půlhvězdy

```php
Rating::make('rating')
    ->allowHalf()      // 0.5 kroky: 1, 1.5, 2, 2.5 …
```

Stav se stane floatem; sloupec, který ho drží, ho musí přijmout.

## Jiná škála

```php
Rating::make('priority')
    ->max(3)           // tříhvězdičková škála
```

## Barva

```php
Rating::make('satisfaction')
    ->color('success')   // 'primary' | 'success' | 'danger' | cokoli jiného → jantarová
```

## Bez rušení

```php
Rating::make('score')
    ->clearable(false)   // klik na aktivní hvězdu už nevynuluje
```

## Rozšířený příklad

```php
use Livewire\Component;
use NyonCode\WireForms\Components\Rating;
use NyonCode\WireForms\Components\Textarea;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WireForms\Forms\WithForms;

class LeaveReview extends Component
{
    use WithForms;

    public array $data = [];

    public function form(Form $form): Form
    {
        return $form
            ->model(Review::class)
            ->statePath('data')
            ->schema([
                Rating::make('score')            // [tl! focus:start]
                    ->label('Jaké to bylo?')
                    ->max(5)
                    ->allowHalf()
                    ->color('success')
                    ->required(),                // [tl! focus:end]
                Textarea::make('comment')
                    ->placeholder('Co byste změnili?'),
            ]);
    }

    public function save(): void
    {
        $this->form->save();
    }
}
```

## API Rating

Část pro hodnocení. Popisek, hint, helper text, `required()`, `disabled()`,
`live()` a zbytek jsou sdílené API pole, zdokumentované ve
[Formulářová pole](index.md).

```php
->max(int $max)                        // počet hvězd — výchozí 5, nejméně 1
->allowHalf(bool $condition = true)    // 0.5 kroky; stav se stane floatem
->color(string $color)                 // 'primary'|'success'|'danger' — výchozí jantarová
->clearable(bool $condition = true)    // klik na aktivní hvězdu vynuluje — výchozí true
->getMax(): int
->isAllowHalf(): bool
->getColor(): string
->isClearable(): bool
```

## Související

- [Formulářová pole](index.md) — sdílené API pole
- [Slider](slider.md) — stejná otázka „vyberte číslo“ na spojitém rozsahu
- [RatingColumn](../../table/columns/rating.md) — totéž skóre zobrazené v tabulce
