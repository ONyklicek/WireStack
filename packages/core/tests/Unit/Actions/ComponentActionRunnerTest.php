<?php

declare(strict_types=1);

use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Gate;
use NyonCode\WireCore\Actions\Action;
use NyonCode\WireCore\Actions\Support\ActionCallbackInvoker;
use NyonCode\WireCore\Actions\Support\ComponentActionRunner;
use NyonCode\WireCore\Foundation\Contracts\ActionContract;
use NyonCode\WireCore\Foundation\Contracts\ClassifiesComponentActions;
use NyonCode\WireCore\Foundation\Contracts\RunsComponentActions;

function foreignAction(bool $hidden = false): ActionContract
{
    return new class($hidden) implements ActionContract
    {
        public function __construct(private readonly bool $hidden) {}

        public function getName(): string
        {
            return 'foreign';
        }

        public function isHidden(): bool
        {
            return $this->hidden;
        }
    };
}

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

/**
 * The classifying half of the same seam.
 *
 * A surface that offers a *list* of actions to a user and runs the one they pick
 * has to ask two questions the runner deliberately never asks: may this user run
 * it, and was it going to stop and ask something first. Both answers live on
 * `BaseAction`, which a sibling L2 surface may not import — so they cross the
 * same boundary the runner does.
 */
it('answers both contracts with one object', function () {
    // Aliased rather than bound twice: two singletons would mean the instance
    // that says "yes you may run this" is not the instance that runs it.
    // `toBeInstanceOf` as well as `toBe`: the alias made both names resolve to the
    // same object while the class had not declared the interface at all, and
    // nothing noticed until a real type hint asked. `app()` returns `mixed`.
    expect(app(ClassifiesComponentActions::class))
        ->toBe(app(RunsComponentActions::class))
        ->toBeInstanceOf(ClassifiesComponentActions::class)
        ->toBeInstanceOf(RunsComponentActions::class);
});

it('calls an action with a modal one that needs to ask first', function (Closure $build, bool $expected) {
    expect(app(ClassifiesComponentActions::class)->needsPrompt($build()))->toBe($expected);
})->with([
    'a plain action' => [fn () => Action::make('a')->action(fn () => null), false],
    'a link' => [fn () => Action::make('a')->url('/docs'), false],
    'a confirmation' => [fn () => Action::make('a')->requiresConfirmation(), true],
    'a modal heading' => [fn () => Action::make('a')->modalHeading('Sure?'), true],
]);

it('treats a foreign contract implementation as needing nothing', function () {
    // It promises a name and a visibility flag; it cannot have attached a modal,
    // because a modal is a thing only this module knows how to build.
    expect(app(ClassifiesComponentActions::class)->needsPrompt(foreignAction()))->toBeFalse();
});

it('filters on authorization, not only on visibility', function () {
    // The distinction that matters to a palette: `isHidden()` is what the author
    // wrote about rendering, `canExecute()` folds in the gate. Offering the first
    // as though it were the second lists something the user may not run.
    $this->actingAs(new class extends User
    {
        protected $attributes = ['id' => 1];
    });

    Gate::define('never', fn (): bool => false);
    Gate::define('always', fn (): bool => true);

    $classifier = app(ClassifiesComponentActions::class);

    expect($classifier->isRunnable(Action::make('a')->authorize('never')))->toBeFalse()
        ->and($classifier->isRunnable(Action::make('a')->authorize('always')))->toBeTrue()
        ->and($classifier->isRunnable(Action::make('a')->hidden()))->toBeFalse()
        ->and($classifier->isRunnable(Action::make('a')))->toBeTrue();
});

it('evaluates the action against the context it is given', function () {
    // A record action is authorized about a record, so the context has to reach
    // the closure the author wrote — otherwise every row answers the same.
    $classifier = app(ClassifiesComponentActions::class);
    $action = Action::make('cancel')->visible(fn ($record): bool => $record === 'open');

    expect($classifier->isRunnable($action, 'open'))->toBeTrue()
        ->and($classifier->isRunnable($action, 'closed'))->toBeFalse();
});

it('falls back to visibility for a foreign contract implementation', function () {
    // Waving it through would be the wrong default for the one surface that asks.
    expect(app(ClassifiesComponentActions::class)->isRunnable(foreignAction()))->toBeTrue()
        ->and(app(ClassifiesComponentActions::class)->isRunnable(foreignAction(hidden: true)))->toBeFalse();
});
