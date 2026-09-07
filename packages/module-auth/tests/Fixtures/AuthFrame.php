<?php

declare(strict_types=1);

namespace NyonCode\WireModuleAuth\Tests\Fixtures;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use NyonCode\WireModuleAuth\Support\Frame;

/**
 * A stand-in for the shell's signed-out frame.
 *
 * The suite registers the `wire-admin` namespace itself rather than booting the
 * shell: the
 * assertions here are about the screens, the frame has its own suite, and a
 * package whose tests only pass with an optional dependency present is a package
 * that has quietly made it required.
 *
 * What the seam actually needs from a frame is this small — a component that
 * takes a title and renders a slot — and that is worth having in front of you
 * when reading {@see Frame}.
 */
class AuthFrame extends Component
{
    public function __construct(public ?string $title = null) {}

    public function render(): View
    {
        return view('wire-admin::auth-layout');
    }
}
