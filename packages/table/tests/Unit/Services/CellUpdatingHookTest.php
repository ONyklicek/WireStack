<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use NyonCode\WireCore\Core\Plugin\Hooks\CellUpdatingPayload;
use NyonCode\WireCore\Core\Plugin\PluginManager;
use NyonCode\WireCore\Foundation\Enums\Hook;
use NyonCode\WireTable\Columns\TextInputColumn;
use NyonCode\WireTable\Services\CellEditPipeline;

/*
 * An inline cell edit is a save, and it was the only write path in the table
 * with no way to change it.
 *
 * `CellUpdating` and `CellUpdated` are Laravel events, so by the line this
 * repository draws between the mechanisms they may watch a value change and
 * never alter it — a form save had `form.saving` and the cell beside it had
 * neither. What this file pins is the ordering that keeps the new seam honest:
 * it runs after the column's own checks, so a callback narrows and cannot widen.
 */

class CuhUser extends Model
{
    protected $table = 'cuh_users';

    protected $guarded = [];

    public $timestamps = false;
}

function cuhCommit(mixed $state = 'Edited'): object
{
    return app(CellEditPipeline::class)->commit(
        TextInputColumn::make('name'),
        'name',
        CuhUser::find(1),
        $state,
        null,
    );
}

beforeEach(function () {
    Schema::create('cuh_users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
    });

    CuhUser::create(['id' => 1, 'name' => 'Carol']);
});

afterEach(fn () => Schema::dropIfExists('cuh_users'));

it('lets a hook change the value on its way into the record', function () {
    app(PluginManager::class)->hook(
        Hook::CellUpdating,
        function (CellUpdatingPayload $payload): CellUpdatingPayload {
            $payload->value = strtoupper((string) $payload->value);

            return $payload;
        },
    );

    $outcome = cuhCommit();

    expect($outcome->success)->toBeTrue()
        ->and(CuhUser::find(1)->name)->toBe('EDITED');
});

it('lets a hook refuse the write outright', function () {
    // The half that makes this a hook rather than a second event: a refusal
    // reaches the browser as the cell's own error message and nothing is written.
    app(PluginManager::class)->hook(
        Hook::CellUpdating,
        function (CellUpdatingPayload $payload): CellUpdatingPayload {
            $payload->refusal = 'Locked while the invoice is approved.';

            return $payload;
        },
    );

    $outcome = cuhCommit();

    expect($outcome->success)->toBeFalse()
        ->and($outcome->message)->toBe('Locked while the invoice is approved.')
        ->and(CuhUser::find(1)->name)->toBe('Carol');
});

it('hands the hook the dehydrated value and what the record holds now', function () {
    $seen = null;

    app(PluginManager::class)->hook(
        Hook::CellUpdating,
        function (CellUpdatingPayload $payload) use (&$seen): CellUpdatingPayload {
            $seen = [$payload->columnName, $payload->value, $payload->oldValue];

            return $payload;
        },
    );

    cuhCommit('Edited');

    expect($seen)->toBe(['name', 'Edited', 'Carol']);
});

it('runs after the column checks, so a hook cannot write past a refusal', function () {
    // A column that refuses the edit never reaches the hook at all — the
    // permission check returns before it, which is what makes "narrow, never
    // widen" true rather than merely intended.
    $ran = false;

    app(PluginManager::class)->hook(
        Hook::CellUpdating,
        function (CellUpdatingPayload $payload) use (&$ran): CellUpdatingPayload {
            $ran = true;

            return $payload;
        },
    );

    $outcome = app(CellEditPipeline::class)->commit(
        TextInputColumn::make('name')->disabled(true),
        'name',
        CuhUser::find(1),
        'Edited',
        null,
    );

    expect($outcome->success)->toBeFalse()
        ->and($ran)->toBeFalse()
        ->and(CuhUser::find(1)->name)->toBe('Carol');
});

it('scopes a cell hook by the record model', function () {
    app(PluginManager::class)->hook(
        Hook::CellUpdating,
        function (CellUpdatingPayload $payload): CellUpdatingPayload {
            $payload->value = 'SCOPED';

            return $payload;
        },
        for: CuhUser::class,
    );

    cuhCommit();

    expect(CuhUser::find(1)->name)->toBe('SCOPED');
});

it('leaves a cell a scoped hook does not name alone', function () {
    app(PluginManager::class)->hook(
        Hook::CellUpdating,
        function (CellUpdatingPayload $payload): CellUpdatingPayload {
            $payload->value = 'SCOPED';

            return $payload;
        },
        for: 'some-other-model',
    );

    cuhCommit();

    expect(CuhUser::find(1)->name)->toBe('Edited');
});
