<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\View;
use NyonCode\WireCore\Actions\Action;
use NyonCode\WireCore\Actions\BaseAction;
use NyonCode\WireCore\Actions\Contracts\ResolvesActionClick;
use NyonCode\WireCore\Actions\Support\MountActionClickResolver;

/**
 * Byte-identity guard for the menu-item skeleton (Action::renderForDropdown()).
 *
 * The dropdown twin of ActionButtonSkeletonTest. A table's row context menu renders
 * every item for every row, and that was measured at 30–45% of a whole table render.
 * The item is now compiled once per SHAPE and spliced: the click expression is the
 * one per-record value in the markup, a Blade `{{ }}` inside `wire:click`.
 *
 * The reference is the view rendered directly; the comparison is byte for byte,
 * across every shape the view branches on and against record keys chosen to break
 * naive escaping. If this fails, the skeleton is wrong — not the test.
 */
function diRecord(int|string $id = 1): Model
{
    $record = new class extends Model
    {
        protected $guarded = [];

        protected $table = 'di_records';
    };
    $record->forceFill(['id' => $id, 'name' => 'Row '.$id, 'state' => 'open']);

    return $record;
}

/**
 * The pre-skeleton path: the canonical view for this record, as-is — and, as
 * renderForDropdown() always did before rendering, nothing for an action this
 * record may not run.
 */
function diClassic(Action $action, ?Model $record, ?ResolvesActionClick $click = null): string
{
    if (! $action->canExecute($record)) {
        return '';
    }

    return view('wire-core::actions.dropdown-item', [
        'action' => $action,
        'record' => $record,
        'click' => $click ?? new MountActionClickResolver,
    ])->render();
}

/** A host resolver that puts the record key straight into the expression. */
function diClick(): ResolvesActionClick
{
    return new class implements ResolvesActionClick
    {
        public function clickHandler(BaseAction $action, ?Model $record): string
        {
            return sprintf("openActionModal('%s','%s')", $record?->getKey() ?? '', $action->getName());
        }
    };
}

/**
 * Every shape dropdown-item.blade.php branches on, as a fresh action each time —
 * the skeleton cache lives on the instance, so a shared one would hide a miss.
 *
 * @return array<string, Closure(): Action>
 */
function diShapes(): array
{
    return [
        'plain' => fn () => Action::make('edit')->label('Edit'),
        'icon' => fn () => Action::make('edit')->label('Edit')->icon('outline:pencil'),
        'closure icon' => fn () => Action::make('edit')->label('Edit')
            ->icon(fn ($r) => $r->getKey() === 2 ? 'outline:trash' : 'outline:pencil'),
        'coloured' => fn () => Action::make('del')->label('Delete')->color('danger'),
        'closure colour' => fn () => Action::make('del')->label('Delete')
            ->color(fn ($r) => $r->getKey() === 2 ? 'danger' : 'success'),
        'closure label' => fn () => Action::make('edit')->label(fn ($r) => 'Edit '.$r->name),
        'escaped label' => fn () => Action::make('edit')->label('Edit <b> & "co"'),
        'static url' => fn () => Action::make('open')->label('Open')->url('/x'),
        'closure url' => fn () => Action::make('open')->label('Open')->url(fn ($r) => '/r/'.$r->getKey()),
        'url new tab' => fn () => Action::make('open')->label('Open')->url('/x', true),
        'disabled' => fn () => Action::make('edit')->label('Edit')->disabled(),
        'closure disabled' => fn () => Action::make('edit')->label('Edit')->disabled(fn ($r) => $r->getKey() === 2),
        'confirmation' => fn () => Action::make('del')->label('Delete')->requiresConfirmation(),
        'shortcut' => fn () => Action::make('edit')->label('Edit')->keyboardShortcut('e'),
        'hidden' => fn () => Action::make('edit')->label('Edit')->visible(false),
        'closure hidden' => fn () => Action::make('edit')->label('Edit')->visible(fn ($r) => $r->getKey() !== 2),
    ];
}

/** Keys chosen to break naive escaping if the slot's encoding were wrong. */
function diKeys(): array
{
    return [1, 2, 42, "a'b", 'a"b', 'a&b', 'a<x>b', 'ěščřž', 'a\\b', '0'];
}

// ─── The guard ───────────────────────────────────────────────────────────────

it('renders a menu item byte-identically to the view it replaces', function (Closure $make) {
    // One action across every key, the way a table renders one action down its
    // rows — the shape cache is exercised, not bypassed by a fresh instance.
    $action = $make();

    foreach (diKeys() as $key) {
        $record = diRecord($key);

        // As with the button, a compiled skeleton is trimmed: the view file's own
        // leading/trailing newline is dead space between items. Nothing else may differ.
        expect($action->renderForDropdown($record, diClick()))
            ->toBe(trim(diClassic($make(), $record, diClick())), 'key '.var_export($key, true));
    }
})->with(diShapes());

it('renders a menu item byte-identically with the default resolver and with no record', function (Closure $make) {
    expect($make()->renderForDropdown(null))->toBe(trim(diClassic($make(), null)))
        ->and($make()->renderForDropdown(diRecord(7)))->toBe(trim(diClassic($make(), diRecord(7))));
})->with(diShapes());

it('leaves no slot sentinel in a menu item', function (Closure $make) {
    expect($make()->renderForDropdown(diRecord(3), diClick()))->not->toContain('WIRE_SLOT');
})->with(diShapes());

// ─── The point of it ─────────────────────────────────────────────────────────

/** Renders $rows records through one action's menu item; returns the view renders it cost. */
function diRenderCost(Action $action, int $rows): int
{
    $count = 0;
    View::composer('*', function () use (&$count): void {
        $count++;
    });

    $click = diClick();

    foreach (range(1, $rows) as $id) {
        $action->renderForDropdown(diRecord($id), $click);
    }

    return $count;
}

it('renders a menu item once per shape, not once per row', function () {
    $five = diRenderCost(Action::make('edit')->label('Edit')->icon('outline:pencil'), 5);
    $twenty = diRenderCost(Action::make('edit')->label('Edit')->icon('outline:pencil'), 20);

    expect($five)->toBe(1)
        ->and($twenty)->toBe(1);
});

it('gives a per-record menu item its own skeleton and each row its own click', function () {
    $count = diRenderCost(Action::make('edit')->label('Edit')->disabled(fn ($r) => $r->getKey() % 2 === 0), 20);

    $action = Action::make('edit')->label('Edit')->disabled(fn ($r) => $r->getKey() % 2 === 0);
    $rendered = [];
    foreach (range(1, 20) as $id) {
        $rendered[$id] = $action->renderForDropdown(diRecord($id), diClick());
    }

    // Two shapes — enabled and disabled — for twenty rows.
    expect($count)->toBe(2)
        ->and($rendered[2])->toContain('aria-disabled="true"')
        ->and($rendered[3])->not->toContain('aria-disabled');

    foreach ([1, 3, 19] as $id) {
        expect($rendered[$id])->toContain("openActionModal(&#039;{$id}&#039;,&#039;edit&#039;)");
    }
});
