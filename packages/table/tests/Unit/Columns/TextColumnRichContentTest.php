<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use NyonCode\WireCore\Foundation\Mentions\Contracts\Mentionable;
use NyonCode\WireTable\Columns\TextColumn;

/*
 * A cell showing stored editor content. The names in it are read back per render
 * like everywhere else — which in a table is worth knowing about, because a cell
 * is rendered on its own and so the lookups do not batch across rows.
 */

class RichCellArticle extends Model implements Mentionable
{
    protected $table = 'rich_cell_articles';

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
    Schema::dropIfExists('rich_cell_articles');

    Schema::create('rich_cell_articles', function (Blueprint $table) {
        $table->id();
        $table->string('title');
    });
});

function richCellMention(int|string $id, string $text): string
{
    return '<span data-type="mention" data-mention-trigger="#" data-mention-type="'
        .RichCellArticle::class.'" data-id="'.$id.'">'.$text.'</span>';
}

it('is off unless asked for, and implies raw html when it is', function () {
    expect(TextColumn::make('body')->isRichContent())->toBeFalse()
        ->and(TextColumn::make('body')->isHtml())->toBeFalse()
        ->and(TextColumn::make('body')->richContent()->isRichContent())->toBeTrue()
        // Resolved mentions are markup; printing them escaped would show the tags.
        ->and(TextColumn::make('body')->richContent()->isHtml())->toBeTrue()
        ->and(TextColumn::make('body')->richContent(false)->isRichContent())->toBeFalse();
});

it('reads a mention back from the database', function () {
    $article = RichCellArticle::create(['title' => 'Ceník 2027']);
    $record = Mockery::mock(Model::class);

    $formatted = TextColumn::make('body')
        ->richContent()
        ->formatValue('<p>Viz '.richCellMention($article->id, '#Ceník 2026').'</p>', $record);

    expect($formatted)
        ->toContain('#Ceník 2027')
        ->toContain('href="/clanky/'.$article->id.'"')
        ->not->toContain('Ceník 2026');
});

it('leaves the value alone without the opt-in', function () {
    $article = RichCellArticle::create(['title' => 'Ceník 2027']);
    $record = Mockery::mock(Model::class);

    $stored = '<p>'.richCellMention($article->id, '#Ceník 2026').'</p>';

    expect(TextColumn::make('body')->html()->formatValue($stored, $record))->toBe($stored);
});

it('still shows the empty cell text for nothing at all', function () {
    $record = Mockery::mock(Model::class);

    expect(TextColumn::make('body')->richContent()->formatValue(null, $record))
        ->toBe(TextColumn::make('body')->getEmptyCellText());
});
