<?php

declare(strict_types=1);

use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireCore\Actions\ActionHalt;
use NyonCode\WireCore\Actions\Concerns\InteractsWithHalt;

/**
 * A halt without the action pipeline.
 *
 * Until 2.0 "stop and ask" was an action feature: it needed the action runtime,
 * the wire-forms host that composes it, and an action to be raised from. This is
 * the same mechanism on a component that has none of that — one trait, one host
 * view, and a method name to continue in.
 */
class PlainHaltHost extends Component
{
    use InteractsWithHalt;

    public string $log = '';

    public function archive(): void
    {
        $this->halt(
            ActionHalt::confirmDanger('Archive this?', 'It leaves the active list.'),
            then: 'archiveConfirmed',
            arguments: ['id' => 7],
        );
    }

    /** Asks for a reason, and refuses an empty one. */
    public function close(): void
    {
        $this->halt(
            ActionHalt::make()->heading('Why?')->validation(['reason' => 'required|min:3']),
            then: 'closeConfirmed',
        );
    }

    /** @param array<string, mixed> $data */
    public function archiveConfirmed(array $data, array $arguments): void
    {
        $this->log = 'archived:'.$arguments['id'];
    }

    /** @param array<string, mixed> $data */
    public function closeConfirmed(array $data, array $arguments): void
    {
        $this->log = 'closed:'.($data['reason'] ?? '');
    }

    /** Never reachable from a confirmation: the browser cannot call it either. */
    protected function secret(array $data, array $arguments): void
    {
        $this->log = 'secret';
    }

    public function render(): string
    {
        return <<<'BLADE'
            <div>
                <x-wire-actions::halt-host :component="$this" />
            </div>
        BLADE;
    }
}

it('raises a halt from an ordinary method and draws it', function () {
    Livewire::test(PlainHaltHost::class)
        ->call('archive')
        ->assertSet('mountedHalt.show', true)
        ->assertSet('log', '')
        ->assertSee('Archive this?')
        ->assertSee('It leaves the active list.');
});

it('continues in the named method once confirmed, with the arguments it carried', function () {
    Livewire::test(PlainHaltHost::class)
        ->call('archive')
        ->call('submitHaltModal')
        ->assertSet('log', 'archived:7')
        ->assertSet('mountedHalt', []);
});

it('forgets the halt on dismiss and runs nothing', function () {
    Livewire::test(PlainHaltHost::class)
        ->call('archive')
        ->call('closeHaltModal')
        ->assertSet('log', '')
        ->assertSet('mountedHalt', []);
});

it('checks the halt\'s own rules without a form layer, keeping the modal open', function () {
    // No wire-forms here, so there is no Form to validate — but the rules the
    // halt declared are still the halt's, and they are what the submit answers.
    Livewire::test(PlainHaltHost::class)
        ->call('close')
        ->call('submitHaltModal', ['reason' => 'no'])
        ->assertHasErrors()
        ->assertSet('mountedHalt.show', true)
        ->assertSet('log', '');
});

it('runs the resume once the data passes', function () {
    Livewire::test(PlainHaltHost::class)
        ->call('close')
        ->call('submitHaltModal', ['reason' => 'duplicate record'])
        ->assertHasNoErrors()
        ->assertSet('log', 'closed:duplicate record');
});

it('ignores a submit when no halt is open', function () {
    // The state a halt leaves behind is the state a second confirm would run
    // from, so "is one open" gates the whole method rather than the resume.
    Livewire::test(PlainHaltHost::class)
        ->call('submitHaltModal')
        ->assertSet('log', '');
});

it('renders nothing at all while no halt is waiting', function () {
    Livewire::test(PlainHaltHost::class)
        ->assertDontSee('Archive this?')
        ->assertSet('mountedHalt', []);
});

it('refuses to resume into a method the browser could not have called itself', function () {
    // `mountedHalt` is a public property, so a tampered `then` would otherwise
    // turn a confirmation into a way to call the component's protected methods
    // with an array of the caller's choosing.
    Livewire::test(PlainHaltHost::class)
        ->call('archive')
        ->set('mountedHalt.then', 'secret')
        ->call('submitHaltModal')
        ->assertSet('log', '');
});
