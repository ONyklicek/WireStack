# MarkdownEditor

Markdown editor s toolbarovými zkratkami a volitelným live náhledem. Ukládá prostý Markdown text.

```php
use NyonCode\WireForms\Components\MarkdownEditor;
```

## Základní použití

```php
MarkdownEditor::make('description')
    ->minHeight(300)
```

## S náhledovou záložkou

```php
MarkdownEditor::make('content')
    ->withPreview()    // výchozí — přidá přepínač záložek Write / Preview
```

## Náhled vedle sebe

```php
MarkdownEditor::make('article')
    ->livePreview()    // editor a náhled vykreslené vedle sebe
```

## Bez náhledu

```php
MarkdownEditor::make('notes')
    ->withPreview(false)
```

## Limit znaků

```php
MarkdownEditor::make('bio')
    ->maxLength(500)   // zobrazí počítadlo a vynutí limit
```

## Toolbarové zkratky

Toolbar poskytuje klávesnicí přístupná tlačítka pro:

| Tlačítko | Výstup |
|--------|--------|
| **B** | `**bold**` |
| *I* | `*italic*` |
| ~~S~~ | `~~strikethrough~~` |
| `</>` | `` `inline code` `` |
| H | `## Heading` |
| List | `- item` |
| Quote | `> blockquote` |

## Vykreslení náhledu

Vestavěný náhled zvládá: nadpisy (`#`, `##`, `###`), bold/italic/strikethrough, inline kód, odkazy, blockquoty a neseřazené/seřazené seznamy. Pro plné GFM vykreslení uložený Markdown post-processujte na straně serveru knihovnou jako [CommonMark](https://commonmark.thephpleague.com/).

Náhled běží v prohlížeči a zapisuje se přes `x-html`, takže syrové HTML v Markdownu se **escapuje, nevykresluje**: `<img src=x onerror=…>` se zobrazí jako text. URL odkazů jsou navíc omezené na `http(s):`, `mailto:`, `#` a cesty od kořene — cokoli jiného se změní na `#`, takže přes náhled nelze podstrčit `javascript:` odkaz.

## Lokalizace

Tooltipy toolbaru i popisky záložek Psát/Náhled pocházejí ze sdílené slovní
zásoby editorů `wire-forms::fields.editor.*` — ze stejných klíčů, jaké používají
[TiptapEditor](tiptap-editor.md#lokalizace) a
[RichEditor](rich-editor.md#lokalizace), takže všechny tři editory zní v každém
jazyce stejně. Angličtina (`en`) a čeština (`cs`) jsou součástí balíčku; česká
aplikace zobrazí *Tučné*, *Kód v textu* a záložky *Psát* / *Náhled*.

Formulaci změníte (nebo přidáte další jazyk) publikováním překladů a úpravou
`lang/vendor/wire-forms/{locale}/fields.php`:

```bash
php artisan vendor:publish --tag=wire-forms::translations
```

## Metody

| Metoda | Typ | Popis |
|--------|------|-------------|
| `withPreview(bool)` | bool | Zobrazit záložky Write/Preview (výchozí `true`) |
| `livePreview(bool)` | bool | Editor a náhled vedle sebe |
| `minHeight(int)` | int | Minimální výška v pixelech (výchozí `200`) |
| `maxLength(int\|null)` | int | Maximální počet znaků |
| `placeholder(string\|Closure)` | string | Placeholder textarey |
| `disabled(bool\|Closure)` | bool | Znepřístupnit editor |
| `readOnly(bool\|Closure)` | bool | Read-only režim |
| `required()` | — | Označit jako povinné |
| `live()` | — | Spustit Livewire update při každém stisku klávesy |
| `debounce(int)` | ms | Debounce prodleva pro `live()` |

Label, hint, tooltip a další sdílené metody viz [Společné API pole](index.md#spolecne-api-pole).
