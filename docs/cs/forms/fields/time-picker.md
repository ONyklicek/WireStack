---
summary: Čas vybíraný na desktopu na ciferníku a na telefonu ze seznamu slotů, v pevném intervalu.
---

# TimePicker

Picker jen pro čas, jehož hodnoty jsou **sloty** v pevném intervalu. Na desktopu
se čas vybírá na **ciferníku** — hodiny, pak minuty — a potvrdí tlačítkem OK; na
telefonu se vybírá ze seznamu slotů.

```php
use NyonCode\WireForms\Components\TimePicker;
```

```php
TimePicker::make('opens_at')
```

Od [mobilního breakpointu](../../start/configuration.md#mobil) pole výš ukáže
otevřené pole ciferník; pod ním rolovatelný seznam — `00:00`, `00:30`, `01:00`,
… — kde klepnutí slot uloží a panel zavře. Obojí zapisuje tutéž hodnotu.

## Ciferník

Desktopový panel se drží time pickeru z Fiori:

- **Dvě velká tlačítka** ukazují vybíraný čas (`14 : 30`) a říkají, kterou část
  ciferník nastavuje; kliknutím na kterékoli se k ní vrátíte.
- **Ciferník hodin** má dva kruhy — venku 1–12, uvnitř 13–23 a 00. Kliknutí
  nebo tažení vybírá a puštění na hodině přepne ciferník na minuty. **Ciferník
  minut** přichytává na `minutesStep()`; s `withSeconds()` následují sekundy.
- **Okraj ukazuje, co jde vybrat.** Tečka je pod každým číslem a mezi čísly u
  každé minuty, na kterou krok dosáhne — šedesát míst při výchozím kroku jedné
  minuty, mezi čísly žádná při kroku 15. Ručička není: vybraná hodnota je
  značka na okraji a vyplněná bublina, která ji čte (i `07` mezi dvěma čísly),
  a hodnota pod ukazatelem dostane před kliknutím světlejší. Uprostřed
  ciferníku je název nastavované části.
- **Zašedlé nejde vybrat.** Hodina celá mimo meze nebo
  [denní okno](date-time-picker.md#denni-casove-okno) a minuta mimo krok nebo
  meze jsou zašedlé a ukazatel je ignoruje.
- **Před OK se nic nezapíše.** Ciferník upravuje koncept: **OK** ho uloží,
  **Zrušit** a <kbd>Escape</kbd> nechají hodnotu, jaká byla, **Teď** nastaví
  koncept na aktuální čas (zaokrouhlený dolů na krok a vtažený do mezí) a
  **Vymazat** pole vyprázdní. Prázdné pole se otevře na aktuálním čase vtaženém
  do mezí, ne o půlnoci.
- **Klávesnice.** <kbd>↓</kbd> v poli nebo ikona hodin otevře panel s fokusem na
  ciferníku — je to slider: šipky posouvají nastavovanou část a přeskakují
  zašedlé, <kbd>Enter</kbd> přejde na minuty a pak potvrdí. Čas jde do pole vždy
  i napsat.

## TimePicker vs. DateTimePicker::asTime()

Obojí ukládá tutéž hodnotu ve stejném formátu. Liší se jen panelem:

| | Panel |
|---|---|
| `TimePicker::make('x')` | Ciferník (na telefonu seznam časů po `minutesStep()`) |
| `DateTimePicker::make('x')->asTime()` | Tentýž ciferník, nastavovaný průběžně bez OK (na telefonu steppery) |

`TimePicker` zvolte tam, kde jsou časy sloty — otevírací doba, termíny, rozvrhy.
[`asTime()`](date-time-picker.md) zvolte tam, kde je platná libovolná minuta dne,
nebo když se režim musí měnit.

## Interval

Rozestup slotů je zděděný `minutesStep()`, výchozí **30**:

```php
TimePicker::make('opens_at')                    // 00:00, 00:30, 01:00 …
TimePicker::make('opens_at')->minutesStep(15)   // 00:00, 00:15, 00:30 …
```

Samostatný setter `interval()` neexistuje — je to tentýž pojem pod názvem, který
už měl. `hoursStep()` a `secondsStep()` jsou zděděné, ale tady nedělají nic:
seznam slotů má jeden krok, ne tři.

Interval omezuje **seznam**, ne hodnotu. Čas jde napsat rovnou do triggeru, takže
`08:07` zůstává dosažitelný i při třicetiminutovém kroku — napsaný čas odmítnou
jen meze. Viz [Psaní z klávesnice](date-time-picker.md#psani-z-klavesnice).

## Meze

`minDate()` / `maxDate()` se čtou jako časy a sloty mimo ně **zakážou**, takže
rozsah zůstane vidět, místo aby se seznam tiše zkrátil:

```php
TimePicker::make('opens_at')
    ->minDate('08:00')
    ->maxDate('17:00')
```

Panel se otevře na aktuální hodnotě, nebo na prvním slotu, který meze dovolují —
pole od rána do večera se tedy neotevře o půlnoci. Platí to i na mobilu, kde se
z panelu stane spodní sheet.

Pro otevírací dobu jsou přirozenější `minTime()` / `maxTime()` —
[denní časové okno](date-time-picker.md#denni-casove-okno). U `TimePickeru` se
čtou stejně jako meze a když jsou nastavené obě, vyhrává přísnější:

```php
TimePicker::make('opens_at')
    ->minTime('06:00')
    ->maxTime('18:00')
```

## Všechno ostatní je DateTimePicker

Hodnotová strana je celá zděděná, takže tohle se chová přesně podle dokumentace
[`DateTimePicker`](date-time-picker.md):

```php
TimePicker::make('opens_at')
    ->withSeconds()               // ukládá H:i:s; sloty pořád padají na :00
    ->displayFormat('H:i')
    ->typeable(false)             // jen seznam — do triggeru se psát nedá
    ->native()                    // nativní <select> se sloty
    ->placeholder('Vyber čas')
```

Panel vždycky nese tlačítko **Clear**, takže volitelné pole jde zase vyprázdnit
bez vlastního setteru.

Ukládaná hodnota je `H:i`, s `withSeconds()` pak `H:i:s`.

> `timezone()` je zděděná, ale nedělá tu nic — přesně jako u
> `DateTimePicker::asTime()`: holý čas je hodnota nástěnných hodin a převod mezi
> zónami by ji rozbil. Platí jen pro `datetime`.

> `->native()` vykreslí sloty jako nativní `<select>` prohlížeče, ne jako
> `<input type="time">`: `step` časového vstupu se do kolečka telefonu nedostane
> (iOS nabízí každou minutu), takže hodnotu na slotu udrží jen select. Nabízí
> sloty uvnitř `minDate()`/`maxDate()` po intervalu, popsané podle
> `displayFormat()`, a navíc aktuální hodnotu, pokud leží mezi dvěma sloty.
> `->nativeOnMobile()` udělá totéž jen pod mobilním breakpointem a nad ním seznam
> slotů ponechá, ať je interval nebo sekundy jakékoli — viz
> [nativní jen na telefonu](date-time-picker.md#nativni-jen-na-telefonu). Meze
> server hlídá v každém případě; hodnota mezi sloty chybou není, protože napsat ji
> na desktopu je dovolené.

> Na telefonu `->touchOnMobile()` promění seznam slotů v dotykové kolečko —
> sloupce hodin a minut v bottom sheetu — viz
> [dotykové kolečko na telefonu](date-time-picker.md#dotykove-kolecko-na-telefonu).

## Režim je zamčený

`TimePicker` je časový picker natrvalo. Setter režimu — a s ním i zděděné aliasy
`asDate()`, `asMonth()` a `asDateTime()`, které přes něj vedou — vyhodí
`FormConfigurationException`:

```php
TimePicker::make('opens_at')->asDate();       // vyhodí výjimku
TimePicker::make('opens_at')->mode('date');   // vyhodí výjimku
TimePicker::make('opens_at')->mode('time');   // v pořádku — už jím je
```

Není to kosmetika: panel je seznam slotů a nic jiného, takže pole, které by se
dostalo do režimu `date`, by vykreslilo picker bez kalendáře. Pokud se režim musí
měnit, je to `DateTimePicker`.

## Metody

| Metoda | Typ | Popis |
|--------|-----|-------|
| `mode(string)` | string | Zamčeno na `time`; jakýkoli jiný režim vyhodí `FormConfigurationException` |

Všechny ostatní metody pocházejí z [DateTimePicker](date-time-picker.md#metody) a
ze [společného API pole](index.md#spolecne-api-pole) pro popisek, hint, tooltip a
další sdílené metody.
