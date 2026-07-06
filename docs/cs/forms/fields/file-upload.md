# FileUpload

Upload souborů s náhledem, validací a zpracováním obrázků.

```php
use NyonCode\WireForms\Components\FileUpload;
```

## Základní použití

```php
FileUpload::make('attachment')
    ->disk('public')
    ->directory('attachments')
```

## Validace

```php
FileUpload::make('document')
    ->acceptedFileTypes(['application/pdf', 'image/*'])
    ->maxSize(10240)       // KB
    ->minSize(100)         // KB
    ->multiple()
    ->maxFiles(5)
    ->minFiles(1)
```

## Režim obrázku

```php
FileUpload::make('photo')
    ->image()
    ->imageResizeTargetWidth(1920)
    ->imageResizeTargetHeight(1080)
    ->imageCropAspectRatio('16:9')
```

## Avatar

```php
FileUpload::make('avatar')
    ->avatar()             // kulatý náhled, jeden soubor
```

## Úložiště

```php
FileUpload::make('file')
    ->disk('s3')
    ->directory('uploads/2024')
    ->visibility('public')
    ->preserveFilenames()
```

## Metody

| Metoda | Popis |
|--------|-------------|
| `disk(string)` | Storage disk |
| `directory(string)` | Adresář uploadu |
| `visibility(string)` | Viditelnost souboru (`public`, `private`) |
| `preserveFilenames()` | Zachovat původní názvy souborů |
| `acceptedFileTypes(array)` | Povolené MIME typy |
| `maxSize(int)` | Max velikost souboru v KB |
| `minSize(int)` | Min velikost souboru v KB |
| `multiple()` | Povolit více souborů |
| `maxFiles(int)` | Max počet souborů |
| `minFiles(int)` | Min počet souborů |
| `image()` | Režim jen obrázky |
| `avatar()` | Avatar režim (kulatý, jeden) |
| `imageResizeTargetWidth(int)` | Šířka změny velikosti v pixelech |
| `imageResizeTargetHeight(int)` | Výška změny velikosti v pixelech |
| `imageCropAspectRatio(string)` | Poměr stran ořezu (např. `16:9`) |
| `disabled(bool\|Closure)` | Znepřístupnit uploader |
| `required()` | Označit jako povinné |

Label, hint, tooltip a další sdílené metody viz [Společné API pole](index.md#common-field-api).
