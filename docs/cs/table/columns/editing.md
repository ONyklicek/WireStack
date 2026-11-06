---
order: 28
summary: Editace hodnoty přímo v buňce, ve které je vidět, a filtrování sloupce z jeho vlastní hlavičky.
---

# Editace a filtry na úrovni sloupce

<a id="column-level-filtering"></a>
## Filtrování na úrovni sloupce

Kromě dedikovaných tříd Filter může mít jakýkoli sloupec inline filtr ve své hlavičce.

```php
// Select filtr v hlavičce sloupce (jedna hodnota)
TextColumn::make('status')
    ->filterable()
    ->filterAsSelect(['active' => 'Active', 'inactive' => 'Inactive'])

// Multi-select filtr (více hodnot → odpovídá kterékoli, whereIn). Renderuje stejný
// searchable combobox jako wire-forms Select — vyhledávání je defaultně zapnuté.
BadgeColumn::make('role')
    ->filterAsMultiSelect([
        'admin' => 'Administrator',
        'editor' => 'Editor',
        'viewer' => 'Viewer',
    ], 'Libovolná role')
    ->filterSearchable(false)               // vypnutí vyhledávání pro krátký seznam

// Boolean filtr
BooleanColumn::make('is_active')
    ->filterable()
    ->filterAsBoolean()

// Filtr rozsahu data
TextColumn::make('created_at')
    ->filterable()
    ->filterAsDateRange()

// Filtr rozsahu čísel
TextColumn::make('price')
    ->filterable()
    ->filterAsNumberRange(0, 10000)        // min, max, volitelný krok

// Vlastní logika filtru
TextColumn::make('name')
    ->filterable()
    ->filterUsing(fn (Builder $query, mixed $value) => $query->where('name', 'like', "%{$value}%"))
    ->filterDebounce(500)

// Filtr s operátorem
TextColumn::make('age')
    ->filterable()
    ->filterOperator('>=')
```

### API filtru na úrovni sloupce

Filtr hlavičky sloupce je umístění kanonického `Filter` — pomocné metody
`filterAs*()` jsou tenké factory nad `TextFilter` / `SelectFilter` / `DateFilter` /
`NumberRangeFilter` / `TernaryFilter`, nebo předejte hotový přes `->filter()`. Sdílený
engine, chipy a query-string persistenci viz [Filtry na úrovni sloupce](../filters/column-level.md).

```php
->filterable(bool $filterable = true, string $type = 'text', array|string $options = [])
->isFilterable(): bool
->filter(Filter $filter)                                                   // připoj hotový kanonický filtr
->getFilter(): ?Filter
->filterAsSelect(array|string $options, ?string $placeholder = null)       // jedna hodnota; searchable combobox
->filterAsMultiSelect(array|string $options, ?string $placeholder = null)  // více hodnot (whereIn); searchable combobox
->filterSearchable(bool $condition = true)                                 // přepnutí vyhledávání (defaultně zapnuté)
->filterAsDate(string|DateTimeInterface|null $minDate = null, string|DateTimeInterface|null $maxDate = null)
->filterAsDateRange(string|DateTimeInterface|null $minDate = null, string|DateTimeInterface|null $maxDate = null)
->filterAsNumberRange(?float $min = null, ?float $max = null, ?float $step = null)
->filterAsBoolean(?string $trueLabel = null, ?string $falseLabel = null)
->filterOperator(string $operator)     // '=', '!=', '>', '<', '>=', '<=', 'like' (výchozí, částečná shoda), 'starts_with', 'ends_with'
->filterDebounce(int $ms)
->filterPlaceholder(?string $placeholder)
->filterUsing(Closure $fn)             // fn(Builder $query, mixed $value)
```

---

## Inline editace

**Editor určuje typ sloupce**, ne přepínač: použijte
[TextInputColumn](text-input.md), [SelectColumn](select.md),
[ToggleColumn](toggle.md) nebo [CheckboxColumn](checkbox.md). Obyčejný sloupec
žádný editor nevykreslí.

`editable()` je vypínač editoru u dedikovaného sloupce a zároveň serverová brána
pro zápis do toho sloupce:

```php
TextInputColumn::make('name')
    ->editable(fn () => auth()->user()->isAdmin())   // false vykreslí prostou hodnotu
    ->editableRules(fn ($record) => ['required', 'max:255'])
    ->editableUsing(function ($record, $column, $value) {
        $record->update([$column => $value]);
    })
```

Pojmenování typu editoru — `editable(true, 'select', […])` — vyhodí výjimku:
žádná view ho nikdy nečetla, takže by tiše nedělalo nic. Použijte `SelectColumn`.

Argument `options` u `filterable()` / `filterAsSelect()` přijímá i PHP enum —
rozbalí se na `value => label` stejně jako u `SelectColumn`/`SelectFilter`.
Viz [Možnosti z enumu](select.md#moznosti-z-enumu).

### API inline editace

```php
->editable(bool $editable = true): static
->isEditable(): bool
->authorizeInline(?string $ability): static
->getInlineEditAbility(): ?string
->canInlineEdit(): bool
->fillable(bool $condition = true): static
->isFillable(): bool
->editableRules(Closure $callback): static // fn (?Model $record): array
->getEditableRules(?Model $record): array
->editableUsing(Closure $callback): static // vlastní callback pro uložení
->getEditableCallback(): ?Closure
```

`authorizeInline()` pojmenuje Laravel Gate ability ověřovanou při inline editaci.
`editable(false)` vypne editor a odmítne zápisy do sloupce; `fillable(false)`
samostatně zabrání zápisu přes fill handle. `editableUsing()` nahradí výchozí
zápis atributu vlastním callbackem pro uložení. Samotný editor stále pochází z
dedikovaného typu sloupce.

### Jak fungují inline uložení

Uložení buňky (`updateTableCell`) **překreslí tabulku** a buňka si přitom ochrání vlastní stav.
Všechno odvozené od zapsané hodnoty — summary, rollup, badge počítaný ze stejného sloupce, pozice
řádku pod aktuálním řazením — je v okamžiku zápisu zastaralé a spravit to umí jen render.

Buňka morph přežije proto, že její root nese `wire:ignore.self`, takže jí Livewire nesahá na
atributy ani na Alpine stav. Právě proto se k ní ale nová hodnota nemůže dostat přes tento root:
doručuje se na **sync uzlu**, malém potomkovi, který morph aktualizuje a který si buňka hlídá.
Všechno tohle dělá jedna sdílená Alpine komponenta (`wireEditableCell`): text inputy, selecty
i toggly ji používají, takže se chovají konzistentně.

- **Optimistic + rollback.** Buňka hned ukáže novou hodnotu, pak zavolá server; když uložení selže
  (validace, oprávnění, chyba), vrátí se na poslední serverem potvrzenou hodnotu a zobrazí zprávu.
- **Optimistic locking.** Každý edit nese verzi řádku. Když se řádek od chvíle, kdy ho buňka
  načetla, změnil, uložení se odmítne jako konflikt: buňka načte aktuální hodnotu a zobrazí zprávu
  o konfliktu **přímo na buňce** (červený stav na text/select/toggle, bez toastu nebo nastavení
  `NotificationManager`) — dva lidé se tak tiše nepřepíšou. Jakýkoli re-render — tick pollingu,
  zápis z modalu, příchozí změna z jiné relace — obnoví přes sync uzel hodnotu buňky *i* její
  verzi, takže další edit se porovnává proti tomu, co je opravdu v databázi. Volitelně lze pro
  konflikty vyvolat i (nápadnější) toast přes `Table::notifyEditConflicts()` — ten už vyžaduje
  zapojený notifikační systém (toast container); inline hláška funguje i bez něj. Z čeho se verze
  skládá, popisuje [oddíl níže](#presne-verze-s-haslockversion).
- **Buňka je během vlastního zápisu zamčená — a nic jiného.** Od uložení do odpovědi serveru je
  text input `readonly` a nese `aria-busy`; select, toggle a checkbox změnu provedenou mezitím
  vrátí. Zamčená, nikdy `disabled`: zakázaný prvek ztratí focus, takže po Enteru šel další stisk
  klávesy i Tab do prázdna. Zbytek tabulky zůstává editovatelný — z ukládané buňky lze Tabem
  rovnou přejít do další a psát dál.
- **Zápisy jednoho záznamu jdou za sebou, s verzí, kterou vytvořil ten předchozí.** Livewire posílá
  na komponentu jeden požadavek najednou a pozdější volání podrží — s argumenty, se kterými vzniklo.
  Druhá buňka téhož řádku uložená, zatímco první byla na cestě, proto odcházela s verzí z doby
  *před* prvním zápisem a server ji odmítl jako cizí editaci. Každá buňka teď počká, až se
  předchozí zápis záznamu vyřídí, a verzi si přečte teprve při odeslání. Vaše vlastní zápisy se
  nikdy nepřou; zápis někoho jiného mezi nimi je pořád jiná verze a pořád se odmítne.
- **Odchod ze stránky editaci tiše neztratí.** Zápis, který už dorazil na server, tam doběhne, ať
  prohlížeč udělá cokoli (endpointy buněk volají `ignore_user_abort()`). `wire:navigate` počká na
  zápisy, které jsou ještě na cestě, uloží napsaný a neodeslaný text a pak sám pokračuje — ptá se
  jen tehdy, když hodnotu nejde uložit (odmítnutá, nevalidní). Obnovení stránky, zavření panelu
  nebo obyčejný odkaz podržet nejde, takže dokud je něco neuložené nebo se ještě zapisuje,
  prohlížeč zobrazí vlastní výzvu; napsaný text se přitom začne ukládat, takže zůstat znamená ho
  uložit.
- **Vypnutí renderu.** `Table::refreshAfterEdit(false)` se vrací k odpovědi bez HTML. Vyplatí se
  jen u tabulky, kde je dotaz za renderem drahý a na editované hodnotě nic na obrazovce nezávisí:
  buňka se z odpovědi sesynchronizuje pořád, okolí ne.
- **Serverová autorizace.** Klientský `disabled()` stav je jen kosmetika — `disabled()` na úrovni záznamu
  buňka (i oprávnění sloupce) se znovu vynutí na serveru v `updateTableCell`, takže forged request
  nemůže zapsat do zamčené buňky.
- **Tabulka umí zamknout celý řádek.** `Table::rowInactive()` označí záznam za neaktivní —
  stornovaný, zrušený, archivovaný — a každý editovatelný sloupec na tom řádku odmítne zápis přes
  tutéž bránu, fill handle včetně. Je to druhý, nezávislý zdroj per-record `disabled()` stavu
  z předchozího bodu: buňku zamkne kterýkoli z nich, ať byl deklarován první nebo druhý. Viz
  [Neaktivní záznamy](../inactive-records.md).

### Přesné verze s `HasLockVersion`

Ve výchozím stavu je verzí záznamu jeho `updated_at`, resolvovaný přes vlastní timestamp sloupec
modelu (takže `const UPDATED_AT` je respektován). Ten sloupec se ukládá **na sekundy**, takže zámek
má jednosekundovou díru: dva zápisy v jedné sekundě zanechají stejné razítko a klient, který drží
razítko z prvního, se o druhém nedozví. Na gridu, který vyplňuje víc lidí najednou, se práce
odehrává právě v té sekundě.

`HasLockVersion` ji zavře. Model dostane celočíselný sloupec, který při každém Eloquent updatu
zvedá **databáze** (`SET lock_version = lock_version + 1`, nikdy `N + 1` spočítané v PHP, takže dva
procesy ukládající týž řádek dostanou dvě verze), a hodnota, kterou zápis vytvořil, se načte zpět
do modelu:

```php
Schema::table('order_lines', function (Blueprint $table) {
    $table->unsignedBigInteger('lock_version')->default(0); // [tl! focus]
});
```

```php
use NyonCode\WireCore\Foundation\Support\HasLockVersion;

class OrderLine extends Model
{
    use HasLockVersion; // [tl! focus]

    // protected string $lockVersionColumn = 'revision';   // jiný název sloupce
}
```

Razítko je pak `{lock version}.{updated_at}` — obojí, protože zápis přes query builder
(`OrderLine::query()->update()`, `increment()`) nespustí žádnou událost modelu a lock version
nezvedne, ale `updated_at` posune; s obojím v razítku je konfliktem kterýkoli z nich. Nic jiného se
nemění: každý editovatelný sloupec, fill handle, editovatelné položky panelu i `optimisticLock()`
akce čtou verzi přes tentýž `RecordVersion`. Audit sloupec vynechává — mění se při každém updatu a
nikdo ho nezapsal.

## Omezení editace, kterou nevlastníte

Sloupec z nainstalovaného [modulu](../../panels/modules.md) deklaruje svoji
vlastní validaci a aplikace k ní přidá vlastní pravidlo přes
[hook `cell.updating`](../../core/plugins/hooks.md):

```php
$manager->hook(Hook::CellUpdating, function (CellUpdatingPayload $payload) {
    if ($payload->record->approved_at !== null) {                 // [tl! focus]
        $payload->refusal = 'Zamčeno, dokud je faktura schválená.'; // [tl! focus]
    }                                                             // [tl! focus]

    return $payload;
}, for: Invoice::class);
```

`$payload->value` je dehydrovaná hodnota, která se chystá zapsat; její změna změní
to, co doopravdy dopadne. Nastavení `$payload->refusal` zápis zastaví a do
prohlížeče dorazí jako vlastní chybová hláška buňky — stejný tvar, jaký vyrobí
neúspěšná validace.

**Záleží na tom, kde běží.** Dispatchuje se uvnitř `CellEditPipeline::commit()`, po
kontrole oprávnění sloupce, po kontrole optimistického zámku a po jeho validaci —
takže callback zužuje, co se zapíše, a nemůže obejít stráž, kterou sloupec
deklaroval. Je to zároveň jediné místo, které sdílí inline editor
a [fill handle](fill-handle.md), takže pravidlu napsanému tady neuteče ani tažení
přes sloupec.
