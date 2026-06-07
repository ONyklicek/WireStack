<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Actions\Action;

// ─── Divider ────────────────────────────────────────────────────────────────

it('can create a divider action', function () {
    $divider = Action::divider();

    expect($divider->isDivider())->toBeTrue()
        ->and($divider->getName())->toBe('__divider__');
});

it('is not a divider by default', function () {
    expect(Action::make('edit')->isDivider())->toBeFalse();
});

// ─── URL ────────────────────────────────────────────────────────────────────

it('can set url as string', function () {
    $action = Action::make('view')->url('/users/1');

    $model = Mockery::mock(Model::class);

    expect($action->getUrl($model))->toBe('/users/1');
});

it('can set url as closure', function () {
    $action = Action::make('view')->url(fn ($record) => '/users/'.$record->getKey());

    $model = Mockery::mock(Model::class);
    $model->shouldReceive('getKey')->andReturn(42);

    expect($action->getUrl($model))->toBe('/users/42');
});

it('returns null url when not set', function () {
    $model = Mockery::mock(Model::class);

    expect(Action::make('test')->getUrl($model))->toBeNull();
});

it('can open url in new tab', function () {
    $action = Action::make('view')->url('/users/1', openInNewTab: true);

    expect($action->shouldOpenUrlInNewTab())->toBeTrue();
});

it('does not open url in new tab by default', function () {
    $action = Action::make('view')->url('/users/1');

    expect($action->shouldOpenUrlInNewTab())->toBeFalse();
});

// ─── Render Data ─────────────────────────────────────────────────────────────

it('builds solid button render data from canonical size and color resolvers', function () {
    $record = new class extends Model
    {
        protected $guarded = [];
    };
    $record->forceFill(['id' => 1]);

    $data = Action::make('approve')
        ->color('success')
        ->size('md')
        ->getRenderData($record);

    expect($data['classes'])->toContain('px-3 py-2 text-sm gap-2')
        ->and($data['classes'])->toContain('bg-emerald-600 text-white hover:bg-emerald-700 focus:ring-emerald-500 dark:bg-emerald-500 dark:hover:bg-emerald-600');
});

it('builds outlined button render data from canonical resolvers', function () {
    $record = new class extends Model
    {
        protected $guarded = [];
    };
    $record->forceFill(['id' => 1]);

    $data = Action::make('edit')
        ->outlined()
        ->color('primary')
        ->size('sm')
        ->getRenderData($record);

    expect($data['classes'])->toContain('px-2.5 py-1.5 text-sm gap-1.5')
        ->and($data['classes'])->toContain('border border-primary-600 text-primary-600 hover:bg-primary-50 focus:ring-primary-500 dark:border-primary-400 dark:text-primary-400 dark:hover:bg-primary-900/20');
});

it('builds icon button render data from canonical icon button resolver', function () {
    $record = new class extends Model
    {
        protected $guarded = [];
    };
    $record->forceFill(['id' => 1]);

    $data = Action::make('delete')
        ->icon('trash')
        ->iconButton()
        ->color('danger')
        ->size('lg')
        ->getRenderData($record);

    expect($data['classes'])->toContain('p-2.5')
        ->and($data['classes'])->toContain('text-red-600 hover:bg-red-50 focus:ring-red-500 dark:text-red-400 dark:hover:bg-red-900/20')
        ->and($data['iconHtml'])->toContain('w-5 h-5');
});

// ─── Icon Button ────────────────────────────────────────────────────────────

it('is not an icon button by default', function () {
    expect(Action::make('test')->isIconButton())->toBeFalse();
});

it('can be set as icon button', function () {
    expect(Action::make('test')->iconButton()->isIconButton())->toBeTrue();
});

// ─── Hide Label ─────────────────────────────────────────────────────────────

it('does not hide label by default', function () {
    expect(Action::make('test')->isHideLabel())->toBeFalse();
});

it('can hide label', function () {
    expect(Action::make('test')->hideLabel()->isHideLabel())->toBeTrue();
});

it('onlyIcon is an alias for hideLabel', function () {
    expect(Action::make('test')->onlyIcon()->isHideLabel())->toBeTrue();
});

it('deprecated hiddeLabel works', function () {
    expect(Action::make('test')->hiddeLabel()->isHideLabel())->toBeTrue();
});
