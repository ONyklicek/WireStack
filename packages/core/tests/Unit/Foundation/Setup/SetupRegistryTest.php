<?php

declare(strict_types=1);

use Illuminate\Container\Container;
use NyonCode\WireCore\Foundation\Setup\Contracts\SetupConsole;
use NyonCode\WireCore\Foundation\Setup\Contracts\SetupStep;
use NyonCode\WireCore\Foundation\Setup\SetupOutcome;
use NyonCode\WireCore\Foundation\Setup\SetupRegistry;
use NyonCode\WireCore\Foundation\Setup\SetupState;

/*
 * Where a package contributes something the application still has to do.
 *
 * The registry is the seam that lets `wire-suite` ask about a media disk
 * without knowing what one is: the package that knows registers a step, and the
 * installer only collects, orders and asks.
 */

/** A step that does nothing, so the registry can be tested without one. */
function srStep(string $label = 'Something', int $sort = 100): SetupStep
{
    return new class($label, $sort) implements SetupStep
    {
        public function __construct(private string $label, private int $sort) {}

        public function label(): string
        {
            return $this->label;
        }

        public function state(): SetupState
        {
            return SetupState::Pending;
        }

        public function summary(): string
        {
            return 'stands in for a real step';
        }

        public function apply(SetupConsole $console): SetupOutcome
        {
            return SetupOutcome::Applied;
        }

        public function sort(): int
        {
            return $this->sort;
        }
    };
}

beforeEach(function () {
    SetupRegistry::instance()->flush();
});

it('keeps what a package registered, in the order it was registered', function () {
    SetupRegistry::instance()
        ->register('First')
        ->register('Second', 'Third');

    expect(SetupRegistry::instance()->all())->toBe(['First', 'Second', 'Third']);
});

it('takes the same step twice without listing it twice', function () {
    // A provider that boots in both a test and the application under test is
    // the ordinary case, not a mistake worth an exception — and a step listed
    // twice would be asked twice.
    SetupRegistry::instance()->register('Once')->register('Once');

    expect(SetupRegistry::instance()->all())->toBe(['Once']);
});

it('is one instance, whoever reaches it first', function () {
    // Provider order is composer's discovery order and not a contract, so the
    // package registering a step may well run before wire-core. Resolving an
    // unbound concrete class would hand each provider its own object and the
    // step would vanish — on some machines, depending on a lockfile.
    SetupRegistry::instance()->register('Contributed');

    expect(Container::getInstance()->make(SetupRegistry::class)->all())
        ->toBe(['Contributed'])
        ->and(SetupRegistry::instance())->toBe(Container::getInstance()->make(SetupRegistry::class));
});

it('lets an application bind a registry of its own', function () {
    $mine = new SetupRegistry;
    $mine->register('Mine');

    Container::getInstance()->instance(SetupRegistry::class, $mine);

    expect(SetupRegistry::instance()->all())->toBe(['Mine']);
});

it('forgets everything when flushed', function () {
    SetupRegistry::instance()->register('Gone');

    expect(SetupRegistry::instance()->flush()->all())->toBe([]);
});

it('describes a step by three questions and one action', function () {
    // The contract's whole shape: what is it called, where does it stand, what
    // would it do — and only then, do it.
    $step = srStep('First administrator', 300);

    expect($step->label())->toBe('First administrator')
        ->and($step->sort())->toBe(300)
        ->and($step->state())->toBe(SetupState::Pending)
        ->and($step->summary())->toBeString()
        ->and($step->apply(new class implements SetupConsole
        {
            public function ask(string $question, ?string $default = null): string
            {
                return (string) $default;
            }

            public function secret(string $question): string
            {
                return '';
            }

            public function confirm(string $question, bool $default = true): bool
            {
                return $default;
            }

            public function choose(string $question, array $options, ?string $default = null): string
            {
                return (string) $default;
            }

            public function note(string $message): void {}

            public function warn(string $message): void {}

            public function isInteractive(): bool
            {
                return false;
            }
        }))->toBe(SetupOutcome::Applied);
});
