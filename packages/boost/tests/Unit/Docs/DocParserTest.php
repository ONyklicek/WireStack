<?php

declare(strict_types=1);

use NyonCode\WireBoost\Support\Docs\DocParser;
use NyonCode\WireBoost\Support\Docs\DocSection;

/**
 * @return array<int, DocSection>
 */
function parseDoc(string $markdown, string $document = 'docs/table/x.md'): array
{
    return (new DocParser)->parse($document, $markdown, 'wire-table');
}

it('splits a document into one section per heading', function () {
    $sections = parseDoc("# Columns\n\nIntro.\n\n## Sorting\n\nSort body.\n\n## Filtering\n\nFilter body.");

    expect(array_column(array_map(fn ($s) => $s->toArray(), $sections), 'heading'))
        ->toBe(['Columns', 'Sorting', 'Filtering']);
});

it('strips frontmatter without swallowing the body', function () {
    $sections = parseDoc("---\norder: 20\n---\n\n# Columns\n\nIntro body.");

    expect($sections[0]->content)->toBe('Intro body.')
        ->and($sections[0]->content)->not->toContain('order');
});

it('takes the title from the first h1', function () {
    expect(parseDoc("# Columns\n\nBody.")[0]->title)->toBe('Columns');
});

it('falls back to the filename when there is no h1', function () {
    expect(parseDoc('Just prose.', 'docs/table/loose.md')[0]->title)->toBe('loose.md');
});

it('ignores headings inside backtick fences', function () {
    $markdown = <<<'MD'
# Install

Intro.

```bash
# Install the package
composer require nyoncode/wire-table
```

## Real
MD;

    expect(array_map(fn ($s) => $s->heading, parseDoc($markdown)))->toBe(['Install', 'Real']);
});

it('ignores headings inside tilde fences', function () {
    $markdown = "# Doc\n\n~~~php\n# not a heading\n### also not\n~~~\n\n## Real";

    expect(array_map(fn ($s) => $s->heading, parseDoc($markdown)))->toBe(['Doc', 'Real']);
});

it('keeps the fenced sample in the section body', function () {
    $sections = parseDoc("# Doc\n\n```bash\n# Install\ncomposer require x\n```");

    expect($sections[0]->content)->toContain('composer require x')
        ->and($sections[0]->content)->toContain('# Install');
});

it('builds a breadcrumb trail from the heading hierarchy', function () {
    $sections = parseDoc("# Columns\n\nA.\n\n## Shared API\n\nB.\n\n### Identity\n\nC.");

    expect(end($sections)->path())->toBe('Columns > Shared API > Identity');
});

it('drops deeper headings when the hierarchy climbs back up', function () {
    $sections = parseDoc("# Doc\n\nA.\n\n## One\n\nB.\n\n### Deep\n\nC.\n\n## Two\n\nD.");

    expect(end($sections)->path())->toBe('Doc > Two');
});

it('slugifies headings into anchors', function () {
    $sections = parseDoc("# Doc\n\nA.\n\n## Factory & Identity\n\nB.");

    expect($sections[1]->anchor)->toBe('factory-identity')
        ->and($sections[1]->id)->toBe('docs/table/x.md#factory-identity');
});

it('disambiguates repeated headings', function () {
    $sections = parseDoc("# Doc\n\nA.\n\n## Usage\n\nB.\n\n## Usage\n\nC.");

    expect([$sections[1]->anchor, $sections[2]->anchor])->toBe(['usage', 'usage-1']);
});

it('gives a heading that slugifies to nothing a usable anchor', function () {
    $sections = parseDoc("# Doc\n\nA.\n\n## ✅\n\nB.");

    expect($sections[1]->anchor)->toBe('section');
});

it('strips inline markdown from heading text', function () {
    $sections = parseDoc("# Doc\n\nA.\n\n## The `->color()` [helper](x.md)\n\nB.");

    expect($sections[1]->heading)->toBe('The ->color() helper');
});

it('indexes a preamble that precedes any heading', function () {
    $sections = parseDoc("Loose intro prose.\n\n# Doc\n\nBody.");

    expect($sections[0]->heading)->toBe('')
        ->and($sections[0]->anchor)->toBe('')
        ->and($sections[0]->level)->toBe(0)
        ->and($sections[0]->content)->toBe('Loose intro prose.')
        ->and($sections[0]->id)->toBe('docs/table/x.md');
});

it('skips an empty preamble', function () {
    expect(parseDoc("# Doc\n\nBody.")[0]->heading)->toBe('Doc');
});

it('keeps a heading with no body of its own', function () {
    $sections = parseDoc("# Doc\n\n## Empty\n\n## Full\n\nBody.");

    expect(array_map(fn ($s) => $s->heading, $sections))->toBe(['Doc', 'Empty', 'Full']);
});

it('renders a section back as markdown', function () {
    $sections = parseDoc("# Doc\n\nA.\n\n## Sorting\n\nSort body.");

    expect($sections[1]->markdown())->toBe("## Sorting\n\nSort body.");
});

it('renders a preamble as its bare body', function () {
    expect(parseDoc("Loose prose.\n\n# Doc\n\nB.")[0]->markdown())->toBe('Loose prose.');
});

it('carries the owning package onto every section', function () {
    expect(parseDoc("# Doc\n\nA.\n\n## B\n\nC.")[1]->package)->toBe('wire-table');
});

it('returns nothing for an empty document', function () {
    expect(parseDoc(''))->toBe([]);
});
