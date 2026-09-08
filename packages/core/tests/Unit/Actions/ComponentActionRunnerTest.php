<?php

declare(strict_types=1);

use NyonCode\WireCore\Actions\Action;
use NyonCode\WireCore\Actions\Support\ActionCallbackInvoker;
use NyonCode\WireCore\Actions\Support\ComponentActionRunner;
use NyonCode\WireCore\Foundation\Contracts\ActionContract;
use NyonCode\WireCore\Foundation\Contracts\RunsComponentActions;

/**
 * The Actions side of the seam a widget's header button reaches across.
 *
 * `Widgets` and `Actions` are sibling L2 modules and neither may import the
 * other (ADR 0025), so the widget host resolves this contract from the container
 * and hands over an `ActionContract`. Everything module-specific — that an
 * action has a callback, and how that callback is called — is on this side.
 */
it('is what the container answers with for the contract', function () {
    // Bound to the contract rather than to the class, so a consumer names the
    // Foundation interface and nothing else.
    expect(app(RunsComponentActions::class))->toBeInstanceOf(ComponentActionRunner::class);
});

it('runs the action callback with the context it was given', function () {
    $seen = null;

    app(RunsComponentActions::class)->runComponentAction(
        Action::make('recount')->action(function ($widget, $livewire) use (&$seen) {
            $seen = [$widget, $livewire];
        }),
        ['widget' => 'the-widget', 'livewire' => 'the-host'],
    );

    expect($seen)->toBe(['the-widget', 'the-host']);
});

it('passes only what the callback asked for, and lets the rest default', function () {
    // The calling convention, and the reason it is not app()->call(): a callback
    // naming something the payload does not carry gets its own default rather
    // than a container resolution failure.
    $seen = null;

    app(RunsComponentActions::class)->runComponentAction(
        Action::make('a')->action(function ($widget, $missing = 'fallback') use (&$seen) {
            $seen = [$widget, $missing];
        }),
        ['widget' => 'w', 'unused' => 'ignored'],
    );

    expect($seen)->toBe(['w', 'fallback']);
});

it('does nothing for an action with no callback', function () {
    // A legitimate declaration: `->url(…)` renders as a link the browser answers,
    // so there is nothing here to run and nothing to complain about.
    expect(fn () => app(RunsComponentActions::class)
        ->runComponentAction(Action::make('docs')->url('/docs')))
        ->not->toThrow(Throwable::class);
});

it('does nothing for a contract implementation that is not an action', function () {
    $foreign = new class implements ActionContract
    {
        public function getName(): string
        {
            return 'foreign';
        }

        public function isHidden(): bool
        {
            return false;
        }
    };

    expect(fn () => app(RunsComponentActions::class)->runComponentAction($foreign))
        ->not->toThrow(Throwable::class);
});

it('invokes a plain closure the same way the action host does', function () {
    // The extracted owner both callers share; `InteractsWithActions` delegates
    // here rather than keeping a copy of the convention.
    $invoker = new ActionCallbackInvoker;

    expect($invoker->invoke(fn (string $a, int $b = 7) => $a.$b, ['a' => 'x']))->toBe('x7')
        ->and($invoker->invoke(fn () => 'no args', ['a' => 'x']))->toBe('no args');
});
