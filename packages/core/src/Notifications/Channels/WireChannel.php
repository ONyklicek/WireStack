<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Notifications\Channels;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notification as LaravelNotification;
use NyonCode\WireCore\Notifications\Contracts\NotificationDriver;
use NyonCode\WireCore\Notifications\Notification;

/**
 * Laravel's notification system, delivering into this one.
 *
 *   class InvoicePaid extends Notification implements ShouldQueue
 *   {
 *       public function via($notifiable): array
 *       {
 *           return ['mail', 'wire'];
 *       }
 *
 *       public function toWire($notifiable): WireNotification
 *       {
 *           return WireNotification::success('Invoice paid')
 *               ->title($this->invoice->number)
 *               ->url(route('invoices.show', $this->invoice));
 *       }
 *   }
 *
 *   $user->notify(new InvoicePaid($invoice));
 *
 * Small surface, large gain: `ShouldQueue`, the `Notifiable` trait, `via()`
 * deciding per user, mail going out beside the bell entry, `notifyNow()`,
 * `Notification::fake()` in tests — all of it is Laravel's and all of it now
 * reaches the bell. Writing any of that again here would have been the second
 * wheel this codebase keeps deleting.
 *
 * **The recipient comes from Laravel, not from the session**, which is what makes
 * this worth having in a queued job: `$notifiable` is the model `notify()` was
 * called on, and it is handed to the driver through
 * {@see Notification::to()} — so the row is written for the right person with
 * nobody logged in.
 *
 * The driver is the configured one, so a `wire` notification honours whatever
 * `wire-core.notifications.default` says. In practice that means pairing it with
 * `database`: a Laravel notification delivered only to a transient driver is
 * delivered to whatever page happened to be rendering, which for a queued job is
 * no page at all.
 */
final class WireChannel
{
    /** The name an application writes in `via()`. */
    public const NAME = 'wire';

    public function __construct(private readonly NotificationDriver $driver) {}

    public function send(mixed $notifiable, LaravelNotification $notification): void
    {
        if (! method_exists($notification, 'toWire')) {
            // Nothing to deliver rather than a fatal: `via()` listing a channel
            // the notification cannot render is the application's mistake, and
            // taking down a queued job that also had mail to send is not the way
            // to tell them about it.
            return;
        }

        $wire = $notification->toWire($notifiable);

        if (! $wire instanceof Notification) {
            return;
        }

        // Only when it did not say. A notification is entitled to address
        // somebody other than the model `notify()` was called on — an escalation
        // to a manager, a copy to an inbox — and this must not overwrite that.
        //
        // And only for a Model: `Notification::route('mail', …)` notifies an
        // anonymous notifiable, which is a real thing to do and has no row to be
        // stored against. The driver then falls back to the resolver, and with
        // nobody there it writes nothing — the same fail-quiet answer it gives a
        // queue worker.
        if ($wire->notifiable === null && $notifiable instanceof Model) {
            $wire = $wire->to($notifiable);
        }

        $this->driver->send($wire);
    }
}
