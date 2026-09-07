<?php

declare(strict_types=1);

namespace NyonCode\WireModuleNotifications\Resources;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use NyonCode\WireCore\Core\Resources\Concerns\DescribesRecords;
use NyonCode\WireCore\Core\Resources\Contracts\DescribesResource;
use NyonCode\WireCore\Core\Resources\Contracts\ProvidesNavigation;
use NyonCode\WireCore\Core\Resources\Navigation\NavigationItem;
use NyonCode\WireCore\Foundation\Routing\Contracts\ProvidesPages;
use NyonCode\WireCore\Infolists\Components\KeyValueEntry;
use NyonCode\WireCore\Infolists\Components\TextEntry;
use NyonCode\WireCore\Infolists\Contracts\ProvidesResourceInfolist;
use NyonCode\WireCore\Infolists\Infolist;
use NyonCode\WireModuleNotifications\Pages\ListNotifications;
use NyonCode\WireModuleNotifications\Pages\ViewNotification;

/**
 * The history behind the bell.
 *
 * The bell shows the latest few and is a dropdown; this is the same rows with a
 * table over them — searchable, filterable by read state, and readable after
 * they have scrolled out of the dropdown.
 *
 * **Scoped to the signed-in user by default.** `scope => 'all'` turns it into an
 * administrative view of everyone's notifications, which is a different screen
 * with a different policy — so it is a decision an application makes rather than
 * the module's default.
 */
class NotificationResource implements DescribesResource, ProvidesNavigation, ProvidesPages, ProvidesResourceInfolist
{
    use DescribesRecords;

    public static function key(): string
    {
        return 'notifications';
    }

    public static function modelClass(): ?string
    {
        $model = config('wire-module-notifications.model');

        return is_string($model) && $model !== '' ? $model : null;
    }

    public static function label(): string
    {
        return __('wire-module-notifications::messages.notification');
    }

    public static function pluralLabel(): string
    {
        return __('wire-module-notifications::messages.notifications');
    }

    public static function pages(): array
    {
        return [
            'index' => ListNotifications::class,
            'view' => ViewNotification::class,
        ];
    }

    /**
     * The sidebar row — hidden by default, and the page routed regardless.
     *
     * An inbox is reached from its badge, not from a permanent menu row: the
     * bell carries the unread count and its panel links here, so a sidebar entry
     * would be a second, quieter way in that never says anything. `visible()`
     * rather than dropping `ProvidesNavigation` because the entry is a real
     * thing an application may want — hiding it is a decision, not an absence —
     * and because dropping the contract would take the URL with it.
     */
    public static function navigation(): NavigationItem
    {
        return NavigationItem::make(fn (): string => __('wire-module-notifications::messages.notifications'))
            ->group((string) config('wire-module-notifications.navigation.group', 'system'))
            ->icon((string) config('wire-module-notifications.navigation.icon', 'outline:bell'))
            ->visible((bool) config('wire-module-notifications.navigation.visible', false));
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            TextEntry::make('type')->label(__('wire-module-notifications::messages.type')),
            TextEntry::make('created_at')->label(__('wire-module-notifications::messages.when')),
            TextEntry::make('read_at')->label(__('wire-module-notifications::messages.read_at')),
            KeyValueEntry::make('data')->label(__('wire-module-notifications::messages.payload')),
        ]);
    }

    /**
     * Whose notifications this screen shows.
     *
     * The default is the viewer's own, because a notification is addressed to
     * somebody: a list of everyone's is an administrative view, and an
     * application that wants one says so — and puts a policy on the page.
     *
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    public static function scopeToViewer(Builder $query): Builder
    {
        if (config('wire-module-notifications.scope', 'own') === 'all') {
            return $query;
        }

        $user = Auth::user();

        if ($user === null) {
            // Signed out, scoped to "own": nobody's, which is the honest answer
            // and not everybody's.
            return $query->whereRaw('1 = 0');
        }

        return $query
            ->where('notifiable_type', $user->getMorphClass())
            ->where('notifiable_id', (string) $user->getAuthIdentifier());
    }
}
