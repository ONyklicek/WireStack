<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Actions\Action;
use NyonCode\WireCore\Actions\ActionGroup;

/**
 * ActionGroup::lazyMenu() — opt-in deferral of the dropdown menu markup
 * (render-engine-htmlable-first.md §6). This pins the API and the serialized spec
 * the client renders on open; the client render + fuse + CDP are the next phase.
 */
function lazyRecord(): Model
{
    $record = new class extends Model
    {
        protected $guarded = [];
    };
    $record->forceFill(['id' => 7]);

    return $record;
}

it('defaults to eager and opts in fluently', function () {
    expect(ActionGroup::make([])->isLazyMenu())->toBeFalse()
        ->and(ActionGroup::make([])->lazyMenu()->isLazyMenu())->toBeTrue()
        ->and(ActionGroup::make([])->lazyMenu(false)->isLazyMenu())->toBeFalse();
});

it('serializes plain actions into a client-invocable spec', function () {
    $specs = ActionGroup::make([
        Action::make('edit'),
        Action::make('delete'),
    ])->getDropdownItemSpecs(lazyRecord());

    expect($specs)->toHaveCount(2)
        ->and($specs[0]['type'])->toBe('button')
        ->and($specs[0]['label'])->toBe('Edit')
        ->and($specs[0]['testId'])->toBe('menu-action-edit')
        // MountActionClickResolver → mountAction('edit'): method + args split so the
        // client calls $wire.mountAction('edit') without evaluating a string.
        ->and($specs[0]['method'])->toBe('mountAction')
        ->and($specs[0]['args'])->toBe(['edit'])
        ->and($specs[1]['method'])->toBe('mountAction')
        ->and($specs[1]['args'])->toBe(['delete']);
});

it('marks a disabled action and a link distinctly', function () {
    $specs = ActionGroup::make([
        Action::make('locked')->disabled(),
        Action::make('docs')->url('https://example.test/docs', openInNewTab: true),
    ])->getDropdownItemSpecs(lazyRecord());

    expect($specs[0]['type'])->toBe('disabled')
        ->and($specs[0])->not->toHaveKey('method')
        ->and($specs[1]['type'])->toBe('link')
        ->and($specs[1]['href'])->toBe('https://example.test/docs')
        ->and($specs[1]['newTab'])->toBeTrue();
});

it('emits a divider entry', function () {
    $specs = ActionGroup::make([
        Action::make('edit'),
        Action::divider(),
        Action::make('delete'),
    ])->getDropdownItemSpecs(lazyRecord());

    expect($specs)->toHaveCount(3)
        ->and($specs[1])->toBe(['type' => 'divider']);
});
