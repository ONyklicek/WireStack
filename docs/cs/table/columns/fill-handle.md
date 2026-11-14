---
order: 29
summary: Tažení jedné hodnoty dolů přes řádky pod ní, jako v Excelu — celý rozsah zapsaný jedním requestem.
---

# Fill handle (vyplňování tažením jako v Excelu)

Táhnutím přenesete hodnotu z jedné editovatelné buňky na řádky pod ní — stejně
jako v Excelu nebo Google Sheets. Celý rozsah se zapíše **jedním** requestem.

Zapíná se pro každou tabulku:

```php
use NyonCode\WireTable\Columns\SelectColumn;
use NyonCode\WireTable\Columns\TextInputColumn;
use NyonCode\WireTable\Columns\ToggleColumn;

public function table(Table $table): Table
{
    return $table
        ->model(Task::class)
        ->fillHandle()                                  // [tl! focus]
        ->columns([
            TextInputColumn::make('reference')->fillable(false),   // [tl! focus]
            SelectColumn::make('status')->options([
                'open' => 'Otevřeno',
                'done' => 'Hotovo',
            ]),
            ToggleColumn::make('is_urgent'),
        ]);
}
```

Najetím myší na vyplňovatelnou buňku — nebo jejím zaměřením — se v pravém dolním
rohu objeví malý čtvereček; jakmile na něj dojedete, rozbalí se do popsaného
tlačítka pro kopírování. Tažením dolů se pokryté řádky zvýrazní. Dokud tlačítko
nepustíte, nezapíše se nic a neodejde žádný request. Escape tažení zruší.

Reakce na najetí je záměr: kdyby bylo nutné nejdřív kliknout, otevřeli byste
editor jen kvůli zobrazení úchytu. Zaměřená buňka si úchyt udrží, takže neuteče
z řádku, do kterého zrovna píšete.

## API

```php
// Table
->fillHandle(bool $condition = true)   // zapnutí (výchozí: vypnuto)
->isFillHandleEnabled(): bool
->fillMaxRecords(int $max)             // strop řádků na jeden request (výchozí 500)
->getFillMaxRecords(): int

// Column
->fillable(bool $condition = true)     // výchozí true pro editovatelné sloupce
->isFillable(): bool
```

`fillable()` má smysl jen na sloupci, který už je editovatelný — zobrazovací
sloupec vyplnit nelze nikdy. Vypněte ho tam, kde opakování jedné hodnoty nedává
smysl nebo je nebezpečné: číslo faktury, unikátní kód, token vázaný na záznam.

## Co vyplnění doopravdy dělá

Každý záznam projde **stejnou cestou jako jedna inline editace**: vlastní
kontrolou `canEdit()`, vlastní validací, vlastní verzí pro optimistické zamykání.
Request je jeden, zápisy jsou po záznamech.

Je to záměr, ne opomenutá optimalizace. Jediné `UPDATE … WHERE id IN (…)` by
obešlo Eloquent události, casty a mutátory, nesáhlo by na `updated_at` — což je
přesně to, co optimistický zámek porovnává — a neumělo by vyjádřit sloupec
ukládaný přes `editableUsing()`, relaci ani pivot. Svislé tažení navíc dosáhne
jen na vyrenderované řádky, takže je jejich počet stejně omezený stránkou.

### Vyplnění není všechno nebo nic

Když jeden řádek prohraje závod o optimistický zámek nebo je pro uživatele
zakázaný, **nezahodí** to řádky, které se uložily. Každý záznam dostane vlastní
výsledek a klient se smíří buňku po buňce — potvrdí ty zapsané a vrátí zpět jen
ty ostatní. Částečné vyplnění hlásí `„Uloženo :filled z :total řádků"`.

Celou transakci vrátí zpět jen infrastrukturní chyba.

### Bezpečnost

- Endpoint odmítne rovnou, pokud tabulka nezavolala `fillHandle()` — nelze ho
  tedy použít proti tabulce, která tuhle možnost nikdy nenabídla.
- Záznamy se hledají přes vlastní dotaz tabulky, takže klíč mimo něj neodpovídá
  ničemu a je ohlášen jako nenalezený — podvržený request se nedostane na řádek,
  který tabulka nikdy nezobrazila.
- Oprávnění sloupce (`->permission()`) i `canEdit()` na úrovni záznamu se vynucují
  znovu na serveru; klientský stav `disabled()` je jen kosmetický.
- `fillMaxRecords()` omezuje, kolik toho jeden request smí zapsat.

## Události

Každá vyplněná buňka vyvolá `CellUpdating` a `CellUpdated` přesně jako jedna
inline editace, takže auditní listener uvidí vyplnění bez jakékoli změny.
Samostatná hromadná událost neexistuje.

## Vložení bloku z tabulkového procesoru

Se zapnutým `fillHandle()` se blok buněk vložený do editovatelné buňky —
zkopírovaný z Excelu, Numbers, Google Sheets nebo z této tabulky — rozloží tak,
jak ho vloží tabulkový procesor: řádek po řádku přes řádky pod ní, buňku po buňce
přes **vyplnitelné** sloupce vpravo. Text bez tabulátoru a bez konce řádku je
jedna hodnota pro jednu buňku a nechá se inputu, stejně jako psaní.

Blok odejde jako **jeden** request `fillTableCells()`, jedna položka na buňku,
takže každé pravidlo, které platí pro vyplnění, platí na serveru i pro každou
vloženou buňku: validace sloupce, jeho oprávnění, `canEdit()` záznamu,
`fillable(false)` i verze zámku. Zamčené a needitovatelné buňky se přeskočí a co
přesahuje poslední řádek nebo sloupec, se zahodí. Přepínač bere `1`, `true`,
`yes` (a `ano`, `x`, `✓`) jako zapnuto a cokoli jiného jako vypnuto. Buňky ukazují
vloženou hodnotu, dokud request běží, a každá, kterou server odmítne, se vrátí na
svou hodnotu.

**Kopírování ven.** V tabulce, kterou klávesnice ovládá po řádcích
(`selectable()` nebo record actions), <kbd>Ctrl</kbd>/<kbd>⌘</kbd>+<kbd>C</kbd> na
řádku s focusem zkopíruje vybrané řádky stránky jako text oddělený tabulátory,
nebo jen ten řádek, když žádný vybraný není. Tabulkový procesor ho vloží jako
blok a stejně tak se dá vložit zpět do tabulky. Každá buňka se zkopíruje tak, jak
se čte, editovatelná jako hodnota svého inputu; sloupce výběru a akcí se
vynechají.

## Rozsah

Tažení vyplňuje **jen svisle** — jeden sloupec, dolů nebo nahoru od zdrojové
buňky. Vodorovné tažení, obdélníkové výběry buněk a rozpoznávání posloupností
(1, 2, 3 …) implementované nejsou; tažená hodnota se duplikuje, nikdy
neextrapoluje. Vložený blok pokrývá řádky i sloupce.

## Poznámky

- Funguje myší, dotykem i perem přes Pointer Events; tažení za okraj viewportu
  automaticky roluje.
- Úchyt se nikdy neobjeví na buňce se zakázaným ovládacím prvkem ani na
  buňce, která je pro daný záznam readonly — ty žádný editovatelný input nevykreslí.
- Stejně jako jedna inline editace tabulku **nepřekresluje**: buňky se smíří samy,
  takže Alpine stav všech ostatních editovatelných buněk přežije.
- Tabulka s `queryCached()` bude po vyplnění servírovat cachovaná data až do
  vypršení TTL — přesně jako po jakékoli jiné mutaci.
- **Vyplnění se řadí do fronty, vždy jeden request.** Každé posílá verze, které
  vrátilo to předchozí. Táhnout znovu dřív, než dorazí poslední odpověď, je
  v pořádku — počká si, místo aby odešlo s verzemi, přes které už server přešel a
  odmítl by je jako zastaralý zápis.

## Rozlišení zámku

Verze řádku je jeho `updated_at` jako unixový timestamp, takže **dva zápisy uvnitř
jedné vteřiny jsou nerozlišitelné**. Zastaralá verze se tam neodhalí.

V praxi to hraje roli jen při opakovaném zápisu do stejných řádků v rychlém sledu
— proto klient requesty řadí. Pokud voláte `fillTableCells()` sami — z testu,
skriptu nebo vlastního frontendu — posílejte verze, které vrátilo předchozí
volání; nepoužívejte znovu ty, se kterými jste začali.

## Související

- [Editace a filtry na úrovni sloupce](editing.md) — jak funguje jedno inline uložení
- [TextInputColumn](text-input.md) · [SelectColumn](select.md) · [ToggleColumn](toggle.md)
- [Vrstva gest](../gestures.md) — handle je jedna z jejích schopností;
  `gestures(false)` ho zavře i s endpointem
