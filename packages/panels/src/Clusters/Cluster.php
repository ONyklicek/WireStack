<?php

declare(strict_types=1);

namespace NyonCode\WirePanels\Clusters;

use Illuminate\Contracts\View\View;
use NyonCode\WireCore\Core\Resources\Contracts\ProvidesNavigation;
use NyonCode\WireCore\Core\Resources\Navigation\ActiveNavigation;
use NyonCode\WireCore\Core\Resources\Navigation\NavigationItem;
use NyonCode\WireCore\Foundation\Routing\Zone;
use NyonCode\WirePanels\Enums\SubNavigationPosition;
use NyonCode\WirePanels\Pages\Page;

/**
 * One section of the admin: one entry in the menu, one URL prefix, and the way
 * across between its screens on every one of them.
 *
 *   final class Settings extends Cluster
 *   {
 *       protected static ?string $navigationIcon = 'outline:cog-6-tooth';
 *   }
 *
 *   // each member — a resource or a page — names it
 *   public static function cluster(): ?string { return Settings::class; }   // a resource
 *   protected static ?string $cluster = Settings::class;                    // a page
 *
 * **A page, because that is where every part of it already lives** (ADR 0039):
 * registered in `config('wire-panels.pages')` or discovered, routed at its key,
 * listed in the menu by the same statics, `url()` included. What differs is its
 * address: it renders nothing, and sends the viewer to the first member they
 * may open — a 403 when there is none they may, a 404 when there is none.
 *
 * Its menu entry stands for every member: it is lit on each of their pages, and
 * shown only while one of them would be.
 */
abstract class Cluster extends Page
{
    /** Where the members are drawn on each member page. */
    protected static SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Start;

    public static function subNavigationPosition(): SubNavigationPosition
    {
        return static::$subNavigationPosition;
    }

    /**
     * The page's entry, lit on every member's pages and hidden while none of
     * them would be shown.
     *
     * Visibility is asked of the members' own entries directly rather than of
     * the menu: the menu is being built when this is called, and asking it would
     * ask for this entry again.
     */
    public static function navigation(): NavigationItem
    {
        $item = parent::navigation();
        $members = app(ClusterNavigation::class)->members(static::class);

        return $item
            ->visible($item->isVisible() && self::anyMemberShown($members))
            ->activeWhen(static fn (ActiveNavigation $active): bool => $active->key !== null
                && ($active->key === static::key() || isset($members[$active->key])));
    }

    /** Off to the first member this person may open. */
    public function mount(): void
    {
        $navigation = app(ClusterNavigation::class);
        $landing = $navigation->landing(static::class, Zone::current(), request()->user());

        // Members this person may not see are still members: the section exists
        // and is refused, which is a 403. Only a cluster nothing names is a 404.
        if ($landing === null) {
            abort($navigation->members(static::class) === [] ? 404 : 403);
        }

        $this->redirect($landing);
    }

    /** Never reached on a full page load — the mount above redirects first. */
    public function render(): View
    {
        return view('wire-panels::clusters.cluster');
    }

    /**
     * @param  array<string, class-string>  $members
     */
    private static function anyMemberShown(array $members): bool
    {
        foreach ($members as $member) {
            if (is_subclass_of($member, ProvidesNavigation::class) && $member::navigation()->isVisible()) {
                return true;
            }
        }

        return false;
    }
}
