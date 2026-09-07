<?php

declare(strict_types=1);

namespace NyonCode\WireModuleNotifications\Pages;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use NyonCode\WireCore\Core\Plugin\Contracts\IdentifiesHookTarget;
use NyonCode\WireCore\Core\Resources\Contracts\ProvidesBreadcrumbs;
use NyonCode\WireCore\Foundation\Routing\Contracts\ResolvesPageUrls;
use NyonCode\WireCore\Foundation\Routing\Zone;
use NyonCode\WireCore\Notifications\DatabaseNotification;
use NyonCode\WireCore\Notifications\Support\NotificationStyle;
use NyonCode\WireModuleNotifications\Resources\NotificationResource;
use NyonCode\WirePanels\Resources\Concerns\BelongsToResource;
use NyonCode\WirePanels\Resources\Pages\ListPage;

/**
 * The inbox — and deliberately **not** a table.
 *
 * Every other list in this repository is a {@see ListPage}, which composes
 * `WithTable` and gets search, filters, sorting, paging, selection and exports
 * for nothing. That is the right trade wherever a reader **compares** records:
 * amounts, dates and states read across a grid of columns.
 *
 * A reader of an inbox does not compare. They read, one line at a time, newest
 * first, and stop when they reach something they have already seen. Built on the
 * table, this screen spent three rewrites having the table talked out of it —
 * one content column instead of three, no state column, weight instead of a
 * badge, no checkbox, no page-size control, no sort control — and still wore a
 * toolbar with a *Filters* button and a footer counting records. When a design
 * keeps removing what a component brought, the component was the wrong one.
 *
 * So this composes {@see BelongsToResource} and nothing else: the resource still
 * owns the key, the label, the breadcrumbs, the URLs and the permissions, and
 * the records are read through
 * {@see NotificationResource::scopeToViewer()} — the one owner of *whose*
 * notifications these are. What is gone is only the grid.
 *
 * What that costs, stated plainly: search, filtering and paging are written
 * here rather than inherited, and there is no selection and no export. Three
 * short methods below, against a screen that finally reads like an inbox.
 */
class ListNotifications extends Component implements IdentifiesHookTarget, ProvidesBreadcrumbs
{
    use BelongsToResource;
    use WithPagination;

    protected static ?string $resource = NotificationResource::class;

    /** Which of the three the reader is looking at: `all`, `unread` or `read`. */
    #[Url(as: 'stav', except: 'all')]
    public string $tab = 'all';

    #[Url(as: 'q', except: '')]
    public string $search = '';

    /** How many rows a page holds. Not a control — a reader of an inbox scrolls. */
    public int $perPage = 25;

    /**
     * Show all, only unread, or only read.
     *
     * Anything else is `all` rather than an error: the value arrives from a URL,
     * which is user input, and the honest answer to a tab that does not exist is
     * the default tab.
     */
    public function setTab(string $tab): void
    {
        $this->tab = in_array($tab, ['unread', 'read'], true) ? $tab : 'all';
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    // ─── Verbs ──────────────────────────────────────────────────
    //
    // Every one goes through find(), which scopes to the viewer: an id arriving
    // from a Livewire call is user input, and an unscoped lookup would let one
    // person mark, read or delete another's notification.

    public function markAsRead(string $id): void
    {
        $this->find($id)?->markAsRead();
    }

    public function markAsUnread(string $id): void
    {
        $this->find($id)?->markAsUnread();
    }

    public function delete(string $id): void
    {
        $this->find($id)?->delete();
    }

    public function markAllAsRead(): void
    {
        $this->scoped()->whereNull('read_at')->update(['read_at' => now()]);
    }

    /**
     * Follow a notification to whatever it is about, marking it read on the way.
     *
     * A server round trip rather than a plain link, because the two things a
     * click means happen in different places: *read* is a write and *go* is the
     * browser's. Doing the write first and answering with the redirect makes
     * them one action that cannot half-happen.
     */
    public function open(string $id): void
    {
        $notification = $this->find($id);

        if ($notification === null) {
            return;
        }

        $notification->markAsRead();

        $url = $this->destination($notification);

        if ($url !== null) {
            $this->redirect($url, navigate: true);
        }
    }

    /** A list is titled by the plural: "Notifications", not "Notification". */
    public function getTitle(): ?string
    {
        return $this->title ?? NotificationResource::pluralLabel();
    }

    public function render(): View
    {
        $page = $this->records();

        return view('wire-module-notifications::livewire.list-notifications', [
            'title' => $this->getTitle(),
            'breadcrumbs' => $this->breadcrumbs(),
            'groups' => $this->groups($page),
            'page' => $page,
            'unreadCount' => (int) $this->scoped()->whereNull('read_at')->count(),
            'hasAny' => $this->scoped()->exists(),
        ]);
    }

    // ─── Reading ────────────────────────────────────────────────

    /**
     * This viewer's notifications, narrowed by the tab and the search box.
     *
     * The search reads the JSON payload rather than a column, because that is
     * where the visible text lives — a search over `title` would search a column
     * that does not exist, which is what the table version did until it was
     * caught.
     *
     * @return LengthAwarePaginator<int, DatabaseNotification>
     */
    protected function records(): LengthAwarePaginator
    {
        $query = $this->scoped();

        match ($this->tab) {
            'unread' => $query->whereNull('read_at'),
            'read' => $query->whereNotNull('read_at'),
            default => null,
        };

        $term = trim($this->search);

        if ($term !== '') {
            $query->where(function (Builder $q) use ($term): void {
                $q->where('data->title', 'like', "%{$term}%")
                    ->orWhere('data->message', 'like', "%{$term}%");
            });
        }

        return $query
            ->orderByDesc('created_at')
            // The ULID breaks the same-second ties a bulk job produces.
            ->orderByDesc('id')
            ->paginate($this->perPage);
    }

    /**
     * The viewer's own rows, and nobody else's.
     *
     * Through the resource, which is the one owner of that question — the bell,
     * the detail page and this all ask it there.
     *
     * @return Builder<DatabaseNotification>
     */
    protected function scoped(): Builder
    {
        /** @var Builder<DatabaseNotification> $query */
        $query = NotificationResource::scopeToViewer(DatabaseNotification::query())->reorder();

        return $query;
    }

    protected function find(string $id): ?DatabaseNotification
    {
        /** @var DatabaseNotification|null */
        return $this->scoped()->find($id);
    }

    /**
     * The rows the view draws, under their day headings.
     *
     * Everything the template needs is decided here — the payload read through
     * its owner, the type's icon and tint, where the row goes — because a view
     * may not branch on domain state.
     *
     * @param  LengthAwarePaginator<int, DatabaseNotification>  $page
     * @return array<string, list<array<string, mixed>>>
     */
    protected function groups(LengthAwarePaginator $page): array
    {
        $groups = [];

        foreach ($page->items() as $record) {
            $payload = $record->toNotification();
            $style = NotificationStyle::for($payload->type);

            // Not both when they are the same sentence: an application that
            // passes one string twice should not get it printed twice. Decided
            // here rather than inside the array literal, where a constant arm
            // compiles to no opcode of its own and so can never be shown covered.
            $body = $payload->title === null || $payload->title === $payload->message
                ? null
                : $payload->message;

            $groups[$this->heading($record)][] = [
                'id' => (string) $record->id,
                'title' => $payload->title ?? $payload->message,
                'message' => $body,
                'icon' => $style->iconOr($payload->icon),
                'tile' => $this->tile($style->color),
                'when' => (string) $record->created_at?->diffForHumans(),
                'read' => $record->isRead(),
                'url' => $this->destination($record),
                'actions' => array_map(
                    fn (object $action): array => $action->toArray() + ['classes' => $style->actionClasses($action->color)],
                    $payload->actions,
                ),
            ];
        }

        return $groups;
    }

    /**
     * The tinted tile a row is anchored by.
     *
     * Literal class strings so Tailwind's scanner sees them, and the match arms
     * double as the allow-list for a role read out of a stored payload.
     */
    protected function tile(string $role): string
    {
        return match ($role) {
            'success' => 'bg-emerald-100 text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-400',
            'danger' => 'bg-red-100 text-red-600 dark:bg-red-500/15 dark:text-red-400',
            'warning' => 'bg-amber-100 text-amber-600 dark:bg-amber-500/15 dark:text-amber-400',
            'info' => 'bg-cyan-100 text-cyan-600 dark:bg-cyan-500/15 dark:text-cyan-400',
            default => 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400',
        };
    }

    /** Today, yesterday, or everything before them. */
    protected function heading(DatabaseNotification $record): string
    {
        $at = $record->created_at;

        return match (true) {
            $at === null => __('wire-core::messages.notifications_earlier'),
            $at->isToday() => __('wire-core::messages.notifications_today'),
            $at->isYesterday() => __('wire-core::messages.notifications_yesterday'),
            default => __('wire-core::messages.notifications_earlier'),
        };
    }

    /**
     * Where a row goes: the thing it is about, else its own page.
     *
     * A list whose rows sometimes go to an invoice and sometimes to a
     * notification is a list you cannot click confidently — so the payload's own
     * `url()` wins and the detail page is the fallback.
     */
    protected function destination(DatabaseNotification $record): ?string
    {
        $url = $record->toNotification()->url;

        if (is_string($url) && $url !== '') {
            return $url;
        }

        return app(ResolvesPageUrls::class)->urlFor(
            NotificationResource::key(),
            'view',
            ['record' => $record->getKey()],
            Zone::current(),
        );
    }
}
