<?php

declare(strict_types=1);

use NyonCode\WireCore\Core\Query\AliasGenerator;

beforeEach(function () {
    $this->generator = new AliasGenerator;
});

it('generates deterministic alias', function () {
    $alias = $this->generator->generate('users', ['company']);

    expect($alias)->toBe('users_company');
});

it('generates nested alias', function () {
    $alias = $this->generator->generate('users', ['company', 'address']);

    expect($alias)->toBe('users_company_address');
});

it('returns same alias for same path', function () {
    $first = $this->generator->generate('users', ['company']);
    $second = $this->generator->generate('users', ['company']);

    expect($first)->toBe($second);
});

it('generates different aliases for different paths', function () {
    $a = $this->generator->generate('users', ['company']);
    $b = $this->generator->generate('users', ['profile']);

    expect($a)->not->toBe($b);
});

it('truncates long aliases', function () {
    $alias = $this->generator->generate('very_long_table_name', [
        'some_really_long_relation',
        'another_very_long_relation_name',
    ]);

    expect(strlen($alias))->toBeLessThanOrEqual(60);
});

it('checks alias existence', function () {
    expect($this->generator->hasAlias('users', ['company']))->toBeFalse();

    $this->generator->generate('users', ['company']);

    expect($this->generator->hasAlias('users', ['company']))->toBeTrue();
});

it('resets all aliases', function () {
    $this->generator->generate('users', ['company']);
    $this->generator->reset();

    expect($this->generator->getAllAliases())->toBe([]);
});
