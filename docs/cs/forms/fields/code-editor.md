# CodeEditor

Editor kódu s monospace stylováním, čísly řádků a odsazením klávesou Tab. Bez externích závislostí — ukládá prostý text.

```php
use NyonCode\WireForms\Components\CodeEditor;
```

## Základní použití

```php
CodeEditor::make('script')
    ->language('php')
```

## Konfigurace

```php
CodeEditor::make('config')
    ->language('json')
    ->minHeight(300)
    ->withLineNumbers()
    ->maxLength(10000)
```

## Bez čísel řádků

```php
CodeEditor::make('query')
    ->language('sql')
    ->withLineNumbers(false)
```

## Podporované jazykové labely

Volání `language()` je **jen pro zobrazení** (ukázané v hlavičkové liště). Přijímá se libovolný řetězec:

```php
->language('php')
->language('javascript')
->language('json')
->language('sql')
->language('yaml')
->language('bash')
->language('plaintext')   // výchozí
```

Plné zvýrazňování syntaxe vyžaduje integraci externí knihovny (např. CodeMirror nebo Monaco) do JS buildu aplikace.

## Metody

| Metoda | Typ | Popis |
|--------|------|-------------|
| `language(string)` | string | Jazykový label zobrazený v hlavičce (výchozí `'plaintext'`) |
| `minHeight(int)` | int | Minimální výška editoru v pixelech (výchozí `200`) |
| `withLineNumbers(bool)` | bool | Zobrazit sloupec s čísly řádků (výchozí `true`) |
| `maxLength(int\|null)` | int | Maximální počet znaků |
| `placeholder(string\|Closure)` | string | Placeholder textarey |
| `disabled(bool\|Closure)` | bool | Znepřístupnit editor |
| `readOnly(bool\|Closure)` | bool | Read-only režim |
| `required()` | — | Označit jako povinné |
| `live()` | — | Spustit Livewire update při každém stisku klávesy |
| `debounce(int)` | ms | Debounce prodleva pro `live()` |

Label, hint, tooltip a další sdílené metody viz [Společné API pole](index.md#spolecne-api-pole).
