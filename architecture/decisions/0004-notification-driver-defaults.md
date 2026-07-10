# ADR 0004: Notification Driver Defaults

## Status
Accepted

## Context
The notification system supports pluggable drivers (Session, LivewireEvent, Flasher). Which should be the default?

## Decision
**A `SessionDriver`-backed default.** It works out of the box with zero configuration by using Laravel's `session()->flash()` mechanism plus a live Livewire event.

Resolution order:
1. Per-table driver (set via `Table::notificationDriver()`)
2. Global default driver (set via `TableNotificationManager::setDefaultDriver()`)
3. Built-in default (fallback)

The Flasher driver is guarded by `class_exists()` – it only activates if the `php-flasher` package is installed.

## Consequences
- **Good:** Works immediately after installation without configuration.
- **Good:** Users can upgrade to LivewireEvent or Flasher with one line of configuration.
- **Trade-off:** the session flash itself requires a page load to display; the live event covers the in-place case.

## Update (2026-07)
The built-in fallback is now **`CurrentComponentDriver` wrapping `SessionDriver`**, not a bare `SessionDriver`. The decorator resolves the currently rendering component via `Livewire::current()`, so producers no longer thread `$this` through `NotificationManager::send()`. `SessionDriver` was also changed to dispatch the **full** `Notification::toArray()` payload (previously only `type`+`message`), so titles, durations, and toast actions survive without switching to `LivewireEventDriver`. The resolution order is unchanged; only the concrete fallback instance changed.
