---
order: 49
summary: Stornovaný, zrušený nebo archivovaný záznam, který v seznamu zůstává a přestává být zapisovatelný — ztlumený, na přání přeškrtnutý a obarvený, a tak jako tak odmítnutý serverem.
---

# Neaktivní záznamy

Záznam může přestat být živý, aniž by z tabulky zmizel. Stornovaná objednávka,
zrušený řádek faktury, archivovaný zákazník: pořád se čte, pořád se v něm hledá,
pořád se počítá do součtů — a už do něj nikdo nemá psát.

Skrýt ho by byla lež o datech. Nechat otevřené jeho editory je pozvánka k zápisu,
který doména už jednou odmítla. Tabulce se proto jednou řekne, které záznamy jsou
neaktivní:

```php
->rowInactive(fn (Order $order) => $order->status === 'cancelled')
```

Takový řádek se ztlumí, jeho inline editory se neotevřou a server zápis odmítne,
i kdyby byl podvržený. Všechno ostatní na řádku — akce, zaškrtávátko, kliknutí,
které záznam otevře — funguje dál.

## Co jedno volání rozhoduje

`rowInactive()` bere predikát a volitelný druhý argument, který určuje, co stav
dělá:

```php
use NyonCode\WireTable\Support\InactiveRow;

->rowInactive(
    fn (Order $order) => $order->status === 'cancelled',
    fn (InactiveRow $row) => $row
        ->strikethrough()      // přeškrtnout texty řádku
        ->color('danger'),     // a obarvit ho
)
```

Closure dostane `InactiveRow` této tabulky a konfiguruje ho na místě, stejně jako
`gestures()` konfiguruje `TableGestures`. Návratová hodnota se ignoruje, takže
funguje jak plynulý řetěz, tak víceřádkové tělo.

Předejte prosté `true` pro tabulku, jejíž každý řádek je neaktivní (obrazovka
archivu jen ke čtení), nebo `false`, chcete-li výchozí stav vyslovit nahlas:

```php
->rowInactive()          // každý záznam této tabulky
->rowInactive(false)     // žádný — totéž jako metodu nezavolat
```

## Zámek je na straně serveru

Tohle je ta půlka, na které záleží, a není to nová mašinerie: tabulka vloží své
pravidlo do vlastního per-record stavu disabled každého editovatelného sloupce, a
to je brána, kterou `Column::canEdit()` už dnes uplatňuje v zápisové pipeline.

Má to tři důsledky, které stojí za to znát:

- **Podvržený požadavek je odmítnut.** Atribut `disabled`, který vidí prohlížeč,
  je jen kosmetika; odmítnutí padne znovu při commitu editace buňky, pod zámkem
  řádku, v `CellEditPipeline::commit()`.
- **Fill handle je pokrytý zdarma.** Tažení, které zapíše jednu hodnotu do sta
  řádků, projde pro každý z nich stejnou per-record bránou — stornované řádky
  uprostřed tahu se přeskočí a nahlásí, nezapíšou.
- **Vlastní `disabled()` sloupce platí dál.** Jsou to dvě oddělené přihrádky a
  buňku zamkne kterákoli z nich, ať byla deklarována první nebo druhá — pravidlo
  sloupce pro jeho vlastní buňky:

```php
TextInputColumn::make('quantity')->disabled(fn (Order $order) => $order->locked)
```

a pravidlo tabulky, naráz pro všechny editovatelné sloupce té tabulky:

```php
->rowInactive(fn (Order $order) => $order->status === 'cancelled')
```

Na pořadí deklarace nezáleží. `->columns([...])->rowInactive(...)` i
`->rowInactive(...)->columns([...])` zamknou přesně stejné buňky.

## Vzhled

Dva oddělené přepínače, protože každý říká něco jiného.

**`dim()` je ve výchozím stavu zapnutý.** Ztlumí texty řádku: tenhle řádek
přestal být živý. Je to tiché konstatování a platí o každém neaktivním záznamu.

**`strikethrough()` čeká, až si o něj řeknete.** Přeškrtnutí je tvrzení o
*obsahu* — „tahle hodnota není součástí součtu“ — což je správně u stornovaného
řádku objednávky a špatně u archivovaného zákazníka.

```php
->rowInactive($when, fn (InactiveRow $row) => $row->strikethrough())
->rowInactive($when, fn (InactiveRow $row) => $row->dim(false))        // jen přeškrtnutí
```

Přeškrtnutí dosáhne i na formulářové prvky řádku, nejen na texty. Je to záměr a
je to smysl celé věci: prohlížeč nedědí `text-decoration` do `<input>` a
nedotčený input je přesně to, co by čtenář jinak považoval za editovatelné.

**`color()` obarví celý řádek** stejným resolverem, jaký používá `rowColor()` —
kanonickým vlastníkem tónování řádků — takže se neaktivní a obarvený řádek
nemohou rozejít. Funguje libovolná sémantická role i syrový odstín a tónovaný
řádek dostane vlastní hover ve stejném odstínu a ztratí neutrální pruhování:

```php
->rowInactive($when, fn (InactiveRow $row) => $row->color('danger'))
```

Explicitní `rowColor()` na tabulce nad ním vždy vyhraje, což je právě to, co
tabulce dovolí tónovat podle stavu *a zároveň* označit stornované řádky:

```php
->rowColor(fn (Order $o) => $o->isOverdue() ? 'warning' : null)   // vyhraje tam, kde vrátí barvu
->rowInactive(
    fn (Order $o) => $o->status === 'cancelled',
    fn (InactiveRow $row) => $row->color('gray'),                 // použije se tam, kde rowColor() vrátil null
)
```

Řádek navíc nese `aria-disabled="true"` a `data-inactive="true"`, takže
asistivní technologie se to dozví a aplikace si může stylovat podle
`[data-inactive]`, aniž by publikovala view.

## Co zůstává živé a jak to vzít pryč

Akce řádku, zaškrtávátko výběru i kliknutí na záznam na neaktivním řádku fungují
dál. Je to výchozí stav záměrně: akce, která stav *vrací zpět* („obnovit“,
„zrušit storno“), bydlí právě ve sloupci akcí toho řádku, a zamknout celý řádek
by znamenalo vzít pryč jediný ovládací prvek, kterým se záznam vrací.

Zbylé dvě věci jdou vypnout, když je neaktivní záznam opravdu netečný.

### `selectable(false)` — držet ho mimo hromadné akce

```php
->rowInactive($when, fn (InactiveRow $row) => $row->selectable(false))
```

Zaškrtávátko řádku se stane `inert` (nejen neklikatelné — vypadne i z pořadí
tabulátoru), „vybrat stránku“ i zaškrtávátko v hlavičce řádek přeskočí a
podvržené přepnutí je odmítnuto. Odškrtnout jde vždycky, takže řádek zamčený *až
poté*, co byl vybrán, jde ze výběru pořád odebrat.

Jedno omezení, a to upřímné: výběr **„vybrat vše odpovídající“** je dotaz, ne
seznam — teprve to umožňuje vybrat 128 000 řádků — a predikát napsaný v PHP do
toho dotazu vložit nejde. Hromadná akce, která se neaktivních záznamů nesmí
dotknout, si záznam ověří sama, přesně jako u jakéhokoli jiného pravidla, které
databáze neumí vyjádřit.

### `actions(false)` — udělat z řádku netečný

```php
->rowInactive($when, fn (InactiveRow $row) => $row->actions(false))
```

Buňka s akcemi se stane `inert`, kontextové menu se pro ten záznam vůbec
nepostaví a `executeTableAction()` / `openActionModal()` ho odmítnou na serveru —
což pokrývá i *navázanou* record akci ([Record akce](record-actions.md):
`onClick()`, `onDoubleClick()`, `onKey()`), protože ty se spouštějí přes tytéž
dvě metody. Kliknutí do prohlížeče dorazí a pak neudělá nic.

`recordUrl()` je jediné klikání na celý řádek, kterého se to nedotkne: je to
prostý odkaz a otevřít stornovaný záznam je čtení, ne zápis. Když se záznam nemá
otevřít vůbec, ať `recordUrl(fn ($record) => …)` pro něj vrátí `null`.

### `editing(true)` — stav jako štítek

Pro tabulku, kde „neaktivní“ je barva a nic víc:

```php
->rowInactive($when, fn (InactiveRow $row) => $row->editing())
```

## Telefony dostanou stejný stav

Skládaná karta ([Responzivní rozvržení](overview.md#responzivni-layout)) nese
stejné ztlumení, stejné přeškrtnutí i stejné tónování a platí pro ni stejné dva
zámky na zaškrtávátku a řádku akcí. Není co navíc konfigurovat — karta je druhé
vykreslení téhož záznamu, ne druhá definice.

## Výchozí nastavení pro celý projekt

Back office, kde má každý stornovaný řádek vypadat stejně, to řekne jednou v
`config/wire-table.php`:

```php
'defaults' => [
    'inactive_rows' => [
        'strikethrough' => true,
        'color' => 'danger',
    ],
],
```

Každá tabulka, která zavolá `rowInactive()`, z toho vychází; konfigurátor na
úrovni tabulky to přebije klíč po klíči. Neznámý klíč vyhodí výjimku, místo aby
tiše nic nedělal.

## Rozšířený příklad

```php
use Livewire\Component;
use NyonCode\WireCore\Actions\Action;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Columns\TextInputColumn;
use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireTable\Support\InactiveRow;
use NyonCode\WireTable\Table;

class ListOrders extends Component
{
    use WithTable;

    public function table(Table $table): Table
    {
        return $table
            ->model(Order::class)
            ->selectable()
            ->columns([
                TextColumn::make('number')->searchable(),
                TextInputColumn::make('quantity'),      // na stornovaném řádku zamčeno // [tl! focus]
                TextColumn::make('total')->money('CZK'),
            ])
            ->actions([
                Action::make('restore')                 // pořád ovladatelná — právě ona stav vrací // [tl! focus:start]
                    ->visible(fn (Order $order) => $order->status === 'cancelled')
                    ->action(fn (Order $order) => $order->update(['status' => 'open'])),
            ])
            ->rowInactive(
                fn (Order $order) => $order->status === 'cancelled',
                fn (InactiveRow $row) => $row
                    ->strikethrough()
                    ->color('danger')
                    ->selectable(false),                // a držet ho mimo hromadné akce // [tl! focus:end]
            );
    }

    public function render()
    {
        return $this->getTableProperty();
    }
}
```

## Přehled API

### Na tabulce

```php
->rowInactive(bool|Closure $when = true, Closure|InactiveRow|null $configure = null)
// $when: fn ($record): bool — které záznamy jsou neaktivní (true = všechny)
// $configure: fn (InactiveRow $row) => … — konfiguruje se na místě, návratová hodnota se ignoruje

->hasInactiveRecords(): bool                 // zda tabulka stav vůbec deklaruje
->isRecordInactive(?Model $record): bool     // vyhodnocený predikát
->getInactiveRow(): InactiveRow              // konfigurace této tabulky, nasazená z configu
```

### Na `InactiveRow`

```php
->strikethrough(bool $condition = true)  // přeškrtnout texty řádku i jeho inputy — výchozí false
->dim(bool $condition = true)            // ztlumit texty řádku — výchozí true
->color(?string $color)                  // 'danger'|'warning'|… nebo libovolný odstín; null = bez tónu — výchozí null
->editing(bool $allowed = true)          // smí se neaktivní řádek dál editovat — výchozí false
->selectable(bool $allowed = true)       // smí se zaškrtnout — výchozí true
->actions(bool $allowed = true)          // jsou jeho akce ovladatelné — výchozí true

->isStrikethrough(): bool
->isDimmed(): bool
->getColor(): ?string
->allowsEditing(): bool
->allowsSelection(): bool
->allowsActions(): bool
```

## Řešení potíží

**Řádek vypadá neaktivně, ale buňka se pořád uloží.** Predikát se vyhodnocuje
pro záznam na obou cestách, takže to znamená, že si odporuje sám — ověřte, že
čte *uložený* atribut, ne takový, který vzniká až během vykreslení.

**Nic není přeškrtnuté.** `strikethrough()` je ve výchozím stavu vypnutý;
`dim()` je vzhled, který se dodává. Zapněte ho na tabulce, nebo pro celý projekt
v configu.

**Přeškrtnutí je vidět na textu, ale ne na odznaku nebo odkazu.** Potomek, který
si nastavuje vlastní barvu nebo dekoraci, si ji ponechá — je to totéž pravidlo,
díky kterému zůstane odznak čitelný uvnitř tónovaného řádku.

## Související

- [Inline editace](overview.md#inline-editace) — editory, které tenhle stav zamyká
- [Výběr](selection.md) — co zamčené zaškrtávátko znamená pro hromadné akce
- [Record akce](record-actions.md) — kliknutí, kterého se stav nedotkne
- [Vrstva gest](gestures.md) — fill handle, který pokrývá stejná brána
