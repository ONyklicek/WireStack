---
order: 23
nav: false
---

# PollColumn

Advanced auto-refreshing column with state machines, progress tracking, and condition-based polling. Ideal for background jobs, live status, progress bars.

```php
use NyonCode\WireTable\Columns\PollColumn;
```

## Basic Polling

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

## Job Status Preset

```php
PollColumn::make('job_status')
    ->forJobStatus()           // preconfigured for Laravel Job states
    ->intervalSeconds(3)
    ->stopWhen(fn ($state) => in_array($state, ['completed', 'failed']))
```

## Progress Bar Preset

```php
PollColumn::make('progress')
    ->forProgress()            // progress bar UI (0-100)
    ->intervalSeconds(2)
    ->stopWhen(fn ($state) => $state >= 100)
```

## Conditional Polling

```php
PollColumn::make('sync_status')
    ->intervalSeconds(5)
    ->pollWhile(fn ($state) => $state === 'syncing')   // poll only while syncing
    ->pollForever(false)                                // stop when condition fails
    ->maxPolls(60)                                      // safety limit
```

## Custom State Resolution

```php
PollColumn::make('deployment')
    ->resolveStateUsing(fn ($record) => $record->fresh()->deployment_status)
    ->intervalSeconds(10)
```

## Badge Mode

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

## Loading Indicator

```php
PollColumn::make('data')
    ->loadingIndicator('spinner')    // show during fetch
    ->keepContentWhileLoading()       // don't flash blank
    ->animateTransitions()            // smooth state changes
```

## Callbacks

```php
PollColumn::make('batch_progress')
    ->intervalSeconds(3)
    ->onComplete(fn ($record) => Notification::success("Batch {$record->id} done"))
    ->stopWhen(fn ($state) => $state === 'done')
```

## PollColumn API

```php
// Polling control
->interval(int|Closure $milliseconds)    // raw milliseconds (e.g. 5000)
->intervalSeconds(int|Closure $seconds)  // seconds (use this for '5s'-style intervals)
->pollForever(bool $forever = true)      // don't stop
->maxPolls(int $max)                     // safety limit
->stopWhen(Closure $fn)                  // fn($state) => bool
->pollWhile(Closure $fn)                 // fn($state) => bool
->pollWhilePending()                     // shortcut: poll while 'pending'

// State display
->stateDisplays(array $map)              // ['state' => 'display text', ...]
->displayForState(string $state, Closure $display)
->defaultState(string|Closure $state)
->stateClasses(array $map)               // ['state' => 'css classes', ...]
->stateIcons(array $map)                 // ['state' => 'icon name', ...]
->stateColors(array $map)                // ['state' => 'color name', ...]
->resolveStateUsing(Closure $fn)         // custom state resolver

// Presets
->forJobStatus()                         // job lifecycle preset
->forProgress()                          // progress bar preset

// UI options
->badge(bool $badge = true)              // render as badge
->colors(array $map)                     // badge color map
->colorUsing(Closure $fn)                // dynamic color
->size(string $size)                     // badge size
->loadingIndicator(?string $type)        // 'spinner', 'dots', 'pulse'
->withoutLoadingIndicator()
->keepContentWhileLoading(bool $keep = true)
->animateTransitions(bool $animate = true)

// Row-level
->rowLevelPolling(bool $rowLevel = true) // poll per row (not whole table)

// Callbacks
->onComplete(Closure $fn)
->refreshMethod(string $method)          // Livewire method on refresh
```
