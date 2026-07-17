---
order: 23
nav: false
---

# PollColumn

Pokročilý auto-obnovující sloupec se stavovými automaty, sledováním postupu a pollingem podle podmínky. Ideální pro joby na pozadí, živý stav, progress bary.

```php
use NyonCode\WireTable\Columns\PollColumn;
```

## Základní polling

```php
PollColumn::make('status')
    ->intervalSeconds(5)
    ->stateDisplays([
        'pending' => 'Waiting...',
        'processing' => 'In Progress',
        'completed' => 'Done',
        'failed' => 'Failed',
    ])
    ->stateColors([
        'pending' => 'gray',
        'processing' => 'info',
        'completed' => 'success',
        'failed' => 'danger',
    ])
    ->stateIcons([
        'pending' => 'clock',
        'processing' => 'refresh',
        'completed' => 'check',
        'failed' => 'x',
    ])
```

## Preset stavu jobu

```php
PollColumn::make('job_status')
    ->forJobStatus()           // předkonfigurováno pro stavy Laravel Jobu
    ->intervalSeconds(3)
    ->stopWhen(fn ($record) => in_array($record->job_status, ['completed', 'failed']))
```

## Preset progress baru

```php
PollColumn::make('progress')
    ->forProgress()            // UI progress baru (0-100)
    ->intervalSeconds(2)
    ->stopWhen(fn ($record) => $record->progress >= 100)
```

## Podmíněný polling

```php
PollColumn::make('sync_status')
    ->intervalSeconds(5)
    ->pollWhile(fn ($record) => $record->sync_state === 'syncing')   // pollovat jen během syncování
    ->pollForever(false)                                // zastavit, když podmínka selže
```

> Limit počtu pollů neexistuje. `maxPolls()` ho sliboval a nevynucoval nic:
> sloupec nevidí, kolikrát už polloval — počítadlo by muselo žít v hostiteli,
> který ho nikdy nevedl. Polling omez přes `stopWhen()` / `pollWhile()` /
> `stopOnComplete()`, což jsou podmínky, které sloupec vyhodnotit umí.

## Vlastní resolvování stavu

```php
PollColumn::make('deployment')
    ->resolveStateUsing(fn ($record) => $record->fresh()->deployment_status)
    ->intervalSeconds(10)
```

## Badge režim

```php
PollColumn::make('status')
    ->badge()
    ->colors([
        'online' => 'success',
        'offline' => 'danger',
        'degraded' => 'warning',
    ])
    ->intervalSeconds(30)
```

Mapa je klíčovaná **stavem**, stejně jako u `BadgeColumn` — a stejně jako
`->stateColors()` výše. Stav, který mapa neuvádí, spadne zpět na vlastní
`->color()` sloupce, a když ani ten není nastavený, na `gray`. Pokud je
pollovaný atribut enum cast implementující kontrakt `HasColor`, obarví si badge
sám i bez mapy.

## Indikátor načítání

```php
PollColumn::make('data')
    ->loadingIndicator('spinner')    // zobrazit během fetche
    ->keepContentWhileLoading()       // neproblikávat prázdno
    ->animateTransitions()            // plynulé změny stavu
```

## Callbacky

```php
PollColumn::make('batch_progress')
    ->intervalSeconds(3)
    ->stopWhen(fn ($record) => $record->batch_progress === 'done')
```

## Zastavení na koncovém stavu

`stopOnComplete()` je vypsaná obvyklá varianta: zastavit, jakmile stavový sloupec
dosáhne koncové hodnoty.

```php
PollColumn::make('status')
    ->stopOnComplete()                       // status v completed|failed|cancelled
    ->stopOnComplete('state', ['done'])      // jiný sloupec a stavy
```

> Callbacky dostanou **záznam**, ne stav buňky — `stopWhen(fn ($record) => …)`.
> Closure porovnávající svůj argument s řetězcem by se nikdy netrefila a sloupec
> by polloval donekonečna.

## API PollColumn

```php
// Řízení pollingu
->interval(int|Closure $milliseconds)    // raw milisekundy (např. 5000)
->intervalSeconds(int|Closure $seconds)  // sekundy (použijte pro intervaly ve stylu '5s')
->pollForever(bool $forever = true)      // nezastavovat
->stopWhen(Closure $fn)                  // fn($record, $column) => bool — zastaví, jakmile vrátí true
->stopOnComplete(string $statusColumn = 'status', array $completeStates = ['completed', 'failed', 'cancelled'])
->pollWhile(Closure $fn)                 // fn($record, $column) => bool — polluje, dokud vrací true
->pollWhilePending()                     // zkratka: pollovat během 'pending'

// Zobrazení stavu
->stateDisplays(array $map)              // ['state' => 'display text', ...]
->displayForState(string $state, Closure $display)
->defaultState(string|Closure $state)
->stateClasses(array $map)               // ['state' => 'css classes', ...]
->stateIcons(array $map)                 // ['state' => 'icon name', ...]
->stateColors(array $map)                // ['state' => 'color name', ...]
->resolveStateUsing(Closure $fn)         // vlastní resolver stavu

// Presety
->forJobStatus()                         // preset životního cyklu jobu
->forProgress()                          // preset progress baru

// UI volby
->badge(bool $badge = true)              // vykreslit jako badge
->colors(array $map)                     // ['state_value' => 'color_name'|Color, ...]
->colorUsing(Closure $fn)                // fn($state) => 'color_name'|Color|null
->size(string $size)                     // velikost badge
->loadingIndicator(?string $type)        // 'spinner', 'dots', 'pulse'
->withoutLoadingIndicator()
->keepContentWhileLoading(bool $keep = true)
->animateTransitions(bool $animate = true)

// Na úrovni řádku
->rowLevelPolling(bool $rowLevel = true) // pollovat per řádek (ne celou tabulku)

// Callbacky
->refreshMethod(string $method)          // Livewire metoda při obnovení
```
