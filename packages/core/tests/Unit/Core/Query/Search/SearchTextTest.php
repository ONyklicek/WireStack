<?php

declare(strict_types=1);

use NyonCode\WireCore\Core\Query\Search\SearchConfig;
use NyonCode\WireCore\Core\Query\Search\SearchTerm;
use NyonCode\WireCore\Core\Query\Search\SearchTermParser;
use NyonCode\WireCore\Core\Query\Search\SearchText;

function searchTextTerm(string $term, ?SearchConfig $config = null): SearchTerm
{
    return (new SearchTermParser)->parse($term, $config);
}

it('folds text to lower-case ASCII', function (string $text, string $folded) {
    expect(SearchText::fold($text))->toBe($folded);
})->with([
    ['Novák', 'novak'],
    ['ČERNÝ', 'cerny'],
    ['Žluťoučký kůň', 'zlutoucky kun'],
    ['plain', 'plain'],
]);

it('finds a needle ignoring case and accents', function (string $haystack, string $needle, bool $found) {
    expect(SearchText::contains($haystack, $needle))->toBe($found);
})->with([
    ['Jan Novák', 'novak', true],
    ['Jan Novak', 'Novák', true],
    ['Jan Novák', 'dvorak', false],
]);

it('needs every word, each in any of the texts', function (array $texts, string $term, bool $matches) {
    expect(SearchText::matches($texts, searchTextTerm($term)))->toBe($matches);
})->with([
    'words across texts' => [['Jan Novák', 'Praha'], 'praha novak', true],
    'one word missing' => [['Jan Novák', 'Praha'], 'novak brno', false],
    'one text, both words' => [['Vydané faktury'], 'fakt vyd', true],
    'quoted phrase is one word' => [['Nová Ves'], '"ves nova"', false],
    'quoted phrase in order' => [['Nová Ves'], '"nova ves"', true],
]);

it('matches everything for an empty term', function () {
    expect(SearchText::matches(['anything'], searchTextTerm('  ')))->toBeTrue();
});

it('matches a comparison token as the text that was typed', function () {
    $term = searchTextTerm('>100', SearchConfig::make()->ranges());

    expect(SearchText::matches(['score >100 points'], $term))->toBeTrue()
        ->and(SearchText::matches(['score 150'], $term))->toBeFalse();
});
