# RichEditor

Rich text editor s konfigurovatelným toolbarem.

```php
use NyonCode\WireForms\Components\RichEditor;
```

## Použití

```php
RichEditor::make('content')
    ->toolbarButtons([
        'bold', 'italic', 'underline',
        'h2', 'h3',
        'bulletList', 'orderedList',
        'link', 'blockquote',
    ])
```

## Výchozí toolbar

Když není zavoláno `toolbarButtons()` (a není přepsán config), výchozí toolbar je:

| Klíč tlačítka | Popis |
|------------|-------------|
| `bold` | Tučné |
| `italic` | Kurzíva |
| `underline` | Podtržení |
| `strike` | Přeškrtnutí |
| `h2` | Nadpis 2 |
| `h3` | Nadpis 3 |
| `bulletList` | Neseřazený seznam |
| `orderedList` | Seřazený seznam |
| `link` | Hypertextový odkaz |
| `blockquote` | Blokový citát |
| `codeBlock` | Blok kódu |
| `undo` | Zpět |
| `redo` | Znovu |

Výchozí lze přepsat globálně přes `config('wire-forms.rich_editor.toolbar')`.

## Znepřístupnit konkrétní tlačítka

```php
RichEditor::make('content')
    ->disableToolbarButtons(['codeBlock', 'attachFiles'])
```

## Znepřístupnit všechna tlačítka

```php
RichEditor::make('content')
    ->disableAllToolbarButtons()   // prostý rich text, bez toolbaru
```

## Limit znaků

```php
RichEditor::make('summary')
    ->maxLength(500)
```

## Metody

| Metoda | Typ | Popis |
|--------|------|-------------|
| `toolbarButtons(array)` | array | Nahradit toolbar touto sadou tlačítek |
| `disableToolbarButtons(array)` | array | Odstranit konkrétní tlačítka z toolbaru |
| `disableAllToolbarButtons()` | — | Nezobrazovat toolbar (prostý rich text) |
| `maxLength(int\|null)` | int | Limit znaků |
| `disabled(bool\|Closure)` | bool | Znepřístupnit editor |
| `readOnly(bool\|Closure)` | bool | Read-only režim |
| `required()` | — | Označit jako povinné |

Label, hint, tooltip a další sdílené metody viz [Společné API pole](index.md#spolecne-api-pole).
