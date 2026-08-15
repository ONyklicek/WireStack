<?php

declare(strict_types=1);

use Illuminate\View\ViewException;
use Livewire\Component;
use Livewire\Livewire;

/**
 * Two Livewire island behaviours this framework's design decisions rest on.
 *
 * Both were established by probing rather than by reading, both were got wrong
 * once by reasoning from the docs, and both decide whether a planned island is
 * worth building — so they are pinned here. If a Livewire upgrade changes
 * either, this fails and the decision gets revisited instead of quietly rotting.
 *
 * See `architecture/plans/livewire-4-migration-and-performance.md` §5.4.3.
 */
class IsmNested extends Component
{
    public int $n = 1;

    public function render(): string
    {
        return <<<'BLADE'
        <div>
            @island('parent', always: true)
                <p>parent {{ $this->n }}</p>

                @island('childPlain')
                    <p>child-plain {{ $this->n }}</p>
                @endisland

                @island('childAlways', always: true)
                    <p>child-always {{ $this->n }}</p>
                @endisland
            @endisland
        </div>
        BLADE;
    }
}

class IsmPerRow extends Component
{
    /** @return array<int, array{id: int, name: string}> */
    public function getRowsProperty(): array
    {
        return [['id' => 1, 'name' => 'first'], ['id' => 2, 'name' => 'second']];
    }

    public function render(): string
    {
        return <<<'BLADE'
        <div>
            @foreach ($this->rows as $row)
                @island(name: 'row-'.$row['id'], always: true)
                    <p>{{ $row['name'] }}</p>
                @endisland
            @endforeach
        </div>
        BLADE;
    }
}

it('skips a nested island without always when its parent renders alone', function () {
    // The saving a nested island can offer, and the staleness that comes with it:
    // the child is not rendered and not sent, so whatever it shows keeps whatever
    // it showed — and a click inside the parent cannot target the child, because
    // one call targets one island.
    //
    // Note what makes this visible: `markIslandsAsMounted()`, which is what
    // SupportIslands::hydrate() does on every request after the first. Probed
    // without it, this reads the MOUNT, where every island renders — which is how
    // the opposite conclusion got drawn once.
    $component = Livewire::test(IsmNested::class);
    $instance = $component->instance();

    expect($component->html())->toContain('child-plain 1');

    $instance->markIslandsAsMounted();
    $instance->n = 2;

    $token = collect($instance->getIslands())->firstWhere('name', 'parent')['token'];
    $parentOnly = $instance->renderIslandView('parent', $token);

    expect($parentOnly)->toContain('parent 2')
        ->and($parentOnly)->not->toContain('child-plain 2')
        ->and($parentOnly)->toContain('mode=skip')
        // …and `always` is how a child opts back in.
        ->and($parentOnly)->toContain('child-always 2');
});

it('cannot name an island after something only a loop knows', function () {
    // Why there is no per-row island, and it is sharper than "islands can't be
    // used in loops": the compiled island body re-evaluates the DIRECTIVE'S OWN
    // ARGUMENTS inside the island's separate view file —
    //
    //     })(name: 'row-'.$row['id'], always: true);
    //
    // — and that file is given the component and its public properties, never the
    // enclosing loop's variables. So the name expression throws on the very first
    // render. An island's name has to be resolvable with the component alone.
    expect(fn () => Livewire::test(IsmPerRow::class)->html())
        ->toThrow(ViewException::class, 'Undefined variable $row');
});
