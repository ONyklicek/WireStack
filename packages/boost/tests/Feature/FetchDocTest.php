<?php

declare(strict_types=1);

use NyonCode\WireBoost\Mcp\Tools\FetchDoc;
use NyonCode\WireBoost\Mcp\WireBoostServer;
use NyonCode\WireBoost\Support\Docs\DocsIndex;

/**
 * A real id from the shipped corpus, so the test breaks if the docs move.
 */
function firstSectionId(string $query): string
{
    return DocsIndex::default()->search($query, null, 1)[0]['id'];
}

it('reads a section in full by the id search returned', function () {
    $id = firstSectionId('badge column color');

    WireBoostServer::tool(FetchDoc::class, ['id' => $id])
        ->assertOk()
        ->assertSee('BadgeColumn')
        ->assertSee('content');
});

it('lists the sibling sections alongside a fetched section', function () {
    WireBoostServer::tool(FetchDoc::class, ['id' => firstSectionId('badge column color')])
        ->assertOk()
        ->assertSee('siblings');
});

it('returns an outline for a document id', function () {
    // A whole page is tens of kilobytes; the outline plus targeted fetches is
    // the cheaper path, so it is the default.
    WireBoostServer::tool(FetchDoc::class, ['id' => 'docs/table/columns/badge.md'])
        ->assertOk()
        ->assertSee('sections')
        ->assertSee('Outline only');
});

it('returns a whole document when asked', function () {
    WireBoostServer::tool(FetchDoc::class, ['id' => 'docs/table/columns/badge.md', 'full' => true])
        ->assertOk()
        ->assertSee('content');
});

it('treats full=true on a section id as a request for its page', function () {
    WireBoostServer::tool(FetchDoc::class, ['id' => firstSectionId('badge column color'), 'full' => true])
        ->assertOk()
        ->assertSee('content');
});

it('reports an unknown section id as an error, with a way forward', function () {
    WireBoostServer::tool(FetchDoc::class, ['id' => 'docs/table/columns/badge.md#nope'])
        ->assertHasErrors()
        ->assertSee('Unknown documentation section')
        ->assertSee('search-wire-docs');
});

it('reports an unknown document id as an error, with a way forward', function () {
    WireBoostServer::tool(FetchDoc::class, ['id' => 'docs/nope.md'])
        ->assertHasErrors()
        ->assertSee('Unknown document')
        ->assertSee('search-wire-docs');
});

it('leaves a preamble out of the outline', function () {
    // A preamble has no heading, so there is nothing to address it by; only
    // headed sections belong in an outline.
    $dir = sys_get_temp_dir().'/wire-boost-fetch-'.uniqid();
    mkdir($dir, 0755, true);
    file_put_contents($dir.'/loose.md', "Preamble prose.\n\n# Real Heading\n\nBody.");

    config()->set('wire-boost.docs.paths', [$dir]);
    app()->forgetInstance(DocsIndex::class);

    WireBoostServer::tool(FetchDoc::class, ['id' => basename($dir).'/loose.md'])
        ->assertOk()
        ->assertSee('Real Heading')
        ->assertDontSee('Preamble prose');

    unlink($dir.'/loose.md');
    rmdir($dir);
});

it('exposes an input schema', function () {
    expect(app(FetchDoc::class)->toArray())->toHaveKey('inputSchema');
});
