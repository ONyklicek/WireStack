<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Notifications;

use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\On;
use Livewire\Component;
use NyonCode\WireCore\Foundation\Routing\Contracts\ResolvesPageUrls;
use NyonCode\WireCore\Foundation\Routing\Zone;
use NyonCode\WireCore\Notifications\Contracts\ResolvesNotifiable;
use NyonCode\WireCore\Notifications\Support\NotificationChannel;
use NyonCode\WireCore\Notifications\Support\NotificationStyle;
use NyonCode\WirePanels\Resources\Pages\ViewPage;

/**
 * The bell: an unread count, and a panel behind it.
 *
 * A Livewire component rather than a Blade one, because everything it does is a
 * round trip — switching tabs, marking one read, marking all read, and re-reading
 * after a job finished somewhere else. It composes no host trait for the same
 * reason {@see ViewPage} composes none: there is no form state to bind and no
 * table to drive.
 *
 * It reads {@see NotificationCenter} and holds no query of its own, so the
 * recipient scoping — which is what stops one user seeing another's rows — has
 * exactly one owner.
 *
 * Mount it wherever the layout wants:
 *
 *   `@livewire`('wire-notification-bell')
 *
 * ─── How it stays current ──────────────────────────────────────
 *
 * With the `broadcast` driver configured it subscribes to the recipient's own
 * channel and re-reads when something lands — including on the tab nobody is
 * clicking in, which is the case a queued job finishing twenty minutes later
 * actually has. Without it the bell is right on every render and no sooner:
 * `wire:poll` is then the caller's choice, or an application that knows a
 * notification landed dispatches `wire-notification-received`.
 *
 * ─── Where the list goes ───────────────────────────────────────
 *
 * The panel shows the newest few; an inbox with filters and a URL per
 * notification is what `wire-module-notifications` adds. The bell asks
 * {@see ResolvesPageUrls} whether the key `notifications` is routed and links to
 * it when it is — so installing the module makes the links appear and there is
 * nothing to configure, while an application without it renders a panel with no
 * links rather than a broken one.
 */
class NotificationBell extends Component
{
    /** How many to show in the panel. The count is always the true total. */
    public int $limit = 10;

    /** Whether the panel is open. Entangled with the slide-over's Alpine state. */
    public bool $panelOpen = false;

    /** Which of the two tabs is showing: `all` or `unread`. */
    public string $tab = 'all';

    /**
     * The zone the bell was rendered in, so its links point back into it.
     *
     * A public property because it has to survive the round trip: every verb
     * here runs on a Livewire request, where {@see Zone::current()} answers
     * `livewire.update` and therefore nothing — so it is read once while the page
     * renders and carried from there (ADR 0027 §3), exactly as the global search
     * palette and the admin sidebar carry it. (Named rather than linked: they
     * are another L2 module and this one may not see it — ADR 0025.)
     *
     * Without it an application that mounts its pages inside zones has a bell
     * whose "view all" resolves against the unzoned mount point: `null`, so the
     * link simply is not drawn. Nothing errors, nothing is logged, and the panel
     * looks finished.
     *
     * Public also means an application can set it — a bell in a shell that is not
     * itself a wire route says which zone it belongs to:
     *
     *   `@livewire`('wire-notification-bell', ['zone' => 'admin'])
     */
    public ?string $zone = null;

    public function mount(?int $limit = null, ?string $zone = null): void
    {
        $this->limit = $limit ?? (int) config('wire-core.notifications.bell.limit', 10);
        $this->zone = $zone ?? Zone::current();
    }

    #[On('wire-notification-received')]
    public function refresh(): void
    {
        // Nothing to do: re-rendering is the refresh. The listener exists so an
        // application that knows a notification landed can say so, and it is
        // what the broadcast bridge falls back on.
    }

    /**
     * Show all notifications, or only the unread ones.
     *
     * Anything else is `all` rather than an error: the argument arrives from a
     * Livewire call, which is user input, and the honest answer to a tab that
     * does not exist is the default tab.
     */
    public function setTab(string $tab): void
    {
        $this->tab = $tab === 'unread' ? 'unread' : 'all';
    }

    public function markAsRead(string $id): void
    {
        $this->center()->markAsRead($id);
    }

    public function markAsUnread(string $id): void
    {
        $this->center()->markAsUnread($id);
    }

    public function markAllAsRead(): void
    {
        $this->center()->markAllAsRead();
    }

    public function delete(string $id): void
    {
        $this->center()->delete($id);
    }

    /** Clear what has already been read — see {@see NotificationCenter::deleteRead()}. */
    public function clearRead(): void
    {
        $this->center()->deleteRead();
    }

    /**
     * Follow a notification to whatever it is about, marking it read on the way.
     *
     * A server round trip rather than a plain link, because the two things a
     * click means here happen in different places: *read* is a write, and *go*
     * is the browser's. Doing the write first and answering with the redirect
     * makes them one action that cannot half-happen — where a `wire:click`
     * racing a `wire:navigate` is a write the navigation is entitled to abort.
     *
     * The row keeps a real `href` all the same, so the link can be copied,
     * middle-clicked and read by anything that reads links; only the plain left
     * click comes through here.
     */
    public function open(string $id): void
    {
        $notification = $this->center()->find($id);

        if ($notification === null) {
            return;
        }

        $notification->markAsRead();

        $url = $this->destination($notification);

        if ($url !== null) {
            $this->redirect($url, navigate: true);
        }
    }

    public function render(): View
    {
        $center = $this->center();

        $notifications = $this->tab === 'unread'
            ? $center->unread($this->limit)
            : $center->latest($this->limit);

        $items = $this->items($notifications);
        $unreadCount = $center->unreadCount();

        return view('wire-core::notifications.bell', [
            'unreadCount' => $unreadCount,
            // Three states, not two. A bell with no badge cannot say whether
            // nothing has ever happened or whether the user has simply read
            // everything — and those deserve different marks: nothing, and a
            // quiet one. Only asked when the count cannot already answer it, so
            // the ordinary render still costs one query.
            'hasAny' => $unreadCount > 0 || $center->hasAny(),
            'items' => $items,
            // Grouped here, not sequenced in the view: emitting a heading when
            // the previous row's day differs is a comparison the template would
            // have to carry, and one the template has no business making.
            'groups' => $this->groups($items),
            // Whether the footer has anything to clear. Read off what is on
            // screen rather than counted with a second query: the button clears
            // what has been read, and offering it when the panel shows nothing
            // read is offering a button whose effect is invisible.
            'hasRead' => array_filter($items, static fn (array $i): bool => $i['read']) !== [],
            'channel' => $this->channel(),
            'indexUrl' => $this->urls()->urlFor('notifications', 'index', [], $this->zone),
        ]);
    }

    protected function center(): NotificationCenter
    {
        return app(NotificationCenter::class);
    }

    /**
     * The rows, resolved into what the panel draws.
     *
     * Resolved here rather than in the view because the view may not branch on
     * domain state (Rendering Rule 1): the payload shape, the type's icon and
     * colour, and whether this notification has a page of its own are all
     * decisions, and they belong on this side of the boundary.
     *
     * @param  Collection<int, DatabaseNotification>  $notifications
     * @return list<array{id: string, group: string, title: string|null, message: string, icon: string, tint: string, when: string, read: bool, url: string|null, actions: list<array<string, mixed>>}>
     */
    protected function items(Collection $notifications): array
    {
        return $notifications->map(function (DatabaseNotification $notification): array {
            // The value object it was raised as, so the panel reads the payload
            // through the same owner a toast does.
            $payload = $notification->toNotification();
            $style = NotificationStyle::for($payload->type);

            return [
                'id' => (string) $notification->id,
                'group' => $this->groupOf($notification),
                'title' => $payload->title,
                // Not both when they are the same sentence. A notification
                // raised with only a message has no title; one raised with both
                // usually has a short title over a longer line — but the two
                // arrive from an application, and an application that passes the
                // same string twice should not get it rendered twice, in two
                // weights, as though it meant something.
                'message' => $payload->title === $payload->message ? '' : $payload->message,
                'icon' => $style->iconOr($payload->icon),
                'tint' => $style->tint,
                'when' => (string) $notification->created_at?->diffForHumans(),
                'read' => $notification->isRead(),
                'url' => $this->destination($notification),
                'actions' => array_map(
                    // Presentation resolved here, not in the view: which of the
                    // six colour names an action carries is a decision, and the
                    // vocabulary has one owner.
                    static fn (NotificationAction $action): array => $action->toArray()
                        + ['classes' => $style->actionClasses($action->color)],
                    $payload->actions,
                ),
            ];
        })->all();
    }

    /**
     * The rows under their day headings, in the order they came.
     *
     * An ordered map rather than a list with a marker on each row, because a
     * heading belongs to the run beneath it — and because the list arrives
     * newest-first, so each label is reached once and never again.
     *
     * @param  list<array<string, mixed>>  $items
     * @return array<string, list<array<string, mixed>>>
     */
    protected function groups(array $items): array
    {
        $groups = [];

        foreach ($items as $item) {
            $groups[(string) $item['group']][] = $item;
        }

        return $groups;
    }

    /**
     * Which day-heading a row belongs under.
     *
     * Three buckets rather than a date per row, because that is how a person
     * reads an inbox: "today" and "yesterday" are the two that carry meaning,
     * and everything before them is one pile the exact date of which the
     * relative timestamp on the row already gives.
     *
     * Resolved here rather than in the view for the ordinary reason — a view may
     * not branch on domain state — and the label is a closure over the
     * translator, evaluated at render.
     */
    protected function groupOf(DatabaseNotification $notification): string
    {
        $at = $notification->created_at;

        return match (true) {
            $at === null => __('wire-core::messages.notifications_earlier'),
            $at->isToday() => __('wire-core::messages.notifications_today'),
            $at->isYesterday() => __('wire-core::messages.notifications_yesterday'),
            default => __('wire-core::messages.notifications_earlier'),
        };
    }

    /**
     * Where a row goes when it is clicked.
     *
     * The thing it is about first — an invoice, an export — because that is what
     * the reader wants and `Notification::url()` is how the notification says
     * so. Its own page is the fallback, and exists at all only where a page
     * package routes the `notifications` key; a notification with neither is a
     * row that reads rather than a link that goes nowhere.
     */
    protected function destination(DatabaseNotification $notification): ?string
    {
        $url = $notification->toNotification()->url;

        if (is_string($url) && $url !== '') {
            return $url;
        }

        return $this->urls()->urlFor('notifications', 'view', ['record' => $notification->id], $this->zone);
    }

    /**
     * The channel to listen on, or null when nothing is broadcasting.
     *
     * Null for the two ordinary reasons — the `broadcast` driver is not
     * configured, or nobody is signed in — and the view renders a bell with no
     * bridge for either, which is exactly what it did before there was one.
     */
    protected function channel(): ?string
    {
        if (! in_array('broadcast', (array) config('wire-core.notifications.default', 'session'), true)) {
            return null;
        }

        $recipient = app(ResolvesNotifiable::class)->resolve();

        return $recipient instanceof Model ? NotificationChannel::for($recipient) : null;
    }

    protected function urls(): ResolvesPageUrls
    {
        return app(ResolvesPageUrls::class);
    }
}
