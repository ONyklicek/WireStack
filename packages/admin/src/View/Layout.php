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
    public function __construct(
        public ?string $title = null,
        public bool $linkedOnly = false,
    ) {}

    public function render(): View
    {
        return view('wire-admin::layout');
    }
}
