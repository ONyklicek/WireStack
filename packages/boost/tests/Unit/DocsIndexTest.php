<?php

declare(strict_types=1);

use NyonCode\WireBoost\Support\DocsIndex;

beforeEach(function () {
    $this->dir = sys_get_temp_dir().'/wire-boost-docs-'.uniqid();
    mkdir($this->dir, 0755, true);

    file_put_contents($this->dir.'/wire-table.md', "# Tables\n\nThe badge column renders a coloured badge for the column state.");
    file_put_contents($this->dir.'/wire-forms.md', "# Forms\n\nValidation rules live on each field.");
    file_put_contents($this->dir.'/skill.md', "---\nname: wire skill\n---\n\nWidget chart helpers.");
    file_put_contents($this->dir.'/plain.md', 'no leading heading here, just badge text');
});

afterEach(function () {
    array_map('unlink', glob($this->dir.'/*') ?: []);
    rmdir($this->dir);
});

it('exposes its configured paths', function () {
    expect((new DocsIndex([$this->dir]))->paths())->toBe([$this->dir]);
});

it('returns no documents when no directory exists', function () {
    expect((new DocsIndex(['/does/not/exist']))->documents())->toBe([]);
});

it('indexes markdown documents', function () {
    expect((new DocsIndex([$this->dir]))->documents())->toHaveCount(4);
});

it('ranks documents by term frequency', function () {
    $results = (new DocsIndex([$this->dir]))->search('badge column');

    expect($results)->not->toBeEmpty()
        ->and($results[0]['title'])->toBe('Tables')
        ->and($results[0]['score'])->toBeGreaterThan(0)
        ->and($results[0]['snippet'])->toContain('badge');
});

it('returns nothing for an empty query', function () {
    expect((new DocsIndex([$this->dir]))->search('  '))->toBe([]);
});

it('skips documents that do not match', function () {
    $results = (new DocsIndex([$this->dir]))->search('validation');

    expect(array_column($results, 'title'))->toBe(['Forms']);
});

it('filters by package path', function () {
    $results = (new DocsIndex([$this->dir]))->search('badge', 'wire-table');

    expect($results)->toHaveCount(1)
        ->and($results[0]['path'])->toContain('wire-table');
});

it('honours the result limit', function () {
    $results = (new DocsIndex([$this->dir]))->search('badge', null, 1);

    expect($results)->toHaveCount(1);
});

it('derives titles from frontmatter and filename', function () {
    $results = (new DocsIndex([$this->dir]))->search('widget chart');
    expect($results[0]['title'])->toBe('wire skill');

    $plain = (new DocsIndex([$this->dir]))->search('leading heading');
    expect($plain[0]['title'])->toBe('plain.md');
});

it('builds a default index from the shipped corpus', function () {
    $paths = DocsIndex::default()->paths();

    expect($paths)->not->toBeEmpty()
        ->and(collect($paths)->contains(fn (string $p): bool => str_ends_with($p, 'guidelines')))->toBeTrue();
});

it('surfaces reactive and field-action recipes from the shipped corpus', function () {
    $index = DocsIndex::default();

    expect($index->search('afterStateUpdated'))->not->toBeEmpty()
        ->and($index->search('suffixAction'))->not->toBeEmpty()
        ->and($index->search('modalFooterActions'))->not->toBeEmpty()
        ->and($index->search('Button field action'))->not->toBeEmpty();
});
