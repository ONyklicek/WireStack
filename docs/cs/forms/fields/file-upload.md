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

## Ukládání & merge (store-on-submit)

Vybrání (nebo přetažení) souboru ho nahraje do Livewire **dočasného** úložiště a
vypíše pod drop zónou jako *pending* upload — na trvalé úložiště se přesune až
při **uložení** formuláře. Model tak zůstává bez orphanů: abandonovaný formulář
nic nezanechá (dočasný upload sám expiruje). Při uložení se každý pending upload
uloží na nakonfigurovaný `disk()`/`directory()` (ctí `visibility()` i
`preserveFilenames()`) a pole dehydruje na uloženou cestu (cesty).

- **single** pole si nechá nejnovější upload a dehydruje na jednu cestu (nebo `null`);
- **multiple** pole **merguje** — nové uploady se přidají k už uloženým cestám,
  takže další nahrávání nikdy nezahodí to, co tam bylo, a dehydruje na pole cest.

Hostitel musí skládat form runtime (`WithForms`, nebo table/form action modal);
plumbing uploadu (Livewire file handling, merge krok i store při uložení) se
zapojí automaticky.

## Seznam souborů & odebrání

Pole vypíše vše, co má aktuálně ve stavu, pod drop zónou — už uložené cesty (ze
záznamu nebo z předchozího uložení) **i** pending uploady. Obrázky ukážou náhled
(uložené přes disk URL, pending přes dočasný náhled), ostatní ikonu dokumentu;
uložený soubor odkazuje sám na sebe, pending je označen *Pending upload*. Každý
má tlačítko **odebrat**, které ho vyhodí ze stavu podle indexu. Odebrání
uloženého souboru nechá fyzický soubor na disku nedotčený (úklid je věcí
aplikace); odebrání pending uploadu ho jen zahodí.

Uložené cesty se resolvují na URL přes nakonfigurovaný `disk()` (hodnota, která
už je plná URL, se použije tak, jak je). `deletable(false)` zobrazí soubory
read-only, bez tlačítka odebrat:

```php
FileUpload::make('gallery')
    ->image()
    ->multiple()
    ->disk('public')
    ->deletable(false)   // zobrazit soubory read-only
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
| `deletable(bool)` | Zda lze už uložené soubory odebrat (výchozí `true`) |
| `disabled(bool\|Closure)` | Znepřístupnit uploader |
| `required()` | Označit jako povinné |

Label, hint, tooltip a další sdílené metody viz [Společné API pole](index.md#common-field-api).
