<?php

declare(strict_types=1);

use NyonCode\WireBoost\Support\Docs\DocsCorpus;

beforeEach(function () {
    $this->dir = sys_get_temp_dir().'/wire-boost-corpus-'.uniqid();
    mkdir($this->dir.'/table', 0755, true);

    file_put_contents($this->dir.'/index.md', "# Index\n\nBody.");
    file_put_contents($this->dir.'/table/columns.md', "# Columns\n\nBody.");
    file_put_contents($this->dir.'/ignored.txt', 'not markdown');

    $this->corpus = new DocsCorpus(['docs' => $this->dir]);
});

afterEach(function () {
    array_map('unlink', glob($this->dir.'/table/*') ?: []);
    array_map('unlink', glob($this->dir.'/*.*') ?: []);
    @rmdir($this->dir.'/table');
    @rmdir($this->dir);
});

it('keys documents by a root-relative id', function () {
    expect(array_keys($this->corpus->documents()))
        ->toBe(['docs/index.md', 'docs/table/columns.md']);
});

it('indexes markdown and blade, not arbitrary files', function () {
    expect($this->corpus->documents())->not->toHaveKey('docs/ignored.txt');
});

it('skips a root that does not exist', function () {
    expect((new DocsCorpus(['docs' => '/does/not/exist']))->documents())->toBe([]);
});

it('reads a document by id', function () {
    expect($this->corpus->read('docs/table/columns.md'))->toContain('# Columns');
});

it('returns null for an unknown id', function () {
    expect($this->corpus->read('docs/nope.md'))->toBeNull()
        ->and($this->corpus->read('bogus-root/index.md'))->toBeNull()
        ->and($this->corpus->read('no-slash'))->toBeNull();
});

it('refuses an id that escapes its root', function () {
    // Ids arrive from the caller, so traversal must not read outside the corpus.
    expect($this->corpus->resolve('docs/../../../etc/hosts'))->toBeNull();
});

it('refuses a directory id', function () {
    expect($this->corpus->resolve('docs/table'))->toBeNull();
});

it('resolves a real id to an absolute path', function () {
    expect($this->corpus->resolve('docs/index.md'))->toBe(realpath($this->dir.'/index.md'));
});

it('infers the owning package from a docs subtree', function () {
    expect($this->corpus->packageFor('docs/table/columns/index.md'))->toBe('wire-table')
        ->and($this->corpus->packageFor('docs/forms/validation.md'))->toBe('wire-forms')
        ->and($this->corpus->packageFor('docs/core/actions.md'))->toBe('wire-core')
        ->and($this->corpus->packageFor('docs/sortable/overview.md'))->toBe('wire-sortable')
        ->and($this->corpus->packageFor('docs/boost/overview.md'))->toBe('wire-boost');
});

it('falls back to the umbrella package for cross-cutting docs', function () {
    expect($this->corpus->packageFor('docs/getting-started.md'))->toBe('wire')
        ->and($this->corpus->packageFor('guidelines/core.blade.php'))->toBe('wire');
});

it('infers the package of guidelines and skills from their filename', function () {
    expect($this->corpus->packageFor('guidelines/wire-table.blade.php'))->toBe('wire-table')
        ->and($this->corpus->packageFor('skills/wire-forms-development/SKILL.md'))->toBe('wire-forms');
});

it('accepts a package filter with or without the wire prefix', function () {
    expect($this->corpus->normalisePackage('table'))->toBe('wire-table')
        ->and($this->corpus->normalisePackage('wire-table'))->toBe('wire-table')
        ->and($this->corpus->normalisePackage(' TABLE '))->toBe('wire-table');
});

it('ships the bundled docs, guidelines and skills by default', function () {
    $roots = DocsCorpus::default()->roots();

    expect($roots)->toHaveKeys(['docs', 'guidelines', 'skills'])
        ->and(is_dir($roots['docs']))->toBeTrue()
        ->and(is_dir($roots['guidelines']))->toBeTrue();
});

it('appends configured project docs as their own root', function () {
    config()->set('wire-boost.docs.paths', [$this->dir]);

    expect(DocsCorpus::default()->roots())->toHaveKey(basename($this->dir));
});

it('ignores a blank configured path', function () {
    config()->set('wire-boost.docs.paths', ['', '   ']);

    expect(DocsCorpus::default()->roots())->toHaveCount(3);
});

it('disambiguates a configured root that collides with a bundled one', function () {
    $collision = sys_get_temp_dir().'/wire-boost-collide-'.uniqid().'/docs';
    mkdir($collision, 0755, true);

    config()->set('wire-boost.docs.paths', [$collision, $collision]);

    expect(array_keys(DocsCorpus::default()->roots()))
        ->toBe(['docs', 'guidelines', 'skills', 'docs-2', 'docs-3']);

    rmdir($collision);
    rmdir(dirname($collision));
});
