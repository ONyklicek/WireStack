<?php

declare(strict_types=1);

use Illuminate\Contracts\Support\Htmlable;
use Livewire\Component;
use Livewire\Drawer\Utils;
use Livewire\Livewire;
use NyonCode\WireCore\Foundation\Support\IslandViewScope;

/**
 * An island has to be able to render this framework's modals.
 *
 * Every modal here is an {@see Htmlable} that builds
 * its own view with an explicit data array (Modal Best Practices rule 5), so the
 * only channel that reaches it is the view factory's *shared* data. A full
 * component render shares `$__livewire` there; a targeted island render does not
 * — it puts it in the island body's data, which `@include` carries and
 * `view(...)->render()` does not.
 *
 * The result was an `Undefined variable $__livewire` (from `@entangle`, which
 * compiles to `$__livewire->getId()`) the moment an action modal was wrapped in
 * an island — a 500 on the very roundtrip the island exists to make cheap, and
 * one no full-render test can see, because the full render shares it.
 */
class IvsHost extends Component
{
    public bool $open = false;

    public function render(): string
    {
        return <<<'BLADE'
        <div>
            @island('confirm', always: true)
                {{ new \NyonCode\WireCore\Modals\Html\Confirmation(heading: 'Delete?', wireModel: 'open') }}
            @endisland
        </div>
        BLADE;
    }
}

/** @return array{0: IvsHost, 1: string} */
function ivsMountedIsland(): array
{
    $component = Livewire::test(IvsHost::class);

    /** @var IvsHost $instance */
    $instance = $component->instance();

    $island = collect($instance->getIslands())->firstWhere('name', 'confirm');

    expect($island)->not->toBeNull();

    return [$instance, $island['token']];
}

it('renders an Htmlable modal from inside a targeted island', function () {
    // The island rendered ON ITS OWN — the path a `wire:island` click takes, and
    // the one that used to throw.
    [$instance, $token] = ivsMountedIsland();

    $html = $instance->renderIslandView('confirm', $token);

    expect($html)->toContain('Delete?')
        // `@entangle` resolved, which is what needs `$__livewire`.
        ->toContain('.entangle(')
        ->toContain("'open'");
});

it('does not leave the component shared with every view afterwards', function () {
    // The scope is borrowed for the render and handed back: shared data outlives
    // the request otherwise, and a stale component would then answer `@this` and
    // `@entangle` for unrelated views.
    [$instance, $token] = ivsMountedIsland();

    $instance->renderIslandView('confirm', $token);

    expect(view()->shared('__livewire', 'absent'))->toBe('absent')
        ->and(view()->shared('_instance', 'absent'))->toBe('absent');
});

it('still renders the same modal through a full component render', function () {
    // The control: the path that always worked has to keep working, since the fix
    // shares the same key the full render shares.
    expect(Livewire::test(IvsHost::class)->html())
        ->toContain('Delete?')
        ->toContain('.entangle(');
});

it('renders a component view containing an island straight from PHP', function () {
    // The other path with no shared scope, and the one rule 3 depends on: a
    // component's own view rendered from PHP rather than through Livewire.
    // `@island` compiles to a directive guarded on `$__livewire`, so without the
    // scope it emits NOTHING — no error, no content. A view whose body lives in
    // an island would render its chrome and none of its body.
    $component = Livewire::test(IvsHost::class)->instance();

    $view = Utils::generateBladeView($component->render());

    // Straight through Blade: no Livewire pipeline, so nothing shares the scope.
    expect($view->render())->not->toContain('Delete?')
        // …and with it borrowed for the render, the island body is there.
        ->and(IslandViewScope::within($component, fn (): string => $view->render()))
        ->toContain('Delete?');
});

it('hands the scope back even when the render throws', function () {
    // It borrows global state; a leak would outlive the request and answer
    // `@this` for unrelated views.
    $component = Livewire::test(IvsHost::class)->instance();

    expect(fn () => IslandViewScope::within($component, function (): string {
        throw new RuntimeException('render failed');
    }))->toThrow(RuntimeException::class);

    expect(view()->shared('__livewire', 'absent'))->toBe('absent');
});
