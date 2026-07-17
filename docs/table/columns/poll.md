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
    ->stopWhen(fn ($record) => in_array($record->job_status, ['completed', 'failed']))
```

## Progress Bar Preset

```php
PollColumn::make('progress')
    ->forProgress()            // progress bar UI (0-100)
    ->intervalSeconds(2)
    ->stopWhen(fn ($record) => $record->progress >= 100)
```

## Conditional Polling

```php
PollColumn::make('sync_status')
    ->intervalSeconds(5)
    ->pollWhile(fn ($record) => $record->sync_state === 'syncing')   // poll only while syncing
    ->pollForever(false)                                // stop when condition fails
```

> There is no poll-count cap. `maxPolls()` used to advertise one and enforce
> nothing: a column cannot see how many times it has polled — the count would
> have to live in the host, which never kept one. Bound the polling with
> `stopWhen()` / `pollWhile()` / `stopOnComplete()`, which are conditions the
> column *can* evaluate.

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
        'online' => 'success',
        'offline' => 'danger',
        'degraded' => 'warning',
    ])
    ->intervalSeconds(30)
```

The map is keyed by the **state**, as on `BadgeColumn` — the same shape
`->stateColors()` above uses. A state the map does not mention falls back to the
column's own `->color()`, and to `gray` when that is unset. If the polled
attribute is an enum cast implementing the `HasColor` contract, it colours the
badge on its own with no map at all.

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
    ->stopWhen(fn ($record) => $record->batch_progress === 'done')
```

## Stopping on a Final State

`stopOnComplete()` is the common case spelled out: stop once a status column
reaches a terminal value.

```php
PollColumn::make('status')
    ->stopOnComplete()                       // status in completed|failed|cancelled
    ->stopOnComplete('state', ['done'])      // a different column and states
```

> The callbacks receive the **record**, not the cell's state — `stopWhen(fn ($record) => …)`.
> A closure comparing its argument to a string would never match, and the column
> would poll forever.

## PollColumn API

```php
// Polling control
->interval(int|Closure $milliseconds)    // raw milliseconds (e.g. 5000)
->intervalSeconds(int|Closure $seconds)  // seconds (use this for '5s'-style intervals)
->pollForever(bool $forever = true)      // don't stop
->stopWhen(Closure $fn)                  // fn($record, $column) => bool — stop once true
->stopOnComplete(string $statusColumn = 'status', array $completeStates = ['completed', 'failed', 'cancelled'])
->pollWhile(Closure $fn)                 // fn($record, $column) => bool — keep polling while true
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
->colors(array $map)                     // ['state_value' => 'color_name'|Color, ...]
->colorUsing(Closure $fn)                // fn($state) => 'color_name'|Color|null
->size(string $size)                     // badge size
->loadingIndicator(?string $type)        // 'spinner', 'dots', 'pulse'
->withoutLoadingIndicator()
->keepContentWhileLoading(bool $keep = true)
->animateTransitions(bool $animate = true)

// Row-level
->rowLevelPolling(bool $rowLevel = true) // poll per row (not whole table)

// Callbacks
->refreshMethod(string $method)          // Livewire method on refresh
```
