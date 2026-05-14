<?php

declare(strict_types=1);

use NyonCode\WireCore\Core\Query\JoinRegistry;

beforeEach(function () {
    $this->registry = new JoinRegistry;
});

it('registers a join and returns alias', function () {
    $alias = $this->registry->registerJoin(
        baseTable: 'users',
        relationPath: ['company'],
        joinTable: 'companies',
        firstColumn: 'users.company_id',
        operator: '=',
        secondColumn: 'users_company.id',
    );

    expect($alias)->toBe('users_company')
        ->and($this->registry->hasJoin($alias))->toBeTrue()
        ->and($this->registry->count())->toBe(1);
});

it('deduplicates same join', function () {
    $alias1 = $this->registry->registerJoin(
        baseTable: 'users',
        relationPath: ['company'],
        joinTable: 'companies',
        firstColumn: 'users.company_id',
        operator: '=',
        secondColumn: 'users_company.id',
    );

    $alias2 = $this->registry->registerJoin(
        baseTable: 'users',
        relationPath: ['company'],
        joinTable: 'companies',
        firstColumn: 'users.company_id',
        operator: '=',
        secondColumn: 'users_company.id',
    );

    expect($alias1)->toBe($alias2)
        ->and($this->registry->count())->toBe(1);
});

it('uses left join by default', function () {
    $alias = $this->registry->registerJoin(
        baseTable: 'users',
        relationPath: ['company'],
        joinTable: 'companies',
        firstColumn: 'users.company_id',
        operator: '=',
        secondColumn: 'users_company.id',
    );

    expect($this->registry->getJoin($alias)->type)->toBe('left');
});

it('supports inner join', function () {
    $alias = $this->registry->registerJoin(
        baseTable: 'users',
        relationPath: ['company'],
        joinTable: 'companies',
        firstColumn: 'users.company_id',
        operator: '=',
        secondColumn: 'users_company.id',
        type: 'inner',
    );

    expect($this->registry->getJoin($alias)->type)->toBe('inner');
});

it('returns null for missing join', function () {
    expect($this->registry->getJoin('nonexistent'))->toBeNull();
});

it('resets all joins', function () {
    $this->registry->registerJoin(
        baseTable: 'users',
        relationPath: ['company'],
        joinTable: 'companies',
        firstColumn: 'users.company_id',
        operator: '=',
        secondColumn: 'users_company.id',
    );

    $this->registry->reset();

    expect($this->registry->isEmpty())->toBeTrue()
        ->and($this->registry->count())->toBe(0);
});

it('returns all registered joins', function () {
    $this->registry->registerJoin(
        baseTable: 'users',
        relationPath: ['company'],
        joinTable: 'companies',
        firstColumn: 'users.company_id',
        operator: '=',
        secondColumn: 'users_company.id',
    );

    $this->registry->registerJoin(
        baseTable: 'users',
        relationPath: ['profile'],
        joinTable: 'profiles',
        firstColumn: 'users.id',
        operator: '=',
        secondColumn: 'users_profile.user_id',
    );

    $all = $this->registry->getAllJoins();

    expect($all)->toHaveCount(2)
        ->and(array_keys($all))->toBe(['users_company', 'users_profile']);
});
