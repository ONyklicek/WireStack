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

> **Publikování assetu (volitelné).** Pokud dáváte přednost servírování souborů přes
> vlastní asset pipeline/CDN, publikujte je pomocí:
> ```bash
> php artisan vendor:publish --tag=wire-forms::assets
> ```
> To zkopíruje bundly do `public/vendor/wire-forms/`.

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
