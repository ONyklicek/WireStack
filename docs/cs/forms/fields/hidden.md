---
summary: "Hodnota, kterou formulář veze bez ovládacího prvku: žádný popisek, žádný obal, žádný způsob, jak do ní psát."
---

# Hidden

Hodnota, kterou formulář veze, ale nikdy se na ni neptá — vlastník záznamu, typ
polymorfního rodiče, token. Sáhni po `Hidden`, když hodnota patří do uloženého
záznamu a uživateli do její volby nic není.

```php
use NyonCode\WireForms\Components\Hidden;
```

## Jak to funguje

Tohle je pole, kde se děje nejmíň, a pochopit, *jak* je neviditelné, je celá tahle
stránka.

**Označí se jako skryté už ve svém konstruktoru.** `Hidden::make('user_id')` se
vrátí s `isHidden()` už na `true` — nic jiného ve schématu to nedělá. A protože
každá vykreslovací smyčka ve frameworku — tělo formuláře, `Grid`, `Section`,
`Fieldset`, `Flex`, `Tab`, `Repeater`, `Builder` — přeskakuje komponenty, jejichž
`isVisible()` je false, přeskočí se skryté pole všude. Prakticky nevypíše **žádný
markup**, ani ten `<input type="hidden">`, který popisuje jeho view.

**Kromě formuláře, který odesílá sám prohlížeč.** V
[režimu nativního odeslání](../overview.md#rendering) není žádný Livewire
snapshot, ve kterém by hodnota mohla cestovat — prohlížeč odešle to, co je
v dokumentu, a nic jiného — takže input přestává být dekorací a stává se jediným
způsobem, jak hodnotu unést. `Hidden` v nativním formuláři vykreslí
`<input type="hidden" name="…" value="…">`, hodnotu bere z `old()` a pak ze svého
`default()`, a nic jiného se na něm nemění: pořád je neviditelný a pořád ho
uživatel nedostane na výběr. Přesně tohle veze token pro obnovu hesla od Fortify
na obrazovce [modulu přihlášení](../../modules/auth.md#pole).

**Hodnota žije ve stavu formuláře, ne v DOM**, a právě to je to, co skutečně
funguje. Když se formulář plní, každé pole ve schématu se naplní — svým
`default()`, když je nastavený, jinak typově správnou prázdnou hodnotou — a to se
děje v PHP, dřív než se cokoli vykreslí. Klíč tedy existuje v `$data`, cestuje
v Livewire snapshotu, validuje se a při uložení se zapíše, aniž by na stránce byl
jediný element.

Dva důsledky, které je dobré mít na paměti:

- **`->visible()` ho zpátky nedostane.** Smyčky čtou to `hidden()` z konstruktoru;
  pole, které chcete podmíněně *ukazovat*, je obyčejné pole s podmínkou, ne
  `Hidden`.
- **Uživatel ji pořád může změnit.** Hodnota sedí ve veřejném stavu komponenty jako
  každé jiné pole, takže je stejně důvěryhodná jako cokoli, co přišlo z prohlížeče.
  Hodnota, se kterou se nesmí manipulovat, patří do `mutateFormDataBeforeSave()`
  nebo na model — ne do skrytého pole.

**Pořád se validuje**, a je to jediné neviditelné pole, které ano. Každou jinou
komponentu, jejíž `isVisible()` je false, validační resolver přeskočí, a to
záměrně: `required()` na poli, které schovala podmínka, nesmí nikdy zablokovat
odeslání. `Hidden` je jiný druh neviditelnosti — jeho hodnota se naplní, veze se
ve snapshotu, který prohlížeč umí změnit, a při uložení se zapíše — takže jeho
pravidla jsou jediné, co stojí mezi záznamem a tím, co se vrátilo. `Hidden`,
který neprojde validací, vyrobí chybovou hlášku, která nemá kde být vykreslená,
takže jeho pravidla držte u věcí, které legitimnímu uživateli spadnout nemůžou.

## Základní použití

```php
Hidden::make('user_id')
    ->default(fn () => auth()->id())
```

## Konstanta, kterou záznam potřebuje

```php
Hidden::make('type')
    ->default('post')
    ->rules(['in:post,page'])   // [tl! focus]
```

## Rozšířený příklad

Formulář komentáře ve skutečném Livewire hostu, kde se vezou dvě hodnoty a ani na
jednu se nikdo neptá:

```php
use Livewire\Component;
use NyonCode\WireForms\Components\Hidden;
use NyonCode\WireForms\Components\Textarea;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WireForms\Forms\WithForms;

class AddComment extends Component
{
    use WithForms;

    public Article $article;

    public ?array $data = [];

    public function form(Form $form): Form
    {
        return $form
            ->statePath('data')
            ->model(Comment::class)
            ->schema([
                Hidden::make('article_id')                          // [tl! focus:start]
                    ->default(fn (): int => $this->article->getKey()),

                Hidden::make('author_id')
                    ->default(fn (): int => auth()->id()),           // [tl! focus:end]

                Textarea::make('body')
                    ->label('Tvůj komentář')
                    ->rows(4)
                    ->required()
                    ->maxLength(2000),
            ])
            ->successMessage('Komentář odeslán');
    }

    public function save(): void
    {
        $this->form->save();
    }
}
```

Oba defaulty jsou closury, takže se vyhodnotí při plnění formuláře, ne při
deklaraci schématu — což je podstatné u `auth()->id()` v komponentě, která může být
namountovaná dřív, než je uživatel k dispozici.

A protože hodnota přichází z prohlížeče jako každá jiná, aplikace, která `author_id`
věřit nesmí, by ho měla nastavit na modelu místo aby ho vezla tudy.

## Hidden API

`Hidden` nepřidává žádnou vlastní konfiguraci — je to sdílený povrch `Field` plus
neviditelný konstruktor. Výchozí hodnoty, pravidla, validační hlášky a zbytek jsou
ve [Společném API pole](index.md#spolecne-api-pole). Přepínač nativního odeslání
patří formuláři, ne poli: `Form::nativeSubmit()` ho nastaví každému poli ve
schématu.

## Související

- [Společné API pole](index.md#spolecne-api-pole) — všechno, co tohle pole umí
- [Placeholder](placeholder.md) — opak: něco zobrazeného, co nenese hodnotu
- [Životní cyklus uložení](../save-lifecycle.md) — kde běží `mutateFormDataBeforeSave()`
- [Pole](index.md) — celý seznam
