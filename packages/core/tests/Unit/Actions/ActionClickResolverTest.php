<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Actions\Action;
use NyonCode\WireCore\Actions\BaseAction;
use NyonCode\WireCore\Actions\Contracts\RendersAsButton;
use NyonCode\WireCore\Actions\Contracts\ResolvesActionClick;
use NyonCode\WireCore\Actions\Support\MountActionClickResolver;

function clickResolverRecord(int $id = 1): Model
{
    $record = new class extends Model
    {
        protected $guarded = [];
    };
    $record->forceFill(['id' => $id]);

    return $record;
}

// ─── MountActionClickResolver (default host) ─────────────────────────────────

it('resolves a mountAction click for the standalone default resolver', function () {
    $resolver = new MountActionClickResolver;

    expect($resolver->clickHandler(Action::make('edit'), null))->toBe("mountAction('edit')");
});

it('defaults an action render to mountAction when no resolver is supplied', function () {
    $data = Action::make('publish')->toButtonRenderArray(clickResolverRecord());

    expect($data['wireClick'])->toBe("mountAction('publish')")
        ->and($data['loadingTarget'])->toBe("mountAction('publish')");
});

// ─── Host-supplied resolver drives the click ─────────────────────────────────

it('lets a host resolver own the wire:click and loading target', function () {
    $resolver = new class implements ResolvesActionClick
    {
        public function clickHandler(BaseAction $action, ?Model $record): string
        {
            return "runIt('{$record?->getKey()}', '{$action->getName()}')";
        }
    };

    $data = Action::make('go')->toButtonRenderArray(clickResolverRecord(7), $resolver);

    expect($data['wireClick'])->toBe("runIt('7', 'go')")
        ->and($data['loadingTarget'])->toBe("runIt('7', 'go')");
});

it('implements the RendersAsButton contract', function () {
    expect(Action::make('x'))->toBeInstanceOf(RendersAsButton::class);
});

// ─── Record-invariant memoisation ────────────────────────────────────────────

it('reuses static icon + classes across records when no visual prop is a closure', function () {
    $action = Action::make('delete')->icon('trash')->color('danger');

    $a = $action->toButtonRenderArray(clickResolverRecord(1));
    $b = $action->toButtonRenderArray(clickResolverRecord(2));

    // Same resolved fragments — the memo path is exercised for the second record.
    expect($a['iconHtml'])->toBe($b['iconHtml'])
        ->and($a['classes'])->toBe($b['classes'])
        ->and($a['iconHtml'])->not->toBe('');
});

it('recomputes per record when a visual property is a per-record closure', function () {
    $action = Action::make('publish')
        ->icon(fn (Model $record) => $record->getKey() === 1 ? 'pencil' : 'check')
        ->color(fn (Model $record) => $record->getKey() === 1 ? 'primary' : 'success');

    $a = $action->toButtonRenderArray(clickResolverRecord(1));
    $b = $action->toButtonRenderArray(clickResolverRecord(2));

    expect($a['iconHtml'])->not->toBe($b['iconHtml'])
        ->and($a['classes'])->not->toBe($b['classes']);
});

// ─── Null record (standalone) path ───────────────────────────────────────────

it('builds render data without a record for a standalone action', function () {
    $data = Action::make('save')->toButtonRenderArray();

    expect($data['url'])->toBeNull()
        ->and($data['isButton'])->toBeTrue()
        ->and($data['disabled'])->toBeFalse()
        ->and($data['recordKey'])->toBeNull()
        ->and($data['wireClick'])->toBe("mountAction('save')");
});
