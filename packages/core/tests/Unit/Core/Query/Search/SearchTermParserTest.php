<?php

declare(strict_types=1);

use NyonCode\WireCore\Core\Query\Search\SearchConfig;
use NyonCode\WireCore\Core\Query\Search\SearchOperator;
use NyonCode\WireCore\Core\Query\Search\SearchTermParser;

beforeEach(function () {
    $this->parser = new SearchTermParser;
});

// ── The default: nothing is interpreted ─────────────────────

it('keeps an unconfigured term whole', function () {
    $term = $this->parser->parse('Ada Lovelace');

    expect($term->tokens)->toHaveCount(1)
        ->and($term->tokens[0]->value)->toBe('Ada Lovelace')
        ->and($term->tokens[0]->operator)->toBe(SearchOperator::Contains);
});

it('does not read operators out of an unconfigured term', function () {
    $term = $this->parser->parse('>100');

    expect($term->tokens)->toHaveCount(1)
        ->and($term->tokens[0]->operator)->toBe(SearchOperator::Contains)
        ->and($term->tokens[0]->value)->toBe('>100');
});

it('treats a blank term as empty', function (string $raw) {
    expect($this->parser->parse($raw)->isEmpty())->toBeTrue();
})->with(['', '   ', "\t\n"]);

it('keeps the term "0", which is a perfectly good search', function () {
    $term = $this->parser->parse('0');

    expect($term->isEmpty())->toBeFalse()
        ->and($term->tokens[0]->value)->toBe('0');
});

// ── Tokenization ────────────────────────────────────────────

it('splits on spaces when tokenizing', function () {
    $term = $this->parser->parse('Ada Lovelace', SearchConfig::make()->tokenize());

    expect($term->tokens)->toHaveCount(2)
        ->and($term->tokens[0]->value)->toBe('Ada')
        ->and($term->tokens[1]->value)->toBe('Lovelace');
});

it('collapses runs of whitespace', function () {
    $term = $this->parser->parse("  Ada   \t Lovelace  ", SearchConfig::make()->tokenize());

    expect($term->tokens)->toHaveCount(2);
});

it('keeps a quoted phrase together', function () {
    $term = $this->parser->parse('"Ada Lovelace" london', SearchConfig::make()->tokenize());

    expect($term->tokens)->toHaveCount(2)
        ->and($term->tokens[0]->value)->toBe('Ada Lovelace')
        ->and($term->tokens[0]->isPhrase)->toBeTrue()
        ->and($term->tokens[1]->value)->toBe('london');
});

it('never reads an operator out of a quoted phrase', function () {
    $config = SearchConfig::make()->tokenize()->ranges();
    $term = $this->parser->parse('">100"', $config);

    expect($term->tokens[0]->operator)->toBe(SearchOperator::Contains)
        ->and($term->tokens[0]->value)->toBe('>100');
});

it('drops an empty phrase', function () {
    $term = $this->parser->parse('"" ada', SearchConfig::make()->tokenize());

    expect($term->tokens)->toHaveCount(1)
        ->and($term->tokens[0]->value)->toBe('ada');
});

// ── Comparisons ─────────────────────────────────────────────

it('reads a comparison operator', function (string $raw, SearchOperator $operator, string $value) {
    $term = $this->parser->parse($raw, SearchConfig::make()->ranges());

    expect($term->tokens[0]->operator)->toBe($operator)
        ->and($term->tokens[0]->value)->toBe($value);
})->with([
    ['>100', SearchOperator::GreaterThan, '100'],
    ['>=100', SearchOperator::GreaterThanOrEqual, '100'],
    ['<10', SearchOperator::LessThan, '10'],
    ['<=10', SearchOperator::LessThanOrEqual, '10'],
    ['=42', SearchOperator::Equals, '42'],
    ['> 100', SearchOperator::GreaterThan, '100'],
    ['>-5', SearchOperator::GreaterThan, '-5'],
    ['>=2026-01-31', SearchOperator::GreaterThanOrEqual, '2026-01-31'],
]);

it('leaves a comparison against text as literal text', function (string $raw) {
    // `>foo` would compare lexically, which is never what was meant.
    $term = $this->parser->parse($raw, SearchConfig::make()->ranges());

    expect($term->tokens[0]->operator)->toBe(SearchOperator::Contains)
        ->and($term->tokens[0]->value)->toBe($raw);
})->with(['>foo', '<name', '=abc', '>']);

// ── Ranges ──────────────────────────────────────────────────

it('reads a two-sided range', function () {
    $term = $this->parser->parse('10..20', SearchConfig::make()->ranges());

    expect($term->tokens[0]->operator)->toBe(SearchOperator::Between)
        ->and($term->tokens[0]->value)->toBe('10')
        ->and($term->tokens[0]->upper)->toBe('20');
});

it('reads an open-ended range', function (string $raw, SearchOperator $operator, string $value) {
    $term = $this->parser->parse($raw, SearchConfig::make()->ranges());

    expect($term->tokens[0]->operator)->toBe($operator)
        ->and($term->tokens[0]->value)->toBe($value);
})->with([
    ['10..', SearchOperator::GreaterThanOrEqual, '10'],
    ['..20', SearchOperator::LessThanOrEqual, '20'],
    ['2026-01-01..', SearchOperator::GreaterThanOrEqual, '2026-01-01'],
    ['..2026-03-31', SearchOperator::LessThanOrEqual, '2026-03-31'],
]);

it('reads a date range', function () {
    $term = $this->parser->parse('2026-01-01..2026-03-31', SearchConfig::make()->ranges());

    expect($term->tokens[0]->operator)->toBe(SearchOperator::Between)
        ->and($term->tokens[0]->value)->toBe('2026-01-01')
        ->and($term->tokens[0]->upper)->toBe('2026-03-31');
});

it('reads a day-first date range', function () {
    $term = $this->parser->parse('01.01.2026..31.03.2026', SearchConfig::make()->ranges());

    expect($term->tokens[0]->operator)->toBe(SearchOperator::Between)
        ->and($term->tokens[0]->value)->toBe('01.01.2026')
        ->and($term->tokens[0]->upper)->toBe('31.03.2026');
});

it('leaves text that merely contains two dots alone', function (string $raw) {
    $term = $this->parser->parse($raw, SearchConfig::make()->ranges());

    expect($term->tokens[0]->operator)->toBe(SearchOperator::Contains)
        ->and($term->tokens[0]->value)->toBe($raw);
})->with(['foo..bar', 'a..', '..b', 'www..com']);

it('does not split a decimal number', function () {
    $term = $this->parser->parse('>1.5', SearchConfig::make()->ranges());

    expect($term->tokens[0]->operator)->toBe(SearchOperator::GreaterThan)
        ->and($term->tokens[0]->value)->toBe('1.5');
});

// ── Combined ────────────────────────────────────────────────

it('mixes text and comparison tokens', function () {
    $config = SearchConfig::make()->tokenize()->ranges();
    $term = $this->parser->parse('praha >1000', $config);

    expect($term->tokens)->toHaveCount(2)
        ->and($term->tokens[0]->operator)->toBe(SearchOperator::Contains)
        ->and($term->tokens[0]->value)->toBe('praha')
        ->and($term->tokens[1]->operator)->toBe(SearchOperator::GreaterThan)
        ->and($term->tokens[1]->value)->toBe('1000');
});

it('builds each token its own pattern', function () {
    $term = $this->parser->parse('ada 50%', SearchConfig::make()->tokenize());

    expect($term->tokens[0]->pattern)->toBe('%ada%')
        ->and($term->tokens[1]->pattern)->toBe('%50!%%');
});

it('honours the wildcard option in the pattern', function () {
    $term = $this->parser->parse('nov*', SearchConfig::make()->wildcards());

    expect($term->tokens[0]->pattern)->toBe('%nov%%');
});

it('keeps the raw term for a comparison token, for the text fallback', function () {
    $term = $this->parser->parse('>100', SearchConfig::make()->ranges());
    $token = $term->tokens[0];

    expect($token->raw)->toBe('>100')
        ->and($token->pattern)->toBe('%>100%')
        ->and($token->asText()->operator)->toBe(SearchOperator::Contains)
        ->and($token->asText()->value)->toBe('>100')
        ->and($token->searchText())->toBe('>100');
});

it('reports the raw term it parsed', function () {
    expect($this->parser->parse('  ada  ')->raw)->toBe('ada');
});

it('literal() switches every capability back off', function () {
    $config = SearchConfig::make()->tokenize()->ranges()->wildcards()->literal();

    expect($config->tokenizes())->toBeFalse()
        ->and($config->parsesRanges())->toBeFalse()
        ->and($config->allowsWildcards())->toBeFalse();
});

// ── The series a range was typed inside ─────────────────────
//
// `8866 01..08` is one thought that whitespace splitting cuts in two. The
// range carries the preceding word so a column holding such a code can put it
// back together; every other column ignores it.

it('carries the preceding word on a range token', function () {
    $config = SearchConfig::make()->tokenize()->ranges();
    $term = $this->parser->parse('8866 01..08', $config);

    expect($term->tokens)->toHaveCount(2)
        ->and($term->tokens[1]->operator)->toBe(SearchOperator::Between)
        ->and($term->tokens[1]->prefix)->toBe('8866')
        ->and($term->tokens[1]->qualify('01'))->toBe('8866 01')
        ->and($term->tokens[1]->qualify('08'))->toBe('8866 08');
});

it('carries only the word directly before the range', function () {
    $config = SearchConfig::make()->tokenize()->ranges();
    $term = $this->parser->parse('faktura 8866 01..08', $config);

    expect($term->tokens[2]->prefix)->toBe('8866');
});

it('carries the preceding word on a one-sided comparison too', function () {
    $config = SearchConfig::make()->tokenize()->ranges();
    $term = $this->parser->parse('8866 >=05', $config);

    expect($term->tokens[1]->prefix)->toBe('8866')
        ->and($term->tokens[1]->qualify('05'))->toBe('8866 05');
});

it('does not carry a quoted phrase as a series', function () {
    // Quoting says "this is literal text", not "this names a series".
    $config = SearchConfig::make()->tokenize()->ranges();
    $term = $this->parser->parse('"8866" 01..08', $config);

    expect($term->tokens[1]->prefix)->toBeNull();
});

it('does not carry another comparison as a series', function () {
    $config = SearchConfig::make()->tokenize()->ranges();
    $term = $this->parser->parse('>100 01..08', $config);

    expect($term->tokens[1]->prefix)->toBeNull();
});

it('leaves a range with nothing before it unqualified', function () {
    $config = SearchConfig::make()->tokenize()->ranges();
    $term = $this->parser->parse('01..08', $config);

    expect($term->tokens[0]->prefix)->toBeNull()
        ->and($term->tokens[0]->qualify('01'))->toBe('01');
});

it('leaves a plain text token unqualified', function () {
    $term = $this->parser->parse('8866 praha', SearchConfig::make()->tokenize());

    expect($term->tokens[1]->prefix)->toBeNull();
});
