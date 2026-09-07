<?php

declare(strict_types=1);

namespace NyonCode\WireModuleAuth\Support;

use Illuminate\Support\Facades\View;
use NyonCode\WireModuleAuth\Exceptions\AuthFrameException;
use NyonCode\WireModuleAuth\Install\LayoutScaffold;

/**
 * Which layout the signed-out screens render inside.
 *
 * This package ships no frame, and that is the decision rather than an omission.
 * A second frame here would be the same forty lines the shell already has — the
 * doctype, the theme decision before the first paint, the asset directives, a
 * centred card — and two copies of a frame diverge on the first change to
 * either. The shell owns chrome; these are screens.
 *
 * So the layout is named, and `auto` asks three questions in order:
 *
 * 1. **The application's own**, at {@see LayoutScaffold::COMPONENT}, which the
 *    installer writes. It has to come first: the shell's frame takes the
 *    stylesheet from a `head` slot — a package cannot know an application's Vite
 *    entry names — so rendering straight into the shell's frame gives a login
 *    page with the framework's markup and **none of the application's styles**.
 *    That page has no error on it and looks like a broken package.
 * 2. **The shell's**, which is the honest answer for an application that has the
 *    shell and has not run the installer: styling missing, everything else
 *    right.
 * 3. **Nothing**, which raises rather than rendering an empty component name.
 */
final class Frame
{
    /** The shell's signed-out frame, and the class that exists only with it. */
    public const SHELL_LAYOUT = 'wire-admin::auth-layout';

    public const SHELL_CLASS = 'NyonCode\\WireAdmin\\View\\AuthLayout';

    /**
     * The layout component the screens render inside.
     *
     * @throws AuthFrameException when the application has neither a layout of
     *                            its own nor the shell
     */
    public static function component(): string
    {
        $layout = config('wire-module-auth.layout', 'auto');

        if (is_string($layout) && $layout !== '' && $layout !== 'auto') {
            return $layout;
        }

        if (self::hasApplicationLayout()) {
            return LayoutScaffold::COMPONENT;
        }

        if (self::hasShell()) {
            return self::SHELL_LAYOUT;
        }

        throw AuthFrameException::undecided();
    }

    /**
     * Whether the application has the layout the installer writes.
     *
     * Asked as a *view* — `components.` is the directory a Blade component
     * lives in — while what is returned is the *component* name. That asymmetry
     * is Blade's, and keeping both halves in {@see LayoutScaffold} is what stops
     * them drifting.
     */
    public static function hasApplicationLayout(): bool
    {
        return View::exists('components.'.LayoutScaffold::COMPONENT);
    }

    /**
     * Whether the admin shell — and so its frame — is here to render in.
     *
     * Two questions, and both are load-bearing. `class_exists` answers whether
     * the package is installed, which a view name cannot: an application that
     * published the shell's views and then removed the package still has the
     * file. `View::exists` answers whether its provider has booted and
     * registered the namespace, which the class cannot: this is asked at render
     * as well as at boot, and provider order is composer's discovery order.
     *
     * Together they answer the only question a caller has — can this be
     * rendered — rather than the two halves of it.
     */
    public static function hasShell(): bool
    {
        return class_exists(self::SHELL_CLASS) && View::exists(self::SHELL_LAYOUT);
    }
}
