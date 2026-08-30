<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Notifications;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;
use NyonCode\WirePanels\Resources\Pages\ViewPage;

/**
 * The bell: an unread count, a short list, and two ways to clear it.
 *
 * A Livewire component rather than a Blade one, because everything it does is a
 * round trip — marking one read, marking all read, and re-reading after a job
 * finished somewhere else. It composes no host trait for the same reason
 * {@see ViewPage} composes none: there is
 * no form state to bind and no table to drive.
 *
 * It reads {@see NotificationCenter} and holds no query of its own, so the
 * recipient scoping — which is what stops one user seeing another's rows — has
 * exactly one owner.
 *
 * Mount it wherever the layout wants:
 *
 *   `@livewire`('wire-notification-bell')
 *
 * A queued job finishing has no component to dispatch to, so the bell refreshes
 * on its own poll rather than waiting to be told. `wire:poll` is the caller's
 * choice — pass an interval, or leave it off and refresh on the
 * `wire-notifications-read` event the actions already emit.
 */
class NotificationBell extends Component
{
    /** How many to show in the dropdown. The count is always the true total. */
    public int $limit = 10;

    public function mount(int $limit = 10): void
    {
        $this->limit = $limit;
    }

    #[On('wire-notification-received')]
    public function refresh(): void
    {
        // Nothing to do: re-rendering is the refresh. The listener exists so an
        // application that knows a notification landed can say so.
    }

    public function markAsRead(string $id): void
    {
        $this->center()->markAsRead($id);
    }

    public function markAllAsRead(): void
    {
        $this->center()->markAllAsRead();
    }

    public function render(): View
    {
        $center = $this->center();

        return view('wire-core::notifications.bell', [
            'unreadCount' => $center->unreadCount(),
            'notifications' => $center->latest($this->limit),
        ]);
    }

    protected function center(): NotificationCenter
    {
        return app(NotificationCenter::class);
    }
}
