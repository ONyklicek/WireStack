<?php

declare(strict_types=1);

use NyonCode\WireBoost\Support\Docs\Tokenizer;

beforeEach(function () {
    $this->tokenizer = new Tokenizer;
});

it('lowercases words and drops punctuation', function () {
    expect($this->tokenizer->tokenize('Badge, column: color!'))->toBe(['badge', 'column', 'color']);
});

it('keeps duplicates so callers can count term frequency', function () {
    expect($this->tokenizer->tokenize('badge badge'))->toBe(['badge', 'badge']);
});

it('deduplicates for queries', function () {
    expect($this->tokenizer->unique('badge badge column'))->toBe(['badge', 'column']);
});

it('emits identifiers whole and split on camel-case humps', function () {
    expect($this->tokenizer->tokenize('TextColumn'))->toBe(['textcolumn', 'text', 'column']);
});

it('splits acronym boundaries without shredding the acronym', function () {
    expect($this->tokenizer->tokenize('HTMLParser'))->toBe(['htmlparser', 'html', 'parser']);
});

it('leaves single-word identifiers alone', function () {
    expect($this->tokenizer->tokenize('column'))->toBe(['column']);
});

it('drops single characters that carry no signal', function () {
    expect($this->tokenizer->tokenize('a badge x'))->toBe(['badge']);
});

it('tokenizes a fluent call into searchable pieces', function () {
    expect($this->tokenizer->tokenize('->afterStateUpdated()'))
        ->toContain('afterstateupdated')
        ->toContain('after')
        ->toContain('state')
        ->toContain('updated');
});

it('finds no terms in an empty or symbol-only query', function () {
    expect($this->tokenizer->unique('   '))->toBe([])
        ->and($this->tokenizer->unique('-> ()'))->toBe([]);
});

it('does not match a term inside a longer word', function () {
    // The whole point of tokenizing: "form" must not hit "format".
    expect($this->tokenizer->tokenize('format'))->not->toContain('form');
});
