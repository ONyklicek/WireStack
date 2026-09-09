---
summary: "Plnohodnotný editor nad TipTapem a ProseMirrorem: tabulky, obrázky, zmínky a zarovnání, uložené jako HTML nebo JSON."
---

# TiptapEditor

Plnohodnotný rich text editor postavený na [TipTap](https://tiptap.dev/) / ProseMirror. Konfigurovatelný toolbar, volitelná rozšíření (tabulky, obrázky, zarovnání textu, zvýraznění) a HTML nebo JSON výstup.

```php
use NyonCode\WireForms\Components\TiptapEditor;
```

## Nastavení

Žádné. JavaScript editoru se dodává **předbundlovaný uvnitř balíčku** a Blade
pohled pole ho automaticky injektuje. Žádný npm install, žádný build krok a žádný
`app.js` import — jen použijte pole a funguje hned.

Editor je **code-split**: základní bundle (jádro TipTapu + vždy zapnutá rozšíření)
se servíruje na `/wire-forms/tiptap/tiptap-editor.js`, a volitelná rozšíření
(`withTables()` / `withImages()` / `withHighlight()` / `withTextAlign()`) jsou v
samostatném addon bundlu, který se načte jen když je nějaké pole na stránce zapne.
Oba sdílejí jeden chunk s jádrem, takže stránka bez těchto rozšíření stáhne méně a
zapnutí tabulek nikdy neposílá druhou kopii jádra editoru. Script tagy
`<script type="module">` se injektují jednou za stránku přes Livewire direktivu
`@assets`; registrují Alpine komponentu `tiptapEditor`, na kterou pohled spoléhá
(Alpine se dodává s Livewire).

> **Publikování assetu (volitelné).** Pokud má soubory servírovat váš webserver
> místo routy balíčku, publikujte je pomocí:
> ```bash
> php artisan vendor:publish --tag=laravel-assets --force
> ```
> To zkopíruje bundly do `public/vendor/wire-forms/` — celého stacku, nejen tohoto
> balíčku — a editor od té chvíle emituje tyhle cesty včetně cache-busteru. Publish
> zrcadlí `dist/` doslova, takže si entry pointy dál resolvují sdílený chunk relativně
> vůči `vendor/wire-forms/tiptap/`. Viz
> [Začínáme → JavaScriptové assety](../../start/getting-started.md#javascriptove-assety).

> **Přispěvatelé.** Bundly se generují z
> `packages/forms/resources/js/tiptap-editor.js` a `tiptap-editor-addons.js` a
> commitují (se sdíleným chunkem) do `packages/forms/dist/tiptap/`. Po editaci
> zdroje je přebuildujte pomocí:
> ```bash
> npm run build:forms-assets
> ```

---

## Základní použití

```php
TiptapEditor::make('content')
```

## Výchozí obsah

Editor se otevře nad hodnotou z `->default()` — kanonického výchozího nastavení,
které má každá komponenta; žádná metoda navíc jen pro editor. Je to **markup, ne
holý text**, takže šablona přichází předformátovaná:

```php
TiptapEditor::make('minutes')
    ->default('<h2>Zápis z porady</h2><p>Nějaký <strong>text</strong>.</p><ul><li>První bod</li></ul>')
```

Jak se to vyhodnotí, v tomto pořadí:

1. **Runtime formuláře hodnotu naseeduje.** `fill()` (a stejně tak výchozí stav
   modalové akce) zapíše `->default()` do state bagu pro každý klíč, který volající
   nedodal, takže editor se prostě otevře nad hodnotou, která už tam je.
2. **Editor ji naseeduje, když to hostitel neudělal** — `null` sloupec, ručně
   navázaná property — výchozí obsah dosadí vždy, když je navázaná hodnota
   prázdná, a rozparsovaný dokument pošle zpět do Livewire, takže uložení
   formuláře, kterého se uživatel ani nedotkl, uloží šablonu, a ne nic.
3. **Vyprázdněný editor není prázdný.** Smazání obsahu uloží `<p></p>`, takže
   znovuotevření dokumentu, který uživatel záměrně vyčistil, výchozí obsah
   *nevrátí*. U editačního formuláře, kde je sloupec skutečně `null`, přidejte
   `->defaultOnNull()`, aby default doplnil hodnotu i na straně serveru.

Při `->outputJson()` může být výchozí hodnotou TipTap JSON dokument jako řetězec,
nebo totéž HTML — HTML se tak jako tak rozparsuje na dokument a uloží jako JSON.

## Vlastní toolbar

```php
TiptapEditor::make('content')
    ->toolbarButtons([
        'bold', 'italic', 'underline',
        '|',
        'h2', 'h3',
        '|',
        'bulletList', 'orderedList',
        '|',
        'link', 'undo', 'redo',
    ])
```

Použijte `'|'` jako vizuální oddělovač mezi skupinami.

## Znepřístupnit konkrétní tlačítka

```php
TiptapEditor::make('content')
    ->disableToolbarButtons(['codeBlock', 'code'])
```

## Bez toolbaru

```php
TiptapEditor::make('content')
    ->disableAllToolbarButtons()
```

## Rozšíření

Zapínejte volitelná rozšíření jednotlivě:

```php
TiptapEditor::make('content')
    ->withTables()       // vkládání + editace tabulek
    ->withImages()       // vkládání obrázků (přes URL prompt)
    ->withTextAlign()    // tlačítka zarovnání left / center / right
    ->withHighlight()    // tlačítko zvýraznění textu
```

Když je rozšíření zapnuto, jeho toolbarové tlačítko se přidá automaticky.

## Zmínky

Zmínka se ukládá jako **identita**, nikdy jako jméno:

```html
<span data-type="mention" data-mention-trigger="#"
      data-mention-type="article" data-id="12">#Ceník 2026</span>
```

Žádné `href` v tom není a text je *fallback*. Každý render si záznam vyhledá
znovu, takže přejmenovaný článek se přejmenuje ve všech dokumentech, které ho kdy
zmínily, a odkaz nemůže přežít oprávnění, které ho povolilo. Cenou je, že uložený
obsah se už nezobrazuje vypsáním — viz
[Zobrazení obsahu se zmínkami](#zobrazeni-obsahu-se-zminkami) níže.

### Jeden trigger, více modelů

Trigger není model. `@` pojmenovávající lidi a `#` pojmenovávající cokoli, co web
publikuje, jsou tatáž funkce a ta druhá funguje jen tehdy, když jeden trigger
unese víc zdrojů:

```php
use Illuminate\Database\Eloquent\Builder;
use NyonCode\WireForms\Components\Mention;
use NyonCode\WireForms\Components\Mention\Source;

TiptapEditor::make('body')
    ->mentions(
        Mention::make('@')->source(
            Source::make(User::class)->titleAttribute('name')->label('Lidé'),
        ),
        Mention::make('#')->sources([                                    // [tl! focus:start]
            Source::make(Article::class)
                ->titleAttribute('title')
                ->label('Články')
                ->modifyOptionsQueryUsing(fn (Builder $query) => $query->published()),

            Source::make(Page::class)->titleAttribute('title')->label('Stránky'),
        ]),                                                              // [tl! focus:end]
    )
```

Právě proto dokument ukládá vedle id i morph typ: pod jedním `#` by `12` samo o
sobě neřeklo, jestli jde o článek, nebo o stránku.

Každý zdroj se dotazuje zvlášť a řádky se seskupí podle jeho popisku — `UNION` by
stál per-source scopování, což je důvod, proč zdroje sdílejí jeden trigger. Řádky
se napříč zdroji **neřadí** proti sobě: seznam říká, ze které skupiny řádek
pochází, místo aby předstíral, že ví, že článek poráží stránku.

### Scopování a autorizace

`modifyOptionsQueryUsing()` rozhoduje, co smí vložit **autor**:

```php
Source::make(Article::class)
    ->titleAttribute('title')
    ->modifyOptionsQueryUsing(fn (Builder $query) => $query->whereBelongsTo($team))
```

Na co se uložená zmínka rozbalí později, se scopuje znovu při renderu, kde už
může být čtenářem někdo úplně jiný — viz
[`MentionRegistry`](#modely-ktere-nevlastnite).

Vyhledávací endpoint nikdy neodpoví na prázdný dotaz: nefiltrovaný seznam zmínek
není vyhledávání, ale enumerační endpoint nad uživateli.

### Jak model udělat zmínitelným

Záznam sám je to jediné, co vždycky zná své aktuální jméno, takže tam patří i
render-time fakta:

```php
use NyonCode\WireCore\Foundation\Mentions\Contracts\Mentionable;

class Article extends Model implements Mentionable
{
    public function getMentionLabel(): string           // [tl! focus:start]
    {
        return $this->title;
    }

    public function getMentionUrl(): ?string
    {
        return $this->published ? route('articles.show', $this) : null;
    }                                                   // [tl! focus:end]
}
```

Vrácené `null` z `getMentionUrl()` je záměr: zmínka se vykreslí pojmenovaná, ale
neklikatelná.

### Modely, které nevlastníte

`User` z balíčku, `Page` od dodavatele — stejná fakta zaregistrujte při bootu:

```php
use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Foundation\Mentions\MentionRegistry;

public function boot(MentionRegistry $mentions): void
{
    $mentions->register(Page::class)
        ->titleAttribute('title')                                        // [tl! focus:start]
        ->url(fn (Model $page) => route('pages.show', $page))
        // Viditelnost podle čtenáře patří sem: záznam, který dotaz vyloučí, se
        // prostě nenajde, a nerozbalená zmínka se vykreslí jako prostý text.
        ->modifyQueryUsing(fn ($query) => $query->where('visibility', 'public')); // [tl! focus:end]
}
```

Smazaný a neviditelný záznam jdou stejnou cestou záměrně. Je to ta jediná, která
nic neprozradí — únikem je čerstvý titulek tažený rovnou z databáze.

Bez kontraktu i bez registrace se zmínka pořád vykreslí: zůstane jí popisek, se
kterým byl dokument napsán — správný tehdy, a už ne potom.

### Zobrazení obsahu se zmínkami

`{!! $post->body !!}` by vypsalo identity a žádné odkazy. Načtěte obsah zpět přes
renderer:

```blade
{{-- Blade, a kdekoli jinde --}}
<x-wire::rich-content :html="$post->body" class="prose" />
```

```php
// Infolist
HtmlEntry::make('body')->label('Tělo')

// Buňka tabulky — implikuje ->html()
TextColumn::make('body')->richContent()
```

Všechny tři vedou přes jednoho vlastníka
(`NyonCode\WireCore\Foundation\Mentions\MentionRenderer`), kterého lze zavolat i
přímo:

```php
use NyonCode\WireCore\Foundation\Mentions\MentionRenderer;

$html = app(MentionRenderer::class)->render($post->body);

// Jen identity — třeba pro rozeslání notifikací.
$mentioned = app(MentionRenderer::class)->extract($post->body);
```

Dokument jmenující dvanáct článků a tři uživatele stojí **dva** dotazy, ne
patnáct: reference se seskupí podle uloženého typu a načtou jedním `whereKey()`
na typ. Obsah bez zmínek se vrací byte po bytu a k DOM parseru se vůbec
nedostane.

> **Buňka tabulky se renderuje sama za sebe**, takže se dotazy dávkují v rámci
> jedné buňky, ne napříč stránkou: dvacet pět řádků s `richContent()` je dvacet
> pět vyhledání. Vyplatí se to na úzké tabulce dokumentů; ne na výpisu, který
> stejně ukazuje jen prvních osmdesát znaků.

### Psaní přes mezery

Ve výchozím stavu vypnuto. S `allowSpaces()` nemá suggestion jak poznat, kde
zmínka skončila, takže polyká větu za ní, dokud seznam něco nezavře:

```php
Mention::make('#')->allowSpaces()
```

Titulky se stejně dají najít podle prvního slova — `#cenik` najde `Ceník 2026`,
protože se matchuje na serveru proti celému sloupci.

### Doručení

Mention node a suggestion engine TipTapu se doručují jako **třetí** ESM entry
(`tiptap-editor-mentions.js`), injektovaný jen pro pole, které zmínky deklaruje.
Editor s tabulkami a bez zmínek si ho nikdy nestáhne a sdílený chunk
`@tiptap/core` se neduplikuje.

## Formát výstupu

```php
// Výchozí: HTML řetězec uložený v modelu
TiptapEditor::make('body')->outputHtml()

// Uložit jako TipTap JSON dokument (serializovaný jako JSON řetězec)
TiptapEditor::make('body')->outputJson()
```

## Limit znaků

```php
TiptapEditor::make('summary')
    ->maxLength(2000)    // zobrazí živé počítadlo, vynuceno rozšířením CharacterCount
```

## Výška

```php
TiptapEditor::make('content')
    ->minHeight(400)     // minimální výška v pixelech (výchozí 240)
```

## Read-only / disabled

```php
TiptapEditor::make('content')
    ->readOnly()
    ->disabled(fn () => ! $this->canEdit)
```

## Lokalizace

Editor si nenese vlastní angličtinu. Tooltipy toolbaru, popisky nadpisů i
prohlížečové prompty, které otevírá tlačítko odkazu a obrázku, se všechny
překládají z `wire-forms::fields.editor.*`, takže pole respektuje
`app()->getLocale()`. Angličtina (`en`) a čeština (`cs`) jsou součástí balíčku —
česká aplikace zobrazí *Tučné*, *Odrážkový seznam*, *Nadpis 2* a prompt
*URL odkazu*.

Titulky promptů se vyhodnocují v PHP a předávají se do Alpine konfigurace
editoru — proto se změna jazyka propíše i do řetězců, které žijí uvnitř JS bundlu.

[RichEditor](rich-editor.md#lokalizace) a
[MarkdownEditor](markdown-editor.md#lokalizace) popisují své toolbary z týchž
klíčů, takže všechny tři editory zní v každém jazyce stejně.

Formulaci změníte (nebo přidáte další jazyk) publikováním překladů a úpravou
`lang/vendor/wire-forms/{locale}/fields.php`:

```bash
php artisan vendor:publish --tag=wire-forms::translations
```

Popisky tlačítek zůstávají `H1` / `H2` / `H3` ve všech jazycích — to jsou
symboly, ne slova; překládá se tooltip.

## Dostupná toolbarová tlačítka

| Klíč | Popis |
|-----|-------------|
| `bold` | Tučné |
| `italic` | Kurzíva |
| `underline` | Podtržení |
| `strike` | Přeškrtnutí |
| `code` | Inline kód |
| `highlight` | Zvýraznění (vyžaduje `withHighlight()`) |
| `h1` | Nadpis 1 |
| `h2` | Nadpis 2 |
| `h3` | Nadpis 3 |
| `bulletList` | Neseřazený seznam |
| `orderedList` | Seřazený seznam |
| `blockquote` | Blockquote |
| `codeBlock` | Blok kódu |
| `link` | Hypertextový odkaz (otevře URL prompt) |
| `image` | Obrázek (vyžaduje `withImages()`) |
| `table` | Vložit tabulku (vyžaduje `withTables()`) |
| `alignLeft` | Zarovnat vlevo (vyžaduje `withTextAlign()`) |
| `alignCenter` | Zarovnat na střed (vyžaduje `withTextAlign()`) |
| `alignRight` | Zarovnat vpravo (vyžaduje `withTextAlign()`) |
| `undo` | Zpět |
| `redo` | Znovu |
| `\|` | Vizuální oddělovač |

## Srovnání s RichEditor

| Funkce | RichEditor | TiptapEditor |
|---------|-----------|--------------|
| Engine | `document.execCommand` (zastaralé) | ProseMirror (stabilní) |
| Cross-browser | Nekonzistentní | Konzistentní |
| Rozšíření | Žádná | Tabulky, obrázky, zarovnání, zvýraznění, … |
| Výstup | HTML | HTML nebo JSON |
| npm závislost | Ne | Ano |
| Náročnost nastavení | Nulová | `npm install` + jeden import |

## Metody

| Metoda | Typ | Popis |
|--------|------|-------------|
| `toolbarButtons(array)` | array | Přepsat seznam toolbarových tlačítek |
| `disableToolbarButtons(array)` | array | Odstranit konkrétní tlačítka |
| `disableAllToolbarButtons()` | — | Skrýt toolbar úplně |
| `default(string\|Closure)` | string | Předformátovaný dokument, nad kterým se prázdný editor otevře |
| `defaultOnNull()` | — | Nechat `default()` doplnit i existující `null` při fill |
| `outputHtml()` | — | Uložit obsah jako HTML (výchozí) |
| `outputJson()` | — | Uložit obsah jako TipTap JSON řetězec |
| `withImages(bool)` | bool | Zapnout rozšíření obrázků + tlačítko |
| `withTables(bool)` | bool | Zapnout rozšíření tabulek + tlačítko |
| `withTextAlign(bool)` | bool | Zapnout rozšíření text-align + tlačítka |
| `withHighlight(bool)` | bool | Zapnout rozšíření zvýraznění + tlačítko |
| `mentions(Mention\|array ...)` | Mention | Triggery zmínek, které editor nabízí |
| `minHeight(int)` | int | Minimální výška editoru v pixelech (výchozí `240`) |
| `maxLength(int\|null)` | int | Limit znaků s živým počítadlem |
| `disabled(bool\|Closure)` | bool | Znepřístupnit editor |
| `readOnly(bool\|Closure)` | bool | Read-only režim |
| `required()` | — | Označit jako povinné |
| `placeholder(string\|Closure)` | string | Placeholder zobrazený, když je prázdné |
| `live()` | — | Spustit Livewire update při každé změně |
| `debounce(int)` | ms | Debounce prodleva pro `live()` |

### `Mention`

| Metoda | Typ | Popis |
|--------|-----|-------|
| `Mention::make(string)` | string | Znak triggeru — `@`, `#` |
| `sources(array)` | array\<Source\> | Modely, které tento trigger nabízí |
| `source(Source)` | Source | Trigger s právě jedním modelem za sebou |
| `allowSpaces(bool)` | bool | Matchovat i přes mezeru (výchozí `false`) |
| `limit(int)` | int | Strop celého seznamu bez ohledu na počet zdrojů (výchozí `15`) |

### `Mention\Source`

| Metoda | Typ | Popis |
|--------|-----|-------|
| `Source::make(string)` | class-string\<Model\> | Model, který zdroj nabízí |
| `titleAttribute(string)` | string | Sloupec zobrazený v nabídce |
| `searchAttribute(string)` | string | Sloupec, proti kterému se matchuje, není-li to zobrazovaný |
| `label(string)` | string | Nadpis skupiny, pod kterou řádky patří |
| `limit(int)` | int | Kolik řádků zdroj přispěje (výchozí `5`) |
| `modifyOptionsQueryUsing(Closure)` | Closure | Scopování dotazu nabídky |

Label, hint, tooltip a další sdílené metody viz [Společné API pole](index.md#spolecne-api-pole).
