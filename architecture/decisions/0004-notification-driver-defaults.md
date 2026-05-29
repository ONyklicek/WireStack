# ADR 0004: Notification Driver Defaults

## Status
Accepted

## Context
The notification system supports pluggable drivers (Session, LivewireEvent, Flasher). Which should be the default?

## Decision
**SessionDriver is the default.** It works out of the box with zero configuration by using Laravel's `session()->flash()` mechanism.

Resolution order:
1. Per-table driver (set via `Table::notificationDriver()`)
2. Global default driver (set via `TableNotificationManager::setDefaultDriver()`)
3. Built-in `SessionDriver` (fallback)

The Flasher driver is guarded by `class_exists()` – it only activates if the `php-flasher` package is installed.

## Consequences
- **Good:** Works immediately after installation without configuration.
- **Good:** Users can upgrade to LivewireEvent or Flasher with one line of configuration.
- **Trade-off:** Session-based notifications require a page refresh to display. LivewireEvent driver is better for SPA-like experiences but requires frontend setup.
