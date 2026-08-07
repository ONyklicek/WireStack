# DateTimePicker

Jednotný date/time picker s režimy date, month, time a datetime.

```php
use NyonCode\WireForms\Components\DateTimePicker;
```

## Režimy

```php
// Jen datum
DateTimePicker::make('birth_date')->asDate()

// DateTime (výchozí)
DateTimePicker::make('event_at')
DateTimePicker::make('event_at')->asDateTime()

// Jen čas
DateTimePicker::make('alarm')->asTime()

// Jen měsíc + rok („YYYY-MM")
DateTimePicker::make('period')->asMonth()

// Explicitní setter režimu
DateTimePicker::make('x')->mode('date')      // 'date', 'month', 'time', 'datetime'
```

> `asMonth()` vždy renderuje nativní `<input type="month">` prohlížeče — vlastní kalendář
> nemá month-only mřížku — takže zůstane nativní i když předáš `->native(false)`.

> `asTime()` vybírá čas steppery hodin a minut. Pro pole, jehož časy jsou **sloty**
> — otevírací doba, termíny — ukládá [`TimePicker`](time-picker.md) tutéž hodnotu,
> ale vybírá ji ze seznamu v pevném intervalu.

## Omezení data

```php
DateTimePicker::make('start')
    ->minDate('2024-01-01')
    ->maxDate('2025-12-31')
    ->disabledDates(['2024-12-25', '2024-12-31'])
    ->firstDayOfWeek(1)           // pondělí
    ->closeOnDateSelection()
```

Meze přijmou cokoli, co jde přečíst jako datum — `Carbon`/`DateTimeInterface`,
nebo řetězec jako `'2026-07-10'`, `'10.07.2026'`, `'today'` či `'+1 week'` — a na
pozadí se převedou do tvaru, kterému widget rozumí. Mez, kterou přečíst nelze,
vyhodí výjimku, místo aby ji prohlížeč tiše zahodil.

```php
DateTimePicker::make('start')
    ->minDate(now())              // žádná data v minulosti
    ->maxDate(now()->addYear())
```

U režimu `datetime` může mez nést i čas — ten pak omezí hodiny jen v ten hraniční
den:

```php
DateTimePicker::make('slot')
    ->minDate('2026-07-10 08:30') // 10. července nejdřív od 08:30
    ->maxDate('2026-07-20 17:00') // 20. července nejpozději do 17:00
```

Horní mez zadaná na celý den pokrývá celý den: `->maxDate('2026-07-20')`
nechá 20. července volitelné až do 23:59.

## Volby času

```php
DateTimePicker::make('meeting')
    ->withSeconds()
    ->hoursStep(1)
    ->minutesStep(15)
    ->secondsStep(30)
```

## Formát

```php
DateTimePicker::make('date')
    ->format('Y-m-d')             // formát uložení
    ->displayFormat('d.m.Y')      // formát zobrazení
    ->timezone('Europe/Prague')
```

> `format()` a `timezone()` jsou opt-in a uplatní se při uložení. Bez nich se
> hodnota uloží přesně tak, jak ji vyrobil widget — přidání na existující field je
> tedy vědomá změna toho, co skončí ve sloupci, nikdy tichá. `timezone()` převádí
> oběma směry a jen u `datetime`: samotné datum nebo čas jsou wall-clock hodnoty,
> které by převod poškodil.

> `displayFormat()` používá tokeny PHP `date()` a mění jen to, co vidí uživatel —
> uložená hodnota zůstává beze změny. Ctí ho vlastní picker; formát zobrazení
> nativního inputu patří prohlížeči a locale uživatele.

## Psaní z klávesnice

Trigger je textové pole, ne tlačítko: hodnotu lze napsat, nejen vybrat. Napsaný
text se čte zpět stejným formátem, jakým se zobrazuje — `displayFormat()`, když
je nastavený, jinak tvar uložené hodnoty — takže pole ukazující
`9. 3. 2026 14:30` přesně tohle zpátky přijme.

Parser je benevolentní ke všemu kromě *pořadí* částí, které určuje formát. Při
`->displayFormat('j. n. Y H:i')` skončí všechny tyhle zápisy na stejné hodnotě:

```text
9. 3. 2026 14:30
9.3.2026 14:30
9/3/2026 14:30
9. 3. 26 14:30        dvojciferný rok patří do tohoto století
9. 3. 2026            čas se nenapsal, zůstává ten, který je právě nastavený
```

Zápis se potvrdí při opuštění pole a klávesou <kbd>Enter</kbd>; <kbd>Escape</kbd>
ho zahodí. Cokoli, co parser nepřečte — `31. 2. 2026`, hodina nad 23, den, který
vylučuje `minDate()`/`maxDate()`/`disabledDates()` — je odmítnuto celé a vrátí se
předchozí hodnota, takže se do stavu nikdy nedostane rozečtené datum. Vyprázdnění
pole hodnotu smaže.

Napsaná hodnota projde stejným ořezem jako vybraná: v hraniční den, který nese
čas, se hodiny stáhnou do meze místo odmítnutí — napsat `10. 3. 2026 07:00` při
`->minDate('2026-03-10 08:30')` uloží 08:30.

Cestu přes klávesnici zavřete tam, kde hodnota opravdu musí přijít z widgetu:

```php
DateTimePicker::make('slot')->typeable(false)
```

> `readOnly()` má přednost před `typeable()`: zavírá klávesnici *i* panel,
> protože hodnota není uživatelova, aby ji měnil jakoukoli cestou.
> `typeable(false)` zavírá jen klávesnici a kalendář nechává funkční.

> Psaní je vlastnost vlastního pickeru. Klávesnice nativního inputu patří
> prohlížeči a jediný způsob, jak ji vzít, je `readonly` — což by s ní vyplo i
> vlastní picker prohlížeče — takže `typeable(false)` pod `->native()` nemá
> žádný efekt.

## Nativní picker

Výchozí je vlastní Alpine picker. Přepnutí na ovládání prohlížeče:

```php
DateTimePicker::make('date')
    ->native()                     // použít nativní picker prohlížeče
    ->native(false)                // zpět na vlastní picker (výchozí)
```

Jedinou výjimkou je [`asMonth()`](#rezimy), který je vždy nativní.

## Metody

| Metoda | Typ | Popis |
|--------|------|-------------|
| `mode(string)` | string | Nastavit režim: `date`, `month`, `time`, `datetime` |
| `asDate()` | — | Alias pro `mode('date')` |
| `asTime()` | — | Alias pro `mode('time')` |
| `asMonth()` | — | Alias pro `mode('month')`; vždy nativní |
| `asDateTime()` | — | Alias pro `mode('datetime')` |
| `format(string)` | string | Formát uložení (Carbon kompatibilní) |
| `displayFormat(string)` | string | Formát zobrazení ukázaný uživateli |
| `minDate(string\|DateTimeInterface\|Closure)` | string | Nejdřívější volitelné datum; u `datetime` může nést i čas |
| `maxDate(string\|DateTimeInterface\|Closure)` | string | Nejpozdější volitelné datum; mez na celý den pokrývá celý den |
| `disabledDates(array\|Closure)` | array | Data, která nelze vybrat |
| `firstDayOfWeek(int)` | int | 0=neděle, 1=pondělí |
| `closeOnDateSelection()` | bool | Zavřít picker po výběru data |
| `withSeconds()` | bool | Zobrazit sloupec sekund v time pickeru |
| `hoursStep(int)` | int | Krok inkrementu hodin |
| `minutesStep(int)` | int | Krok inkrementu minut |
| `secondsStep(int)` | int | Krok inkrementu sekund |
| `timezone(string)` | string | Zobrazí hodnotu v této timezone a při uložení ji převede zpět do timezone aplikace; jen pro `datetime` |
| `native(bool $native = true)` | bool | Použít nativní ovládání prohlížeče místo vlastního pickeru (výchozí: `false`) |
| `typeable(bool\|Closure)` | bool | Umožnit hodnotu napsat, nejen vybrat (výchozí: `true`); jen vlastní picker |
| `disabled(bool\|Closure)` | bool | Znepřístupnit picker |
| `readOnly(bool\|Closure)` | bool | Read-only režim — bez psaní i bez panelu |
| `required()` | — | Označit jako povinné |
| `live()` | — | Spustit Livewire update při změně |

Label, hint, tooltip a další sdílené metody viz [Společné API pole](index.md#spolecne-api-pole).
