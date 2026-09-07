<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\View;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * Blade component: `<x-wire::menu-item icon="outline:user-circle" :href="$url">`.
 *
 * One row of a user menu — a link, or a button that submits the form around it.
 * It lives here rather than in the shell because two packages outside the shell
 * need it: the users module contributes a link to the profile page, the auth
 * module contributes the way out, and neither depends on `wire-admin`. A copy in
 * each would have been the same padding, hover and icon size written three
 * times, diverging on the first change to any of them.
 *
 * Deliberately *not* the dropdown item beside it: that one renders an Action —
 * it takes a BaseAction and resolves clicks, records and permissions through it.
 * This takes an href or a form button, which is what a profile link and a
 * sign-out actually are. Two surfaces that look alike and answer to different
 * owners.
 */
class MenuItem extends Component
{
    public function __construct(
        public ?string $href = null,
        public ?string $icon = null,
        public string $type = 'button',
    ) {}

    public function render(): View
    {
        return view('wire-core::foundation.menu-item');
    }
}
