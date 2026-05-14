<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Builder;
use NyonCode\WireTable\Filters\Filter;

// ─── Factory ────────────────────────────────────────────────────────────────

it('can be created via make()', function () {
    $filter = Filter::make('status');

    expect($filter)->toBeInstanceOf(Filter::class)
        ->and($filter->getName())->toBe('status');
});

// ─── Label ──────────────────────────────────────────────────────────────────

it('generates label from name', function () {
    expect(Filter::make('created_at')->getLabel())->toBe('Created At');
});

it('can set custom label', function () {
    expect(Filter::make('status')->label('Stav')->getLabel())->toBe('Stav');
});

// ─── Column ─────────────────────────────────────────────────────────────────

it('uses name as column by default', function () {
    expect(Filter::make('status')->getColumn())->toBe('status');
});

it('can set explicit column', function () {
    expect(Filter::make('status')->column('user_status')->getColumn())->toBe('user_status');
});

// ─── Relation ───────────────────────────────────────────────────────────────

it('parses relation from dot notation', function () {
    $filter = Filter::make('category.name');

    expect($filter->getRelation())->toBe('category')
        ->and($filter->getRelationshipAttribute())->toBe('name');
});

it('parses nested relation', function () {
    $filter = Filter::make('author.profile.country');

    expect($filter->getRelation())->toBe('author.profile')
        ->and($filter->getRelationshipAttribute())->toBe('country');
});

it('has no relation for simple names', function () {
    expect(Filter::make('status')->getRelation())->toBeNull()
        ->and(Filter::make('status')->getRelationshipAttribute())->toBeNull();
});

// ─── Default ────────────────────────────────────────────────────────────────

it('has null default', function () {
    expect(Filter::make('status')->getDefault())->toBeNull();
});

it('can set default value', function () {
    expect(Filter::make('status')->default('active')->getDefault())->toBe('active');
});

// ─── Placeholder ────────────────────────────────────────────────────────────

it('has default placeholder from translation', function () {
    expect(Filter::make('status')->getPlaceholder())->toBe('select_placeholder');
});

it('can set custom placeholder', function () {
    expect(Filter::make('status')->placeholder('Choose...')->getPlaceholder())->toBe('Choose...');
});

// ─── Multiple ───────────────────────────────────────────────────────────────

it('is not multiple by default', function () {
    expect(Filter::make('status')->isMultiple())->toBeFalse();
});

it('can be set to multiple', function () {
    expect(Filter::make('tags')->multiple()->isMultiple())->toBeTrue();
});

// ─── Hidden ─────────────────────────────────────────────────────────────────

it('is not hidden by default', function () {
    expect(Filter::make('status')->isHidden())->toBeFalse();
});

it('can be hidden', function () {
    expect(Filter::make('status')->hidden()->isHidden())->toBeTrue();
});

it('can be hidden via closure', function () {
    $filter = Filter::make('admin_only')
        ->hidden(fn () => true);

    expect($filter->isHidden())->toBeTrue();
});

it('visible is inverse of hidden', function () {
    $filter = Filter::make('status')->visible(false);

    expect($filter->isHidden())->toBeTrue();
});

// ─── Permission ─────────────────────────────────────────────────────────────

it('has no permission by default', function () {
    expect(Filter::make('status')->getPermission())->toBeNull();
});

it('can set permission', function () {
    expect(Filter::make('status')->permission('manage_users')->getPermission())->toBe('manage_users');
});

// ─── Query Callback ─────────────────────────────────────────────────────────

it('has no query callback by default', function () {
    expect(Filter::make('status')->getQueryCallback())->toBeNull();
});

it('can set query callback', function () {
    $callback = fn (Builder $query, $value) => $query->where('status', $value);
    $filter = Filter::make('status')->query($callback);

    expect($filter->getQueryCallback())->toBe($callback);
});
