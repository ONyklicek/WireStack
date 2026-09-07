<?php

declare(strict_types=1);

namespace NyonCode\WireAdmin\View;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * The page frame: `<x-wire-admin::layout>`.
 *
 * A layout with slots, not a `Panel` object with configuration. Branding, the
 * user menu and anything else an application wants in the chrome arrive as
 * markup it wrote:
 *
 * ```blade
 * <x-wire-admin::layout :title="$title">
 *     <x-slot:brand>{{ config('app.name') }}</x-slot:brand>
 *     <x-slot:user><x-app-user-menu /></x-slot:user>
 *
 *     {{ $slot }}
 * </x-wire-admin::layout>
 * ```
 *
 * The fluent-builder alternative is the one ADR 0020 named as the risk and
 * ADR 0028 §1b refuses: a class holding shell configuration is what pulls
 * branding, colours, auth and per-panel middleware into something the registries
 * below would eventually have to know about. Slots carry the same information
 * and know nothing.
 *
 * It renders on a full page load, which is what makes the sidebar's zone read
 * safe — see {@see Sidebar}.
 */
class Layout extends Component
{
    /**
     * @param  bool|null  $notifications  Whether to mount the bell. Null asks the
     *                                    configuration, which is the honest default: the bell reads stored
     *                                    notifications, and only the `database` driver stores any.
     */
    public function __construct(
        public ?string $title = null,
        public bool $linkedOnly = false,
        public ?bool $notifications = null,
    ) {}

    /**
     * Whether this page shows the notification bell.
     *
     * Asked of the driver rather than assumed, and the reason is a failure this
     * caught: the bell counts rows in the notifications table as soon as a user
     * is authenticated, so mounting it under the default `session` driver is a
     * SQL error on every page of an application that never asked for stored
     * notifications — and a query per render for one that did not migrate.
     *
     * An application that keeps its own table and knows better passes the
     * attribute and skips the question.
     */
    public function showsNotifications(): bool
    {
        if ($this->notifications !== null) {
            return $this->notifications;
        }

        return in_array('database', (array) config('wire-core.notifications.default', []), true);
    }

    public function render(): View
    {
        return view('wire-admin::layout');
    }
}
