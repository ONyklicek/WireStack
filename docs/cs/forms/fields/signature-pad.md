---
summary: Podpis nakreslený ukazatelem, uložený jako data URI nebo PNG na disku.
---

# SignaturePad

Podpis nakreslený na plátno prstem, stylusem nebo myší. Sáhněte po něm, když
záznam potřebuje vlastnoruční znak člověka — dodací list, souhlas, schválení —
místo napsaného jména.

```php
use NyonCode\WireForms\Components\SignaturePad;
```

## Jak to funguje

**Stav je kresba jako `data:image/png;base64,…` URI.** Plátno ji vytvoří a putuje
Livewirem jako každý jiný řetězec. To pole ve výchozím stavu ukládá, což se hodí
pro textový nebo blob sloupec.

**`storeOn()` místo toho přesune bajty na disk** a stav pak nese uloženou cestu —
stejný tvar, jaký po sobě nechává `FileUpload`, a pro zobrazení se překládá
stejným žebříčkem URL, takže se podpis i příloha vykreslují ze záznamu stejně.

**Převod se děje na hranici stavu, ne během kreslení.** Prohlížeč drží vždy jen
data URI, takže přerušený formulář ztratí podpis stejně jako kterékoli jiné
neuložené pole a pro formulář, který nikdy neodešel, se nezapíše osiřelý soubor.

**Zápis je idempotentní.** Host může tentýž stav dehydratovat během jednoho
uložení dvakrát — jednou pro předvalidaci, jednou nad zamčeným záznamem — takže
kresba už během tohoto requestu zapsaná odpoví cestou, kterou dostala poprvé,
místo aby na disku nechala druhou kopii.

**Stav, který už drží uloženou cestu, zůstane nedotčený.** Je to podpis, který
nikdo nepřekreslil, a opětovný zápis by při každém uložení přidal kopii téhož
obrázku.

**Kreslí se přes pointer události**, takže jedna sada událostí pokrývá stylus,
prst i myš a tah, který opustí plátno, k němu zůstane připojený, místo aby skončil
uprostřed písmene. Čitelnost výsledku dělají dvě věci: plátno se dimenzuje
v pixelech zařízení a zpětně se zmenšuje, takže tah na 2× displeji je kreslen
v rozlišení toho displeje; a backing store se znovu vytváří jen při skutečné změně
šířky, protože přiřazení do `canvas.width` plátno vymaže — naivní resize handler
smaže podpis pokaždé, když na mobilu odjede lišta prohlížeče.

**Stav se zapisuje při zvednutí ukazatele, ne po bodech.** `toDataURL()`
překóduje celý obrázek; dělat to při každém pohybu by protlačilo base64 PNG
Livewirem desetkrát za tah.

**Obě barvy procházejí kanonickou pojistkou na CSS barvy**, takže hodnota, která
barvou není, nevykreslí žádnou barvu, místo aby se dostala do konfigurace plátna.

## Základní použití

```php
SignaturePad::make('signature')
```

Průhledné PNG ve sloupci, kreslené barvou běžného textu.

## Velikost a inkoust

```php
SignaturePad::make('signature')
    ->height(240)              // CSS pixely — výchozí 180
    ->penColor('#1d4ed8')
    ->penWidth(3)
```

## Podbarvené plátno

```php
SignaturePad::make('signature')
    ->backgroundColor('#fffbeb')
```

Bez nastavení je PNG průhledné, což chce podpis tištěný do dokumentu. Barva je
pro podpis, který se bude zobrazovat na neznámém pozadí.

## Uložení jako soubor

```php
SignaturePad::make('signature')
    ->storeOn('public', 'signatures')   // sloupec drží signatures/<random>.png
```

Bez argumentů se použije disk a adresář, které už používají přílohy
(`wire-forms.file_upload.*`), takže podpisy končí vedle nich.

## Zobrazení podpisu

Uložený podpis nepotřebuje vlastní zobrazovací komponentu — infolistový
`ImageEntry` vykreslí oba tvary, které toto pole ukládá:

```php
use NyonCode\WireCore\Infolists\Components\ImageEntry;

ImageEntry::make('signature')
    ->disk('private')        // disk, na který zapsal storeOn(); pro data URI vynechte
    ->imageSize(120)
```

Data URI je už samo o sobě zdroj obrázku a projde beze změny; uložená cesta se
přeloží stejným žebříčkem URL, jaký používá samo pole.

## Rozšířený příklad

```php
use Livewire\Component;
use NyonCode\WireForms\Components\Checkbox;
use NyonCode\WireForms\Components\SignaturePad;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WireForms\Forms\WithForms;

class SignDelivery extends Component
{
    use WithForms;

    public array $data = [];

    public function form(Form $form): Form
    {
        return $form
            ->model(DeliveryNote::class)
            ->statePath('data')
            ->schema([
                TextInput::make('received_by')->required(),
                Checkbox::make('goods_undamaged'),
                SignaturePad::make('signature')       // [tl! focus:start]
                    ->label('Podpis příjemce')
                    ->height(220)
                    ->storeOn('private', 'deliveries')
                    ->required(),                     // [tl! focus:end]
            ]);
    }

    public function save(): void
    {
        $this->form->save();
    }
}
```

## API SignaturePad

Část pro plátno. Popisek, hint, placeholder (výzva na prázdném plátně),
`required()`, viditelnost a zbytek jsou sdílené API pole, zdokumentované ve
[Formulářová pole](index.md).

```php
->height(int $pixels)                          // výška kreslicí plochy — výchozí 180
->penColor(string|Closure|null $color)         // libovolná CSS barva — výchozí '#111827'
->penWidth(int|float $pixels)                  // šířka tahu — výchozí 2
->backgroundColor(string|Closure|null $color)  // null (výchozí) kreslí průhledné PNG
->storeOn(?string $disk = null, ?string $directory = null)  // uloží PNG, ponechá cestu
```

A co plátno odpoví samo o sobě:

```php
->getHeight(): int
->getPenColor(): string
->getPenWidth(): int|float
->getBackgroundColor(): ?string
->storesFile(): bool
->getDisk(): string
->getDirectory(): string
->getImageUrl(mixed $state): ?string           // aktuální podpis jako zdroj pro <img>
```

## Související

- [Formulářová pole](index.md) — sdílené API pole
- [FileUpload](file-upload.md) — stejný tvar uložené cesty, pro soubory volené uživatelem
- [Infolisty](../../core/infolists/index.md) — `ImageEntry` uložený podpis zobrazí
- [Životní cyklus ukládání](../save-lifecycle.md) — kde během ukládání běží dehydratace
