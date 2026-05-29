<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Gate;
use NyonCode\WireTable\Table;

class TableAuthorizationRecord extends Model
{
    protected $guarded = [];
}

function actAsTableAuthorizationUser(): void
{
    $user = new class extends Authenticatable
    {
        protected $guarded = [];
    };

    auth()->guard('web')->setUser($user);
}

// ─── Policy defaults ──────────────────────────────────────────────────────

it('does not use policy by default', function () {
    $table = Table::make();

    expect($table->usesPolicy())->toBeFalse();
});

it('can enable policy authorization', function () {
    $table = Table::make()->authorize();

    expect($table->usesPolicy())->toBeTrue();
});

it('can disable policy authorization', function () {
    $table = Table::make()->authorize()->authorize(false);

    expect($table->usesPolicy())->toBeFalse();
});

// ─── canCreate ────────────────────────────────────────────────────────────

it('allows create by default without policy', function () {
    $table = Table::make();

    expect($table->canCreate())->toBeTrue();
});

it('can override create authorization with bool', function () {
    $table = Table::make()->authorizeCreate(false);

    expect($table->canCreate())->toBeFalse();
});

it('can override create authorization with closure', function () {
    $table = Table::make()->authorizeCreate(fn () => false);

    expect($table->canCreate())->toBeFalse();
});

// ─── canUpdate ────────────────────────────────────────────────────────────

it('allows update by default without policy', function () {
    $table = Table::make();
    $record = new class extends Model {};

    expect($table->canUpdate($record))->toBeTrue();
});

it('can override update authorization with bool', function () {
    $table = Table::make()->authorizeUpdate(false);
    $record = new class extends Model {};

    expect($table->canUpdate($record))->toBeFalse();
});

it('can override update authorization with closure', function () {
    $table = Table::make()->authorizeUpdate(fn ($record) => false);
    $record = new class extends Model {};

    expect($table->canUpdate($record))->toBeFalse();
});

// ─── canDelete ────────────────────────────────────────────────────────────

it('allows delete by default without policy', function () {
    $table = Table::make();
    $record = new class extends Model {};

    expect($table->canDelete($record))->toBeTrue();
});

it('can override delete authorization with bool', function () {
    $table = Table::make()->authorizeDelete(false);
    $record = new class extends Model {};

    expect($table->canDelete($record))->toBeFalse();
});

// ─── canView ──────────────────────────────────────────────────────────────

it('allows view by default without policy', function () {
    $table = Table::make();
    $record = new class extends Model {};

    expect($table->canView($record))->toBeTrue();
});

it('can override view authorization with bool', function () {
    $table = Table::make()->authorizeView(false);
    $record = new class extends Model {};

    expect($table->canView($record))->toBeFalse();
});

// ─── Policy gates ─────────────────────────────────────────────────────────

it('uses create policy when policy authorization is enabled', function () {
    actAsTableAuthorizationUser();
    Gate::define('create', fn ($user, string $modelClass): bool => $modelClass === TableAuthorizationRecord::class);

    $table = Table::make()
        ->model(TableAuthorizationRecord::class)
        ->authorize();

    expect($table->canCreate())->toBeTrue();
});

it('denies create when create policy denies', function () {
    actAsTableAuthorizationUser();
    Gate::define('create', fn (): bool => false);

    $table = Table::make()
        ->model(TableAuthorizationRecord::class)
        ->authorize();

    expect($table->canCreate())->toBeFalse();
});

it('uses update policy for records', function () {
    actAsTableAuthorizationUser();

    $record = new TableAuthorizationRecord;
    $record->exists = true;

    Gate::define('update', fn ($user, TableAuthorizationRecord $model): bool => $model === $record);

    expect(Table::make()->authorize()->canUpdate($record))->toBeTrue();
});

it('denies update when update policy denies', function () {
    actAsTableAuthorizationUser();
    Gate::define('update', fn (): bool => false);

    $record = new TableAuthorizationRecord;
    $record->exists = true;

    expect(Table::make()->authorize()->canUpdate($record))->toBeFalse();
});

it('uses delete policy for records', function () {
    actAsTableAuthorizationUser();
    Gate::define('delete', fn (): bool => true);

    $record = new TableAuthorizationRecord;
    $record->exists = true;

    expect(Table::make()->authorize()->canDelete($record))->toBeTrue();
});

it('denies delete when delete policy denies', function () {
    actAsTableAuthorizationUser();
    Gate::define('delete', fn (): bool => false);

    $record = new TableAuthorizationRecord;
    $record->exists = true;

    expect(Table::make()->authorize()->canDelete($record))->toBeFalse();
});

it('uses view policy for records', function () {
    actAsTableAuthorizationUser();
    Gate::define('view', fn (): bool => true);

    $record = new TableAuthorizationRecord;
    $record->exists = true;

    expect(Table::make()->authorize()->canView($record))->toBeTrue();
});

it('denies view when view policy denies', function () {
    actAsTableAuthorizationUser();
    Gate::define('view', fn (): bool => false);

    $record = new TableAuthorizationRecord;
    $record->exists = true;

    expect(Table::make()->authorize()->canView($record))->toBeFalse();
});
