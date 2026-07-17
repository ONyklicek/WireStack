# BelongsToSelect

`BelongsToSelect` je select pole vědomé si relace pro `belongsTo` asociace.

## Základní použití

```php
use NyonCode\WireForms\Components\BelongsToSelect;

BelongsToSelect::make('company_id')
    ->relationship('company', 'name')
    ->label('Company')
    ->searchable()
```

To resolvuje options ze souvisejícího modelu místo vyžadování manuálního pole `options()`.

`searchable()` select bez `preload()` hledá v související tabulce na serveru, jak uživatel
píše (párováním title atributu, omezeno na 50 výsledků), a resolvuje label vybrané
hodnoty jedním klíčovaným lookupem — plný seznam options se nikdy neposílá klientovi.
Přidejte `preload()` pro načtení celého seznamu předem a filtrování na straně klienta. Explicitní
callback `getSearchResultsUsing()` (zděděný ze `Select`) přepíše vestavěné hledání.

## Běžné volby

```php
BelongsToSelect::make('company_id')
    ->relationship('company', 'name')
    ->searchable()
    ->preload()
    ->required()
```

| Metoda | Účel |
|--------|---------|
| `relationship('company', 'name')` | Resolvovat options z relace a title sloupce |
| `searchable()` | Hledat v související tabulce na serveru, jak uživatel píše |
| `preload()` | Načíst plný seznam options okamžitě a filtrovat na straně klienta |
| `modifyOptionsQueryUsing()` | Zúžit nebo seřadit dotaz souvisejícího modelu |
| `createOptionForm()` | Zobrazit inline create formulář pro nový související záznam (auto-vytvoří přes relaci) |
| `createOptionUsing()` | Přizpůsobit, jak se nová option perzistuje |
| `editOptionForm()` | Editovat vybraný související záznam — auto-naplní a zapíše zpět přes jeho relace |
| `fillEditOptionUsing()` / `updateOptionUsing()` | Plná kontrola nad naplněním / uložením editu (každý dostává resolvnutý `$record`) |

## Zúžené options

```php
BelongsToSelect::make('company_id')
    ->relationship('company', 'name')
    ->modifyOptionsQueryUsing(fn ($query) => $query->where('active', true))
```

## Inline create

```php
use NyonCode\WireForms\Components\TextInput;

BelongsToSelect::make('company_id')
    ->relationship('company', 'name')
    ->createOptionForm([
        TextInput::make('name')->required(),
    ])
```

Inline create používá stejný modal flow jako [Select](select.md#create-edit-options): tlačítko „+ Create" v panelu comboboxu otevře modal formulář a při uložení se nový záznam vytvoří na relaci (nebo přes `createOptionUsing()`), vybere a sloučí do dropdownu bez obnovení stránky. `editOptionForm()` ze `Select` zde funguje také.

S nastavenou relací `createOptionForm()` **auto-vytvoří související model** i bez `createOptionUsing()` — introspektuje `belongsTo` relaci, najde související třídu a vytvoří záznam z dat modálu. Pole dostává rodičovský záznam formu od runtime, což je to, co umožňuje tohle (a každý write-back níže) bez jakéhokoli drátování.

## Write-back přes relace

`editOptionForm()` jen nezobrazí modal — **čte z a zapisuje do souvisejícího záznamu přes jeho vlastní relace**. Co související model *vlastní*, se zapíše automaticky; co *sdílí*, se nechává na explicitním callbacku. Každý callback (`fillEditOptionUsing`, `updateOptionUsing`) dostává resolvnutý související **`$record`**, takže můžeš číst i zapisovat přes libovolnou relaci bez znovu-načítání.

### Auto-fill

Edit modal se naplní z vybraného souvisejícího záznamu a vytáhne **jen sloupce, které edit formulář deklaruje** — nikdy id, timestampy ani nesouvisející sloupce:

```php
BelongsToSelect::make('author_id')
    ->relationship('author', 'name')
    ->editOptionForm([
        TextInput::make('name'),
        TextInput::make('email'),
    ])
```

**Tečkový název pole** projde vnořenou relaci při fillu (prázdná relace → `null`):

```php
TextInput::make('company.name')   // naplní z $author->company->name
```

### Matice

Při uložení rozhoduje o způsobu zápisu **druh relace**, na kterou tečkové nebo repeater pole cílí:

| Relace | Tvar pole | Automatický write-back | Explicitní alternativa přes `updateOptionUsing($record)` |
|---|---|---|---|
| `belongsTo`, `morphTo` | tečkové `origin.name` | jen fill (sdílený rodič) | `$record->origin?->update(...)`, `associate()`, `dissociate()` |
| `hasOne`, `morphOne` | tečkové `profile.bio` | `updateOrCreate` (vytvoří když chybí) | `$record->profile()->save($model)` |
| `hasMany`, `morphMany` | `Repeater->relationship()` | sync: create / update / delete | `$record->books()->saveMany([...])` |
| `belongsToMany`, `morphToMany`, `morphedByMany` | `Repeater->relationship()` + pivot sloupce | pivot sync: attach / detach / update | `syncWithoutDetaching()`, `toggle()`, `updateExistingPivot()` |
| `hasManyThrough`, `hasOneThrough` | tečkové / repeater | jen fill (read-through) | zapiš intermediate sám |

Polymorfní varianty (`morphOne`, `morphMany`, `morphTo`, `morphToMany`, `morphedByMany`) se chovají přesně jako jejich nepolymorfní báze a nastaví polymorfní `*_type`/`*_id` samy.

### Vlastněné 1:1 — `hasOne` / `morphOne`

Tečkové pole se zapíše zpět přes relaci, updatne se in-place nebo **vytvoří když chybí**:

```php
BelongsToSelect::make('author_id')
    ->relationship('author', 'name')
    ->editOptionForm([
        TextInput::make('name'),
        TextInput::make('profile.bio'),   // hasOne
        TextInput::make('avatar.url'),    // morphOne
    ])
```

Pro plnou kontrolu perzistuj sám přes `save()` relace:

```php
->updateOptionUsing(function ($record, array $data) {
    $avatar = $record->avatar ?? new Avatar;
    $avatar->url = data_get($data, 'avatar.url');
    $record->avatar()->save($avatar);   // u nového záznamu nastaví morph type
    $record->update(['name' => $data['name']]);
})
```

### Vlastněné 1:N — `hasMany` / `morphMany`

Edituj kolekci přes relationship-backed **Repeater**; write-back ji synchronizuje (create nové, update matchnuté, delete odebrané):

```php
use NyonCode\WireForms\Components\Repeater;

BelongsToSelect::make('author_id')
    ->relationship('author', 'name')
    ->editOptionForm([
        TextInput::make('name'),
        Repeater::make('books')
            ->relationship('books')
            ->schema([TextInput::make('title')]),
    ])
```

Pro nedestruktivní zápis (create/update, nikdy delete) použij `saveMany()` v callbacku:

```php
->updateOptionUsing(function ($record, array $data) {
    $books = collect($data['books'])->map(function (array $row) {
        $book = ! empty($row['id']) ? Book::find($row['id']) : new Book;
        $book->title = $row['title'];
        return $book;
    })->all();
    $record->books()->saveMany($books);
})
```

### Many-to-many s pivotem — `belongsToMany` / `morphToMany` / `morphedByMany`

Každý repeater item nese **klíč souvisejícího modelu plus pivot sloupce**. Fill přečte pivot z každého načteného řádku; write-back ho `sync()`ne (attach / detach / update pivot sloupců):

```php
BelongsToSelect::make('author_id')
    ->relationship('author', 'name')
    ->editOptionForm([
        TextInput::make('name'),
        Repeater::make('tags')
            ->relationship('tags')
            ->schema([
                TextInput::make('id'),     // klíč souvisejícího tagu
                TextInput::make('role'),   // pivot sloupec (deklarovaný přes ->withPivot('role'))
            ]),
    ])
```

Funguje beze změny jak **custom pivot model** (`->using(...)`, takže se aplikují jeho casty), tak **ad-hoc pivot** (jen `->withPivot(...)`, základní `Pivot`/`MorphPivot`), protože flow běží na úrovni relace. Pro jinou pivot strategii sáhni do callbacku:

```php
->updateOptionUsing(function ($record, array $data) {
    $sync = collect($data['tags'])
        ->mapWithKeys(fn ($row) => [$row['id'] => ['role' => $row['role']]])
        ->all();

    $record->tags()->syncWithoutDetaching($sync);       // attach/update, nikdy detach
    // $record->tags()->toggle([$id1, $id2]);           // flip členství uvedených id
    // $record->tags()->updateExistingPivot($id, [...]); // jen pivot sloupce připojeného řádku
})
```

### Sdílený rodič — `belongsTo` / `morphTo`

`belongsTo`/`morphTo` ukazuje na záznam, který související model nevlastní (mnoho autorů může sdílet jednu company), takže se **nikdy nemění automaticky** — fill ho načte k zobrazení a ty ho zapíšeš explicitně:

```php
BelongsToSelect::make('author_id')
    ->relationship('author', 'name')
    ->editOptionForm([
        TextInput::make('name'),
        TextInput::make('company.name'),
    ])
    ->updateOptionUsing(function ($record, array $data) {
        $record->update(['name' => $data['name']]);
        $record->company?->update(['name' => data_get($data, 'company.name')]);
    })
```

**Re-asociace** je buď edit vlastního sloupce (foreign key je reálný sloupec, takže ho auto write-back zvládne) nebo idiomatické `associate()` / `dissociate()`:

```php
->updateOptionUsing(function ($record, array $data) {
    $record->company()->associate(Company::find($data['company_id']));  // re-point
    // $record->company()->dissociate();                                // odpojit
    $record->save();
})
```

U `morphTo` `associate()` re-pointuje na **jiný typ** (nastaví `*_type` i `*_id`); `dissociate()` vynuluje oba.

### Read-through — `hasManyThrough` / `hasOneThrough`

Read-through relace nemá jednoznačný write-back (který intermediate rodič by vlastnil nový vzdálený řádek, nelze určit), takže fill ji stále načte k zobrazení, ale **nikdy se auto-nezapíše** — žádné osiřelé ani smazané řádky. Zapiš ji explicitně přes intermediate v `updateOptionUsing($record)`, když potřebuješ.

### Uvnitř Repeateru

Protože runtime propaguje rodičovský záznam k relačním polím všude, všechno výše funguje stejně i pro `BelongsToSelect` vnořený **uvnitř Repeater itemu**, per item.

## Související dokumentace

- [Select](select.md)
- [Přehled formulářů](../overview.md)
