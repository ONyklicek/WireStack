<?php

declare(strict_types=1);

namespace NyonCode\WireAdmin\View;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * The frame for a page nobody is signed in to: `<x-wire-admin::auth-layout>`.
 *
 * A login screen wants the application's chrome and none of its navigation —
 * there is no menu to draw for someone who has not authenticated, and a command
 * palette over resources they cannot reach is worse than no palette.
 *
 * **It contains no authentication.** Not a gap: Laravel owns that, through
 * Fortify (headless) or Breeze (scaffolding), and writing it here would mean
 * owning login rate limiting, reset-token expiry, email verification, 2FA and
 * session fixation — a security surface with no framework advantage to show for
 * it. This is the card those packages' views render inside, so an application
 * gets one look without this package getting one credential.
 */
class AuthLayout extends Component
{
    public function __construct(
        public ?string $title = null,
    ) {}

    public function render(): View
    {
        return view('wire-admin::auth-layout');
    }
}
