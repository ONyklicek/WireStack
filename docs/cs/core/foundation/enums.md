---
order: 40
summary: "Kontrakty, které enum implementuje, aby si pojmenoval vlastní popisek, barvu a ikonu — stav popsaný jednou, čtený každým povrchem."
---

# Enumy

Stavový enum ví, jak se jmenuje, jakou má barvu a jakou nese ikonu. Když to řekne
na sobě, a jednou, nemusí si badge sloupec, entry v infolistu a select držet každý
vlastní mapu týchž tří faktů — a právě tyhle mapy se rozejdou při prvním přidaném
stavu.

## Enumy

PHP enumy nelze stringifikovat pomocí `(string) $enum`, přesto Eloquent enum casty předávají surovou
instanci každému display a state surface. `EnumResolver` je jediný kanonický vlastník, který
normalizuje takové hodnoty; navazující balíčky (table, forms, infolists, exports) na něj delegují
místo re-enkódování `(string) $enum` nebo lokálních `match` map.

```php
use NyonCode\WireCore\Foundation\Support\EnumResolver;

EnumResolver::scalar($value);   // backed enum → ->value, unit enum → název case, jinak passthrough
EnumResolver::label($value);    // getLabel() → metoda label() → headline(název case); ne-enum passthrough
EnumResolver::display($value);  // label() + array/JSON → kompaktní JSON; (string)-safe všude
EnumResolver::color($value);    // HasColor → getColor(), jinak null
EnumResolver::icon($value);     // HasIcon  → getIcon(),  jinak null
EnumResolver::isEnum($value);   // bool — je to instance enumu?

EnumResolver::isEnumClass($value);       // bool — je to enum class-string?
EnumResolver::options(Status::class);    // [value => label] mapa z case enumu
EnumResolver::normalizeOptions($value);  // třída enumu → options() mapa; pole projdou skrz
```

Použijte `scalar()` pro klíče map, porovnání a copy hodnoty; `display()` (nebo `label()`), kdekoli se
hodnota zobrazuje. Ne-enum hodnoty vždy projdou beze změny, takže je bezpečné helpery volat na
cokoli.

`options()` pohání Filament-style enum-jako-options zkratku: jakýkoli option-based surface —
form `Select` / `Radio` / `CheckboxList` (přes sdílený trait `WireForms\Concerns\HasOptions`),
table `SelectColumn` a `SelectFilter`, plus generický `Column::editable()` / `filterable()` /
`filterAsSelect()` — přijímá `->options(Status::class)` a deleguje rozvinutí sem. Každý case
klíčuje přes `scalar()` a labeluje přes stejné kanonické `label()` resolvování, takže option čte
identicky jako odpovídající display buňka. Jednohodnotové form pole, jehož options pocházejí z enumu,
také získá automatické `in:` validační pravidlo (viz [Formuláře → Select](../../forms/fields/select.md#options-z-enumu)).

### Čím soubor je — rodina, ne formát

Každá plocha, která ukazuje soubor, musí nejdřív odpovědět na jednu otázku: je
tu obrázek, a když ne, co to vlastně je? Šest míst si na to odpovídalo samo a
všech šest odpovídalo stejně chudě — obrázek, nebo jedna šedá ikona dokumentu —
takže katalog, ceník, smlouva a archiv pro tiskárnu vypadaly jako čtyři totožné
šedé obdélníky.

`FileKind` je jediný vlastník té odpovědi. Jeho případy jsou **rodiny**, ne
formáty, a každý si nese odstín ze sdílené palety a ikonu ze sdílené sady —
takže do žádného z těch dvou slovníků nepřibývá nic nového.

```php
use NyonCode\WireCore\Foundation\Enums\FileKind;

FileKind::for('application/pdf');                  // FileKind::Document // [tl! focus:5]
FileKind::for(null, 'cenik-q1.xlsx');              // Spreadsheet — z názvu, když MIME chybí
FileKind::for('application/octet-stream', 'a.zip');// Archive — z názvu, když MIME nic neříká
FileKind::for('text/plain', 'export.csv');         // Spreadsheet — co by řekl každý, kdo to otevře

FileKind::extensionOf('cenik.ods');                // 'ODS' — nápis, z názvu samotného souboru
FileKind::extensionOf('report.final version');     // null — tohle není přípona, tak ji nevypisuj
```

Rozhoduje MIME typ, protože se čte z uloženého souboru, ne z toho, co o něm
tvrdil prohlížeč. Název se použije přesně ve dvou případech, oba skutečné: MIME
typ je **null** — řádky zapsané dřív, než se zaznamenával — nebo je to jeden z té
hrstky, co platí skoro na cokoli (`application/octet-stream`, `text/plain`), a
tedy nerozhoduje nic.

**Rodina není nápis.** `XLSX` a `ODS` jsou obojí `Spreadsheet` a nesmí obě hlásit
„XLSX“, takže písmena na dlaždici jsou z názvu souboru. Název bez použitelné
přípony spadne zpátky na název rodiny.

| Případ | Barva | Případ | Barva |
|--------|-------|--------|-------|
| `Image` | violet | `Presentation` | orange |
| `Video` | pink | `Archive` | yellow |
| `Audio` | teal | `Code` | slate |
| `Document` | blue | `Other` | gray |
| `Spreadsheet` | green | | |

Slovník je **uzavřený**. Takový, který si aplikace může přepsat, je takový, na
který se žádný balíček nemůže spolehnout — `Spreadsheet` musí znamenat tabulku —
takže co se netrefí, je `Other`, a vykreslí se jako vlastní přípona souboru na
neutrálním podkladu.

### Opt-in enum kontrakty

Enum použitý jako cast může implementovat kterýkoli z těchto pro řízení bohatšího vykreslení. Žijí pod
`Foundation\Contracts\Enum\` a jsou **odlišné** od builder-facing `Foundation\Contracts\HasLabel`
/ `HasIcon` (které nesou fluent settery pro komponenty).

| Kontrakt | Metoda | Efekt |
|----------|--------|--------|
| `Enum\HasLabel` | `getLabel(): ?string` | Display surface vykreslí tento label místo výchozího headline názvu case |
| `Enum\HasColor` | `getColor(): string\|Color\|null` | `BadgeColumn` / `IconColumn` / `IconEntry` auto-resolvují barvu |
| `Enum\HasIcon` | `getIcon(): string\|Icon\|null` | Stejné surface auto-resolvují ikonu |

```php
use NyonCode\WireCore\Foundation\Colors\Color;
use NyonCode\WireCore\Foundation\Contracts\Enum\HasColor;
use NyonCode\WireCore\Foundation\Contracts\Enum\HasLabel;

enum OrderStatus: string implements HasColor, HasLabel
{
    case Pending = 'pending';
    case Paid = 'paid';

    public function getLabel(): ?string        { return ucfirst($this->value); }
    public function getColor(): string|Color|null
    {
        return $this === self::Paid ? Color::Success : Color::Warning;
    }
}
```

Použití na úrovni sloupce viz [Table → Enum a JSON casty](../../table/columns/casts.md).

## Související

- [Barvy](colors.md) a [Ikony](icons.md) — slovníky, které enum jmenuje
- [BadgeColumn](../../table/columns/badge.md) — pořadí rozhodování, kterého se enum účastní
- [Enum a JSON casty](../../table/columns/casts.md) — co sloupec udělá s přetypovanou hodnotou
- [Workflow a přechody](../actions/workflow.md) — enumy jako stavy záznamu
