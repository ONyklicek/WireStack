<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Actions\Action;
use NyonCode\WireCore\Actions\ActionGroup;
use NyonCode\WireTable\Actions\TableActionClickResolver;

function tableClickRecord(int $id = 5): Model
{
    $record = new class extends Model
    {
        protected $guarded = [];
    };
    $record->forceFill(['id' => $id]);

    return $record;
}

it('routes a plain row action to executeTableAction with the record key', function () {
    $resolver = new TableActionClickResolver;

    expect($resolver->clickHandler(Action::make('edit'), tableClickRecord(5)))
        ->toBe("executeTableAction('5', 'edit')");
});

it('routes a modal row action to openActionModal', function () {
    $resolver = new TableActionClickResolver;
    $action = Action::make('review')->modalHeading('Review');

    expect($resolver->clickHandler($action, tableClickRecord(9)))
        ->toBe("openActionModal('9', 'review')");
});

it('is the wire:click a rendered table row action emits', function () {
    $html = Action::make('edit')->render(tableClickRecord(3), new TableActionClickResolver);

    // Blade escapes the attribute value; the browser decodes it back for Livewire.
    expect($html)->toContain(e("executeTableAction('3', 'edit')"));
});

it('threads the resolver through an action group dropdown', function () {
    $group = ActionGroup::make([
        Action::make('edit'),
        Action::make('review')->modalHeading('Review'),
    ]);

    $html = $group->getDropdownItemsHtml(tableClickRecord(4), new TableActionClickResolver)->toHtml();

    expect($html)->toContain(e("executeTableAction('4', 'edit')"))
        ->and($html)->toContain(e("openActionModal('4', 'review')"));
});
