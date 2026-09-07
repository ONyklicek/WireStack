<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\View;
use NyonCode\WireModuleAuth\Exceptions\AuthFrameException;
use NyonCode\WireModuleAuth\Install\LayoutScaffold;
use NyonCode\WireModuleAuth\Support\Frame;
use NyonCode\WireModuleAuth\Tests\Fixtures\AuthFrame;

/*
 * Which layout the signed-out screens render inside.
 *
 * The package ships no frame, so this is the whole of the decision: name one,
 * borrow the shell's, or say so loudly. The third case is the one worth a test —
 * it is the difference between a sentence naming the config key and Blade's
 * "unable to locate component", which points at this package's view for a
 * mistake in the application's configuration.
 *
 * The suite deliberately does not register the shell, so `hasShell()` is false
 * here unless a test hands the namespace over itself. That is also the shape of
 * the application this package has to work in.
 */

function amGiveApplicationLayout(): void
{
    // What the installer writes into `resources/views/components/`. Registered
    // as a view path rather than written to disk: the question `Frame` asks is
    // whether the view resolves, and a test that wrote into the skeleton would
    // leak the answer into every test after it.
    View::addLocation(__DIR__.'/../Fixtures/app-views');
}

function amGiveShell(): void
{
    // What wire-admin's provider does through the toolkit: register the
    // namespace its frame is found under, and the component that renders it.
    // Asked of the view rather than only of the class because a class on the
    // autoloader whose provider has not booted cannot be rendered.
    View::addNamespace('wire-admin', __DIR__.'/../Fixtures/views');
    Blade::component(Frame::SHELL_LAYOUT, AuthFrame::class);
}

it('uses the layout the application named', function () {
    config()->set('wire-module-auth.layout', 'components.layouts.guest');

    expect(Frame::component())->toBe('components.layouts.guest');
});

it('prefers the layout the installer wrote for the application', function () {
    // First of the three, and the order is the whole point: the shell's frame
    // takes the stylesheet from a `head` slot nothing else fills, so an
    // application that has both gets its own — with its styles on it — rather
    // than a login page carrying the framework's markup and none of its CSS.
    config()->set('wire-module-auth.layout', 'auto');
    amGiveShell();
    amGiveApplicationLayout();

    expect(Frame::hasApplicationLayout())->toBeTrue()
        ->and(Frame::hasShell())->toBeTrue()
        ->and(Frame::component())->toBe(LayoutScaffold::COMPONENT);
});

it('borrows the shell frame when the shell is there', function () {
    config()->set('wire-module-auth.layout', 'auto');
    amGiveShell();

    expect(Frame::hasShell())->toBeTrue()
        ->and(Frame::component())->toBe(Frame::SHELL_LAYOUT);
});

it('says which line to write when there is no frame at all', function () {
    // Not an exception for its own sake: without it Blade reports "unable to
    // locate component []", which points at this package's view for a decision
    // the application has not made.
    config()->set('wire-module-auth.layout', 'auto');

    expect(Frame::hasShell())->toBeFalse()
        ->and(fn () => Frame::component())->toThrow(
            AuthFrameException::class,
            'nyoncode/wire-admin',
        );
});

it('treats a blanked-out layout name as undecided', function () {
    // A published config with the key emptied is the same question as `auto`,
    // and must not resolve to the empty component name — which renders nothing
    // and reports no error.
    config()->set('wire-module-auth.layout', '');
    amGiveShell();

    expect(Frame::component())->toBe(Frame::SHELL_LAYOUT);
});

it('does not mistake a published view for an installed shell', function () {
    // An application that published the shell's views and then removed the
    // package still has the file. The class is the half of the question a view
    // name cannot answer — and in this monorepo it is always true, which is why
    // the pair is what gets asserted rather than either half.
    config()->set('wire-module-auth.layout', 'auto');

    expect(class_exists(Frame::SHELL_CLASS))->toBeTrue()
        ->and(View::exists(Frame::SHELL_LAYOUT))->toBeFalse()
        ->and(Frame::hasShell())->toBeFalse();
});
