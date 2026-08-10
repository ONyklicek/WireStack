<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Actions\Action;
use NyonCode\WireCore\Actions\ActionGroup;
use NyonCode\WireCore\Actions\BaseAction;
use NyonCode\WireCore\Actions\Contracts\RendersAsMenuItem;
use NyonCode\WireCore\Actions\Contracts\ResolvesActionClick;
use NyonCode\WireCore\Actions\HeaderAction;

/*
 * A dropdown is not only a row surface: the table toolbar collapses its
 * record-less header actions into the same canonical ActionGroup. These cover
 * the group holding actions that never see a record.
 */

/** Stands in for a host (the table) that maps a record-less action to its own method. */
final class RecordlessClickResolver implements ResolvesActionClick
{
    public function clickHandler(BaseAction $action, ?Model $record): string
    {
        return "runIt('{$action->getName()}')";
    }
}

it('lets a header action render itself as a menu row', function () {
    $html = HeaderAction::make('import')->label('Import')->renderForDropdown(null, new RecordlessClickResolver);

    expect(HeaderAction::make('import'))->toBeInstanceOf(RendersAsMenuItem::class)
        ->and($html)->toContain('menu-action-import')
        ->and($html)->toContain('Import')
        // The host, not core, decides what the row calls.
        ->and($html)->toContain('runIt(&#039;import&#039;)');
});

it('renders nothing for a header action the viewer may not run', function () {
    expect(HeaderAction::make('import')->visible(false)->renderForDropdown())->toBe('');
});

it('renders a record-less group as one dropdown of menu rows', function () {
    $html = ActionGroup::make([
        HeaderAction::make('create')->label('New user'),
        HeaderAction::make('import')->label('Import'),
    ])->render(null, new RecordlessClickResolver);

    expect($html)->toContain('action-group-trigger')
        ->and($html)->toContain('menu-action-create')
        ->and($html)->toContain('menu-action-import')
        ->and($html)->toContain('runIt(&#039;create&#039;)');
});

it('drops a hidden action from a record-less group', function () {
    $group = ActionGroup::make([
        HeaderAction::make('create')->label('New user'),
        HeaderAction::make('import')->visible(false),
    ]);

    // A group left with one action renders that action itself rather than a
    // one-item menu — asserted end to end in the table package, because a
    // header action's own button view is wire-table's.
    expect($group->getVisibleActions())->toHaveCount(1)
        ->and($group->getVisibleActions()[0]->getName())->toBe('create');
});

it('ships a record-less lazy menu item as its rendered fragment', function () {
    $specs = ActionGroup::make([
        HeaderAction::make('create')->label('New user'),
        HeaderAction::make('import')->label('Import'),
    ])->lazyMenu()->getDropdownItemSpecs(null, new RecordlessClickResolver);

    // Only a row Action carries the per-record vocabulary the spec shape is
    // built from; a header action ships as HTML instead of being dropped.
    expect($specs)->toHaveCount(2)
        ->and($specs[0]['type'])->toBe('html')
        ->and($specs[0]['html'])->toContain('menu-action-create');
});

it('falls back to an item own rendering when it has no menu surface', function () {
    // A BulkAction is the real case: it lives on BaseAction and renders itself,
    // but declares no menu row. Better a button in the menu than a fatal.
    $group = ActionGroup::make([
        new class('archive') extends BaseAction
        {
            public function toHtml(): string
            {
                return '<span data-testid="own-render">Archive</span>';
            }
        },
        HeaderAction::make('import')->label('Import'),
    ]);

    $specs = $group->getDropdownItemSpecs(null, new RecordlessClickResolver);

    expect($group->getDropdownItemsHtml(null, new RecordlessClickResolver)->toHtml())->toContain('data-testid="own-render"')
        ->and($specs[0])->toBe(['type' => 'html', 'html' => '<span data-testid="own-render">Archive</span>']);
});

it('renders a row action as a menu row without a record', function () {
    $html = Action::make('export')->label('Export')->renderForDropdown();

    expect($html)->toContain('menu-action-export');
});
