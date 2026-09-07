<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Schema;
use NyonCode\WireCore\Foundation\Mentions\Contracts\Mentionable;
use NyonCode\WireCore\Infolists\Components\HtmlEntry;

/*
 * The read side of a mention: an entry that prints markup and resolves what the
 * markup only points at.
 */

class HtmlEntryArticle extends Model implements Mentionable
{
    protected $table = 'html_entry_articles';

    protected $guarded = [];

    public $timestamps = false;

    public function getMentionLabel(): string
    {
        return (string) $this->title;
    }

    public function getMentionUrl(): ?string
    {
        return '/clanky/'.$this->getKey();
    }
}

beforeEach(function () {
    Schema::dropIfExists('html_entry_articles');

    Schema::create('html_entry_articles', function (Blueprint $table) {
        $table->id();
        $table->string('title');
    });
});

function htmlEntryMention(int|string $id, string $text): string
{
    return '<span data-type="mention" data-mention-trigger="#" data-mention-type="'
        .HtmlEntryArticle::class.'" data-id="'.$id.'">'.$text.'</span>';
}

it('prints stored markup instead of escaping it', function () {
    $entry = HtmlEntry::make('body')->record(['body' => '<p>Ahoj <strong>světe</strong></p>']);

    expect($entry->getRenderedHtml())->toBe('<p>Ahoj <strong>světe</strong></p>');
});

it('resolves the mentions the markup only points at', function () {
    $article = HtmlEntryArticle::create(['title' => 'Ceník 2027']);

    $entry = HtmlEntry::make('body')->record([
        'body' => '<p>Viz '.htmlEntryMention($article->id, '#Ceník 2026').'</p>',
    ]);

    expect($entry->getRenderedHtml())
        ->toContain('#Ceník 2027')
        ->toContain('href="/clanky/'.$article->id.'"')
        ->not->toContain('Ceník 2026');
});

it('has nothing to print for an empty or non-scalar state', function () {
    expect(HtmlEntry::make('body')->record(['body' => null])->getRenderedHtml())->toBe('')
        ->and(HtmlEntry::make('body')->record(['body' => ''])->getRenderedHtml())->toBe('')
        ->and(HtmlEntry::make('body')->record(['body' => ['not', 'text']])->getRenderedHtml())->toBe('');
});

it('carries the zone a page was opened in', function () {
    expect(HtmlEntry::make('body')->getZone())->toBeNull()
        ->and(HtmlEntry::make('body')->zone('admin')->getZone())->toBe('admin');
});

it('renders through its own view, with the placeholder when there is nothing', function () {
    $article = HtmlEntryArticle::create(['title' => 'Ceník']);

    $entry = HtmlEntry::make('body')
        ->label('Tělo')
        ->record(['body' => '<p>'.htmlEntryMention($article->id, '#staré').'</p>']);

    expect($entry->render()->name())->toBe('wire-core::infolists.entries.html');

    $html = $entry->render()->render();

    expect($html)->toContain('#Ceník')->toContain('wire-rich-content');

    $empty = HtmlEntry::make('body')->placeholder('Nic')->record(['body' => null])->render()->render();

    expect($empty)->toContain('Nic');
});

it('never shares a render between records', function () {
    // Resolved mentions belong to the row they were looked up for; a memo would
    // hand one row's freshly-read names to another.
    $entry = HtmlEntry::make('body')->record(['body' => '<p>x</p>']);

    $signature = (new ReflectionMethod($entry, 'renderCacheSignature'))->invoke($entry);

    expect($signature)->toBeNull();
});

it('is reachable as a blade component for ordinary pages too', function () {
    $article = HtmlEntryArticle::create(['title' => 'Ceník 2027']);

    $html = Blade::render(
        '<x-wire::rich-content :html="$body" class="prose" />',
        ['body' => '<p>Viz '.htmlEntryMention($article->id, '#Ceník 2026').'</p>'],
    );

    expect($html)
        ->toContain('#Ceník 2027')
        ->toContain('href="/clanky/'.$article->id.'"')
        ->toContain('prose')
        ->and(Blade::render('<x-wire::rich-content :html="null" />'))
        ->toContain('wire-rich-content');
});
