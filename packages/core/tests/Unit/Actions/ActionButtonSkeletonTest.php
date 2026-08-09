<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\View;
use NyonCode\WireCore\Actions\Action;
use NyonCode\WireCore\Actions\BaseAction;
use NyonCode\WireCore\Actions\Contracts\ResolvesActionClick;
use NyonCode\WireCore\Actions\Support\MountActionClickResolver;

/**
 * Byte-identity guard for the button skeleton (Action::render()).
 *
 * A rendered action button was two `view()->render()` calls — the button view and
 * the content partial it includes — for every action on every row, which made the
 * action column the last N×View in the table. It is now compiled once per SHAPE
 * and spliced: the click expression is the only per-record value that reaches the
 * markup, and it lands in `wire:click` and up to three `wire:target`s, all of them
 * a Blade `{{ }}` inside an attribute — one slot, one encoding.
 *
 * That claim is only worth anything if the spliced output is what the view would
 * have produced, so this renders the view directly as the reference and compares
 * byte for byte, across every shape the view branches on and against record keys
 * chosen to break naive escaping.
 *
 * If this fails, the skeleton is wrong — not the test. Do not relax it.
 */
function abRecord(int|string $id = 1): Model
{
    $record = new class extends Model
    {
        protected $guarded = [];

        protected $table = 'ab_records';
    };
    $record->forceFill(['id' => $id, 'name' => 'Row '.$id, 'state' => 'open']);

    return $record;
}

/** The pre-skeleton path: render the canonical view for this record, as-is. */
function abClassic(Action $action, ?Model $record, ?ResolvesActionClick $click = null): string
{
    return view('wire-core::actions.button', [
        'action' => $action,
        'record' => $record,
        'click' => $click ?? new MountActionClickResolver,
    ])->render();
}

/** A host resolver that puts the record key straight into the expression. */
function abClick(): ResolvesActionClick
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
 * Every shape the button view branches on, as a fresh action each time — the
 * skeleton cache lives on the instance, so a shared one would hide a miss.
 *
 * @return array<string, Closure(): Action>
 */
function abShapes(): array
{
    return [
        'plain' => fn () => Action::make('edit')->label('Edit'),
        'icon' => fn () => Action::make('edit')->label('Edit')->icon('outline:pencil'),
        'icon only' => fn () => Action::make('edit')->label('Edit')->icon('outline:pencil')->hideLabel(),
        'icon after' => fn () => Action::make('edit')->label('Edit')->icon('outline:pencil', 'after'),
        'coloured' => fn () => Action::make('del')->label('Delete')->color('danger'),
        'sized' => fn () => Action::make('edit')->label('Edit')->size('lg'),
        'quiet' => fn () => Action::make('edit')->label('Edit')->quiet(),
        'static tooltip' => fn () => Action::make('edit')->label('Edit')->tooltip('Edit this'),
        'closure tooltip' => fn () => Action::make('edit')->label('Edit')->tooltip(fn ($r) => 'Edit '.$r->name),
        'closure label' => fn () => Action::make('edit')->label(fn ($r) => 'Edit '.$r->name),
        'static url' => fn () => Action::make('open')->label('Open')->url('/x'),
        'closure url' => fn () => Action::make('open')->label('Open')->url(fn ($r) => '/r/'.$r->getKey()),
        'url new tab' => fn () => Action::make('open')->label('Open')->url('/x', true),
        'disabled' => fn () => Action::make('edit')->label('Edit')->disabled(),
        'closure disabled' => fn () => Action::make('edit')->label('Edit')->disabled(fn ($r) => $r->getKey() % 2 === 0),
        'confirmation' => fn () => Action::make('del')->label('Delete')->requiresConfirmation(),
        'shortcut' => fn () => Action::make('edit')->label('Edit')->keyboardShortcut('e'),
        'extra attributes' => fn () => Action::make('edit')->label('Edit')->extraAttributes(['data-x' => 'y "z" &']),
        'closure extra attrs' => fn () => Action::make('edit')->label('Edit')
            ->extraAttributes(fn ($r) => ['data-row' => (string) $r->getKey()]),
        'hidden' => fn () => Action::make('edit')->label('Edit')->visible(false),
        'closure hidden' => fn () => Action::make('edit')->label('Edit')->visible(fn ($r) => $r->getKey() !== 2),
    ];
}

/** Keys chosen to break naive escaping if the slot's encoding were wrong. */
function abKeys(): array
{
    return [1, 2, 42, "a'b", 'a"b', 'a&b', 'a<x>b', 'ěščřž', 'a\\b', '0'];
}

// ─── The guard ───────────────────────────────────────────────────────────────

it('renders byte-identically to the view it replaces, across every shape', function () {
    $mismatches = [];

    foreach (abShapes() as $name => $make) {
        foreach (abKeys() as $key) {
            $record = abRecord($key);

            $skeletoned = $make()->render($record, abClick());
            $classic = abClassic($make(), $record, abClick());

            // The ONE deliberate difference: a compiled skeleton is trimmed, so the
            // button no longer carries the view file's own leading/trailing newline.
            // That is dead space between tags — one DOM text node per button — and
            // removing it is the point. Anything else is a bug.
            if ($skeletoned !== trim($classic)) {
                $mismatches[] = $name.' / key '.var_export($key, true);
            }
        }
    }

    expect($mismatches)->toBe([]);
});

it('renders byte-identically with the default resolver and with no record', function () {
    foreach (abShapes() as $name => $make) {
        expect($make()->render(null))->toBe(trim(abClassic($make(), null)), $name.' (no record)');
        expect($make()->render(abRecord(7)))
            ->toBe(trim(abClassic($make(), abRecord(7))), $name.' (default resolver)');
    }
});

it('leaves no slot sentinel in the output', function () {
    foreach (abShapes() as $make) {
        expect($make()->render(abRecord(3), abClick()))->not->toContain('WIRE_SLOT');
    }
});

// ─── The point of it ─────────────────────────────────────────────────────────

/** Renders $rows records through one action and returns the view renders it cost. */
function abRenderCost(Action $action, int $rows): int
{
    $count = 0;
    View::composer('*', function () use (&$count): void {
        $count++;
    });

    $click = abClick();

    foreach (range(1, $rows) as $id) {
        $action->render(abRecord($id), $click);
    }

    return $count;
}

it('costs nothing per row once a shape has been compiled', function () {
    // Warm-up first: the canonical spinner partial resolves once per request, and
    // whichever measurement ran first would otherwise carry it.
    abRenderCost(Action::make('warm')->label('Warm'), 1);

    $five = abRenderCost(Action::make('edit')->label('Edit'), 5);
    $twenty = abRenderCost(Action::make('edit')->label('Edit'), 20);

    // One shape, so both cost the button view plus the content partial it includes —
    // twice in total, for five rows or for twenty.
    expect($five)->toBe(2)
        ->and($twenty)->toBe(2);
});

it('gives a per-record shape its own skeleton rather than the wrong markup', function () {
    abRenderCost(Action::make('warm')->label('Warm'), 1);

    // `disabled` flips on the record, so twenty rows are TWO shapes — and still only
    // two: the first odd row compiles one, the first even row the other, and the
    // remaining eighteen splice into them.
    $count = abRenderCost(
        Action::make('edit')->label('Edit')->disabled(fn ($r) => $r->getKey() % 2 === 0),
        20,
    );

    $action = Action::make('edit')->label('Edit')->disabled(fn ($r) => $r->getKey() % 2 === 0);
    $click = abClick();

    $rendered = [];
    foreach (range(1, 20) as $id) {
        $rendered[$id] = $action->render(abRecord($id), $click);
    }

    expect($count)->toBe(4)
        ->and($rendered[2])->toContain('disabled')
        ->and($rendered[3])->not->toContain('disabled');

    // And each row still carries its OWN click target, which is the whole splice.
    foreach ([1, 2, 19, 20] as $id) {
        expect($rendered[$id])->toContain("openActionModal(&#039;{$id}&#039;,&#039;edit&#039;)");
    }
});
