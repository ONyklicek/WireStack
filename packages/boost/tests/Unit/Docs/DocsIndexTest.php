<?php

declare(strict_types=1);

use NyonCode\WireBoost\Support\Docs\DocParser;
use NyonCode\WireBoost\Support\Docs\DocsCorpus;
use NyonCode\WireBoost\Support\Docs\DocsIndex;
use NyonCode\WireBoost\Support\Docs\Tokenizer;

beforeEach(function () {
    $this->dir = sys_get_temp_dir().'/wire-boost-index-'.uniqid();
    mkdir($this->dir.'/table', 0755, true);
    mkdir($this->dir.'/forms', 0755, true);

    file_put_contents($this->dir.'/table/badge.md', <<<'MD'
# BadgeColumn

Renders a status pill.

## Badge Colors

Set the pill colour with the color helper.

## Sub-Rows

Expandable child rows hang off a parent record.
MD);

    file_put_contents($this->dir.'/forms/validation.md', <<<'MD'
# Validation

Rules live on each field.

## Formatting

A note about format and performance.
MD);

    $this->index = fn (): DocsIndex => new DocsIndex(
        new DocsCorpus(['docs' => $this->dir]),
        new DocParser,
        new Tokenizer,
    );
});

afterEach(function () {
    foreach (['table', 'forms'] as $sub) {
        array_map('unlink', glob($this->dir.'/'.$sub.'/*') ?: []);
        @rmdir($this->dir.'/'.$sub);
    }
    @rmdir($this->dir);
});

it('indexes every section of every document', function () {
    expect(($this->index)()->sections())->toHaveCount(5);
});

it('returns nothing for a query with no usable terms', function () {
    expect(($this->index)()->search('   '))->toBe([]);
});

it('ranks the section that is about the query first', function () {
    $results = ($this->index)()->search('badge colors');

    expect($results[0]['heading'])->toBe('Badge Colors')
        ->and($results[0]['id'])->toBe('docs/table/badge.md#badge-colors');
});

it('surfaces a section on the strength of its heading alone', function () {
    // "Sub-Rows" appears only in the heading; the body never repeats it.
    $results = ($this->index)()->search('sub-rows');

    expect($results[0]['heading'])->toBe('Sub-Rows');
});

it('does not match a term inside a longer word', function () {
    // The old substr_count engine scored "form" against "format"/"performance".
    $results = ($this->index)()->search('form');

    expect(array_column($results, 'heading'))->not->toContain('Formatting');
});

it('returns a breadcrumb and package with every hit', function () {
    $results = ($this->index)()->search('badge colors');

    expect($results[0]['breadcrumb'])->toBe('BadgeColumn > Badge Colors')
        ->and($results[0]['package'])->toBe('wire-table')
        ->and($results[0]['document'])->toBe('docs/table/badge.md');
});

it('returns a snippet drawn from the matching prose', function () {
    expect(($this->index)()->search('badge colors')[0]['snippet'])->toContain('colour');
});

it('scores every hit above zero', function () {
    foreach (($this->index)()->search('validation rules') as $result) {
        expect($result['score'])->toBeGreaterThan(0);
    }
});

it('filters by package', function () {
    $results = ($this->index)()->search('field', 'wire-forms');

    expect($results)->not->toBeEmpty()
        ->and(array_unique(array_column($results, 'package')))->toBe(['wire-forms']);
});

it('accepts a package filter without the wire prefix', function () {
    expect(($this->index)()->search('badge', 'table'))->not->toBeEmpty();
});

it('treats a blank package filter as no filter', function () {
    expect(($this->index)()->search('badge', '  '))->not->toBeEmpty();
});

it('honours the result limit', function () {
    expect(($this->index)()->search('badge', null, 1))->toHaveCount(1);
});

it('always returns at least one result for a matching query', function () {
    expect(($this->index)()->search('badge', null, 0))->toHaveCount(1);
});

it('fetches a section by id', function () {
    $section = ($this->index)()->section('docs/table/badge.md#badge-colors');

    expect($section?->heading)->toBe('Badge Colors')
        ->and($section?->markdown())->toContain('Set the pill colour with the color helper.');
});

it('returns null for an unknown section id', function () {
    expect(($this->index)()->section('docs/table/badge.md#nope'))->toBeNull();
});

it('lists the sections of a document in order', function () {
    $sections = ($this->index)()->document('docs/table/badge.md');

    expect(array_map(fn ($s) => $s->heading, $sections))
        ->toBe(['BadgeColumn', 'Badge Colors', 'Sub-Rows']);
});

it('returns nothing for an unknown document', function () {
    expect(($this->index)()->document('docs/nope.md'))->toBe([]);
});

it('exposes its corpus', function () {
    expect(($this->index)()->corpus())->toBeInstanceOf(DocsCorpus::class);
});

it('builds a default index from the shipped corpus', function () {
    expect(DocsIndex::default()->corpus()->roots())->toHaveKey('docs');
});

it('finds real answers in the shipped corpus', function () {
    $index = DocsIndex::default();

    // Each of these must land on the page that actually documents it — the
    // point of bundling the real docs rather than the guideline summaries.
    expect($index->search('badge column color')[0]['document'])->toBe('docs/table/columns/badge.md')
        ->and($index->search('money formatting')[0]['document'])->toBe('docs/table/columns/text.md')
        ->and($index->search('relation manager')[0]['document'])->toBe('docs/table/relation-managers.md')
        ->and($index->search('wizard step validation')[0]['package'])->toBe('wire-core');
});

it('scopes a shipped-corpus search to one package', function () {
    $results = DocsIndex::default()->search('column', 'wire-table', 5);

    expect($results)->not->toBeEmpty()
        ->and(array_unique(array_column($results, 'package')))->toBe(['wire-table']);
});
