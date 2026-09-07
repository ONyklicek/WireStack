<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Notifications;

use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Notifications\Contracts\NotificationDriver;
use NyonCode\WireCore\Notifications\Drivers\CurrentComponentDriver;
use NyonCode\WireCore\Notifications\Drivers\SessionDriver;

/**
 * Central manager for notification drivers.
 *
 * Provides a global default driver and allows per-component overrides.
 * The resolution order is:
 *   1. Per-component driver (e.g. Table::notificationDriver())
 *   2. Global default driver (set via NotificationManager::setDefaultDriver())
 *   3. Built-in SessionDriver (backwards compatible)
 *
 * ─── Setup ─────────────────────────────────────────────────────
 *
 * Global (e.g. in AppServiceProvider::boot()):
 *
 *   use NyonCode\WireCore\Notifications\NotificationManager;
 *   use NyonCode\WireCore\Notifications\Drivers\FlasherDriver;
 *
 *   NotificationManager::setDefaultDriver(new FlasherDriver('toastr'));
 *
 * ─── Sending notifications ─────────────────────────────────────
 *
 * The default driver resolves the active Livewire component itself
 * (see CurrentComponentDriver), so no component has to be passed:
 *
 *   NotificationManager::send(Notification::success('Uloženo'));
 *
 * Or use the convenience methods:
 *
 *   NotificationManager::success('Uloženo');
 *   NotificationManager::error('Chyba');
 */
final class NotificationManager
{
    /**
     * Global default driver instance.
     */
    private static ?NotificationDriver $defaultDriver = null;

    public static function success(
        string $message,
        ?NotificationDriver $driver = null,
        mixed $livewireComponent = null
    ): void {
        self::send(Notification::success($message), $driver, $livewireComponent);
    }

    /**
     * Send a notification to a specific recipient.
     *
     * The queue-worker case, said in one line: there is nobody authenticated at
     * three in the morning, and the user this concerns was an argument the job
     * was given. Equivalent to `send($notification->to($user))` — sugar, because
     * the ordinary shape of the call is a loop over recipients and putting the
     * `to()` inside it reads as an afterthought.
     *
     * The transient drivers ignore the recipient, as they must: they deliver to
     * the page being rendered, and that page belongs to whoever is looking at
     * it. Pair `database` (and `broadcast`) with this.
     */
    public static function sendTo(
        Model $notifiable,
        Notification $notification,
        ?NotificationDriver $driver = null,
        mixed $livewireComponent = null,
    ): void {
        self::send($notification->to($notifiable), $driver, $livewireComponent);
    }

    /**
     * Send a notification through the resolved driver.
     */
    public static function send(
        Notification $notification,
        ?NotificationDriver $driver = null,
        mixed $livewireComponent = null,
    ): void {
        self::resolve($driver)->send($notification, $livewireComponent);
    }

    /**
     * Resolve which driver to use.
     *
     * Priority: explicit $driver > global default > SessionDriver
     */
    public static function resolve(?NotificationDriver $driver = null): NotificationDriver
    {
        return $driver ?? self::getDefaultDriver();
    }

    /**
     * Get the global default notification driver.
     *
     * Falls back to a CurrentComponentDriver wrapping the backwards-compatible
     * SessionDriver: the driver resolves the active Livewire component itself,
     * so call-sites don't have to pass it.
     */
    public static function getDefaultDriver(): NotificationDriver
    {
        return self::$defaultDriver ??= new CurrentComponentDriver(new SessionDriver);
    }

    // ─── Convenience shortcuts ─────────────────────────────────

    /**
     * Set the global default notification driver.
     */
    public static function setDefaultDriver(NotificationDriver $driver): void
    {
        self::$defaultDriver = $driver;
    }

    public static function error(
        string $message,
        ?NotificationDriver $driver = null,
        mixed $livewireComponent = null
    ): void {
        self::send(Notification::error($message), $driver, $livewireComponent);
    }

    public static function warning(
        string $message,
        ?NotificationDriver $driver = null,
        mixed $livewireComponent = null
    ): void {
        self::send(Notification::warning($message), $driver, $livewireComponent);
    }

    public static function info(
        string $message,
        ?NotificationDriver $driver = null,
        mixed $livewireComponent = null
    ): void {
        self::send(Notification::info($message), $driver, $livewireComponent);
    }

    /**
     * Reset to built-in default (useful for testing).
     */
    public static function reset(): void
    {
        self::$defaultDriver = null;
    }
}
