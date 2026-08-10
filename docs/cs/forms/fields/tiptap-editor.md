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
> [Začínáme → JavaScriptové assety](../../getting-started.md#javascriptove-assety).

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
| `minHeight(int)` | int | Minimální výška editoru v pixelech (výchozí `240`) |
| `maxLength(int\|null)` | int | Limit znaků s živým počítadlem |
| `disabled(bool\|Closure)` | bool | Znepřístupnit editor |
| `readOnly(bool\|Closure)` | bool | Read-only režim |
| `required()` | — | Označit jako povinné |
| `placeholder(string\|Closure)` | string | Placeholder zobrazený, když je prázdné |
| `live()` | — | Spustit Livewire update při každé změně |
| `debounce(int)` | ms | Debounce prodleva pro `live()` |

Label, hint, tooltip a další sdílené metody viz [Společné API pole](index.md#spolecne-api-pole).
