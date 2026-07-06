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
    ->stopWhen(fn ($state) => in_array($state, ['completed', 'failed']))
```

## Preset progress baru

```php
PollColumn::make('progress')
    ->forProgress()            // UI progress baru (0-100)
    ->intervalSeconds(2)
    ->stopWhen(fn ($state) => $state >= 100)
```

## Podmíněný polling

```php
PollColumn::make('sync_status')
    ->intervalSeconds(5)
    ->pollWhile(fn ($state) => $state === 'syncing')   // pollovat jen během syncování
    ->pollForever(false)                                // zastavit, když podmínka selže
    ->maxPolls(60)                                      // bezpečnostní limit
```

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
        'success' => 'online',
        'danger' => 'offline',
        'warning' => 'degraded',
    ])
    ->intervalSeconds(30)
```

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
    ->onComplete(fn ($record) => Notification::success("Batch {$record->id} done"))
    ->stopWhen(fn ($state) => $state === 'done')
```

## API PollColumn

```php
// Řízení pollingu
->interval(int|Closure $milliseconds)    // raw milisekundy (např. 5000)
->intervalSeconds(int|Closure $seconds)  // sekundy (použijte pro intervaly ve stylu '5s')
->pollForever(bool $forever = true)      // nezastavovat
->maxPolls(int $max)                     // bezpečnostní limit
->stopWhen(Closure $fn)                  // fn($state) => bool
->pollWhile(Closure $fn)                 // fn($state) => bool
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
->colors(array $map)                     // mapa barev badge
->colorUsing(Closure $fn)                // dynamická barva
->size(string $size)                     // velikost badge
->loadingIndicator(?string $type)        // 'spinner', 'dots', 'pulse'
->withoutLoadingIndicator()
->keepContentWhileLoading(bool $keep = true)
->animateTransitions(bool $animate = true)

// Na úrovni řádku
->rowLevelPolling(bool $rowLevel = true) // pollovat per řádek (ne celou tabulku)

// Callbacky
->onComplete(Closure $fn)
->refreshMethod(string $method)          // Livewire metoda při obnovení
```
