# TimePicker

Picker jen pro čas. Čas se vybírá ze **seznamu slotů** v pevném intervalu, ne
otáčením stepperů.

```php
use NyonCode\WireForms\Components\TimePicker;
```

```php
TimePicker::make('opens_at')
```

Po otevření pole se ukáže rolovatelný seznam — `00:00`, `00:30`, `01:00`, … —
a kliknutí na položku ji uloží a panel zavře.

## TimePicker vs. DateTimePicker::asTime()

Obojí ukládá tutéž hodnotu ve stejném formátu. Liší se jen panelem:

| | Panel |
|---|---|
| `TimePicker::make('x')` | Seznam časů po `minutesStep()` |
| `DateTimePicker::make('x')->asTime()` | Steppery hodin / minut / sekund |

`TimePicker` zvol tam, kde jsou časy sloty — otevírací doba, termíny, rozvrhy.
[`asTime()`](date-time-picker.md) zvol tam, kde je platná libovolná minuta dne,
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

## Všechno ostatní je DateTimePicker

Hodnotová strana je celá zděděná, takže tohle se chová přesně podle dokumentace
[`DateTimePicker`](date-time-picker.md):

```php
TimePicker::make('opens_at')
    ->withSeconds()               // ukládá H:i:s; sloty pořád padají na :00
    ->displayFormat('H:i')
    ->native()                    // nativní <input type="time"> prohlížeče
    ->placeholder('Vyber čas')
```

Panel vždycky nese tlačítko **Clear**, takže volitelné pole jde zase vyprázdnit
bez vlastního setteru.

Ukládaná hodnota je `H:i`, s `withSeconds()` pak `H:i:s`.

> `timezone()` je zděděná, ale nedělá tu nic — přesně jako u
> `DateTimePicker::asTime()`: holý čas je hodnota nástěnných hodin a převod mezi
> zónami by ji rozbil. Platí jen pro `datetime`.

> `->native()` předá celé pole vlastnímu časovému prvku prohlížeče, takže s ním
> odejde i seznam slotů, interval a zakázané sloty — nativní input si `min`/`max`
> vynucuje po svém.

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
ze [společného API pole](index.md#spolecne-api-pole) pro label, hint, tooltip a
další sdílené metody.
