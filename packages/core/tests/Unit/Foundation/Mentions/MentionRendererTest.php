<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use NyonCode\WireCore\Core\Resources\Concerns\DescribesRecords;
use NyonCode\WireCore\Core\Resources\Contracts\DescribesResource;
use NyonCode\WireCore\Core\Resources\ResourceRegistry;
use NyonCode\WireCore\Exceptions\MentionRegistrationException;
use NyonCode\WireCore\Foundation\Contracts\ResolvesRecordUrls;
use NyonCode\WireCore\Foundation\Mentions\Contracts\Mentionable;
use NyonCode\WireCore\Foundation\Mentions\MentionRegistry;
use NyonCode\WireCore\Foundation\Mentions\MentionRenderer;
use NyonCode\WireCore\Foundation\Routing\Contracts\ResolvesPageUrls;

/**
 * The renderer is the whole reason a mention stores an identity and not a name:
 * every test here is a way for the stored text to be out of date.
 */
class MentionArticle extends Model implements Mentionable
{
    protected $table = 'mention_articles';

    protected $guarded = [];

    public $timestamps = false;

    public function getMentionLabel(): string
    {
        return (string) $this->title;
    }

    public function getMentionUrl(): ?string
    {
        return $this->published ? '/clanky/'.$this->getKey() : null;
    }
}

/** A resource over MentionPage, so a mention can fall back to its own screen. */
class MentionArticleResource implements DescribesResource
{
    use DescribesRecords;

    public static function modelClass(): ?string
    {
        return MentionPage::class;
    }
}

/**
 * Stands in for `wire-panels`: core binds "nothing is routed" by default, and
 * what the fallback does with a real answer is the thing under test.
 */
class FakePageUrls implements ResolvesPageUrls
{
    public function __construct(private readonly bool $routed = true) {}

    public function urlFor(string $key, string $page = 'index', array $parameters = [], ?string $zone = null): ?string
    {
        if (! $this->routed || $page !== 'view') {
            return null;
        }

        return '/panel/pages/'.($parameters['record'] ?? '');
    }
}

/** Deliberately not Mentionable — the model an application does not own. */
class MentionPage extends Model
{
    protected $table = 'mention_pages';

    protected $guarded = [];

    public $timestamps = false;
}

beforeEach(function () {
    Schema::dropIfExists('mention_articles');
    Schema::dropIfExists('mention_pages');

    Schema::create('mention_articles', function (Blueprint $table) {
        $table->id();
        $table->string('title');
        $table->boolean('published')->default(true);
    });

    Schema::create('mention_pages', function (Blueprint $table) {
        $table->id();
        $table->string('name');
    });

    Relation::morphMap([], false);
});

afterEach(function () {
    Relation::morphMap([], false);
});

function mentionSpan(string $type, string|int $id, string $text, string $trigger = '#'): string
{
    return '<span data-type="mention" data-mention-trigger="'.$trigger.'" '
        .'data-mention-type="'.$type.'" data-id="'.$id.'">'.$text.'</span>';
}

function renderer(): MentionRenderer
{
    return app(MentionRenderer::class);
}

// ─── The pass-through ───────────────────────────────────────────────────────

it('returns content holding no mentions byte for byte', function () {
    $html = '<p>Nic tu <strong>není</strong> ke hledání.</p>';

    expect(renderer()->render($html))->toBe($html);
});

it('returns empty content untouched', function () {
    expect(renderer()->render(''))->toBe('');
});

// ─── Freshness ──────────────────────────────────────────────────────────────

it('renders the label the record has now, not the one that was stored', function () {
    $article = MentionArticle::create(['title' => 'Ceník 2027']);

    $html = '<p>Viz '.mentionSpan(MentionArticle::class, $article->id, '#Ceník 2026').'.</p>';

    $rendered = renderer()->render($html);

    expect($rendered)
        ->toContain('#Ceník 2027')
        ->not->toContain('Ceník 2026')
        ->toContain('href="/clanky/'.$article->id.'"');
});

it('keeps the trigger in front of the fresh label', function () {
    $article = MentionArticle::create(['title' => 'Ceník']);

    expect(renderer()->render(mentionSpan(MentionArticle::class, $article->id, '#starý', '#')))
        ->toContain('>#Ceník<');
});

it('renders a record that says it has no url as text rather than a link', function () {
    $article = MentionArticle::create(['title' => 'Koncept', 'published' => false]);

    $rendered = renderer()->render(mentionSpan(MentionArticle::class, $article->id, '#starý'));

    expect($rendered)
        ->toContain('#Koncept')
        ->not->toContain('<a ');
});

// ─── The fallback ───────────────────────────────────────────────────────────

it('falls back to the stored label when the record is gone', function () {
    $html = mentionSpan(MentionArticle::class, 999, '#Ceník 2026');

    $rendered = renderer()->render($html);

    expect($rendered)
        ->toContain('#Ceník 2026')
        ->toContain('wire-mention--unresolved')
        ->not->toContain('<a ');
});

it('falls back for a type the application no longer has', function () {
    $rendered = renderer()->render(mentionSpan('app\\models\\gone', 7, '#Smazané'));

    expect($rendered)->toContain('#Smazané')->toContain('wire-mention--unresolved');
});

it('falls back for a model that is neither mentionable nor registered', function () {
    $page = MentionPage::create(['name' => 'Kontakty']);

    $rendered = renderer()->render(mentionSpan(MentionPage::class, $page->id, '#Kontakt'));

    expect($rendered)->toContain('#Kontakt')->toContain('wire-mention--unresolved');
});

it('names an unlabelled mention by its id when even the stored text is empty', function () {
    $rendered = renderer()->render(mentionSpan(MentionArticle::class, 42, ''));

    expect($rendered)->toContain('#42');
});

// ─── The registry ───────────────────────────────────────────────────────────

it('resolves a model that answers through the registry instead of the contract', function () {
    app(MentionRegistry::class)
        ->register(MentionPage::class)
        ->titleAttribute('name')
        ->url(fn (Model $page): string => '/stranky/'.$page->getKey());

    $page = MentionPage::create(['name' => 'Kontakty']);

    $rendered = renderer()->render(mentionSpan(MentionPage::class, $page->id, '#Staré'));

    expect($rendered)
        ->toContain('#Kontakty')
        ->toContain('href="/stranky/'.$page->id.'"');
});

it('treats a record the registry query excludes exactly like a deleted one', function () {
    app(MentionRegistry::class)
        ->register(MentionPage::class)
        ->titleAttribute('name')
        ->modifyQueryUsing(fn ($query) => $query->where('name', 'Veřejné'));

    $page = MentionPage::create(['name' => 'Interní zápis Q3']);

    $rendered = renderer()->render(mentionSpan(MentionPage::class, $page->id, '#Interní zápis Q3'));

    // The point of the shared path: a title the viewer may not see is never
    // fetched, so the only thing on screen is what was already in the document.
    expect($rendered)->toContain('wire-mention--unresolved')->not->toContain('<a ');
});

it('refines an existing registration rather than replacing it', function () {
    $registry = app(MentionRegistry::class);

    $first = $registry->register(MentionPage::class)->titleAttribute('name');
    $second = $registry->register(MentionPage::class);

    expect($second)->toBe($first)
        ->and($registry->all())->toHaveCount(1);
});

it('refuses to register something that is neither a model nor an alias', function () {
    expect(fn () => app(MentionRegistry::class)->register('nonsense'))
        ->toThrow(MentionRegistrationException::class, 'nonsense');
});

it('has nothing to say about an unregistered type', function () {
    expect(app(MentionRegistry::class)->for(MentionPage::class))->toBeNull()
        ->and(app(MentionRegistry::class)->for(null))->toBeNull();
});

// ─── Morph aliases ──────────────────────────────────────────────────────────

it('reads a stored morph alias back through the morph map', function () {
    Relation::morphMap(['article' => MentionArticle::class]);

    $article = MentionArticle::create(['title' => 'Aliasovaný']);

    expect(renderer()->render(mentionSpan('article', $article->id, '#starý')))
        ->toContain('#Aliasovaný');
});

it('finds a registry entry registered by alias when the document stored the class', function () {
    Relation::morphMap(['page' => MentionPage::class]);

    app(MentionRegistry::class)->register('page')->titleAttribute('name');

    $page = MentionPage::create(['name' => 'Kontakty']);

    expect(renderer()->render(mentionSpan(MentionPage::class, $page->id, '#staré')))
        ->toContain('#Kontakty');
});

// ─── Cost ───────────────────────────────────────────────────────────────────

it('costs one query per type no matter how many mentions there are', function () {
    $articles = collect(range(1, 6))->map(
        fn (int $i) => MentionArticle::create(['title' => 'Článek '.$i]),
    );
    $page = MentionPage::create(['name' => 'Kontakty']);

    app(MentionRegistry::class)->register(MentionPage::class)->titleAttribute('name');

    $html = '<p>'.$articles->map(
        fn (MentionArticle $a) => mentionSpan(MentionArticle::class, $a->id, '#x'),
    )->implode(' ').mentionSpan(MentionPage::class, $page->id, '#y').'</p>';

    $queries = 0;
    DB::listen(function () use (&$queries) {
        $queries++;
    });

    renderer()->render($html);

    expect($queries)->toBe(2);
});

it('asks once for a record mentioned several times', function () {
    $article = MentionArticle::create(['title' => 'Opakovaný']);

    $html = str_repeat(mentionSpan(MentionArticle::class, $article->id, '#x'), 4);

    $queries = 0;
    DB::listen(function () use (&$queries) {
        $queries++;
    });

    $rendered = renderer()->render($html);

    expect($queries)->toBe(1)
        ->and(substr_count($rendered, '#Opakovaný'))->toBe(4);
});

// ─── Safety ─────────────────────────────────────────────────────────────────

it('escapes a label that came straight out of the database', function () {
    $article = MentionArticle::create(['title' => '<script>alert(1)</script>']);

    $rendered = renderer()->render(mentionSpan(MentionArticle::class, $article->id, '#staré'));

    expect($rendered)
        ->not->toContain('<script>')
        ->toContain('&lt;script&gt;');
});

it('keeps utf-8 intact through the dom round trip', function () {
    $article = MentionArticle::create(['title' => 'Příliš žluťoučký kůň']);

    expect(renderer()->render('<p>Ěščřžýáíé '.mentionSpan(MentionArticle::class, $article->id, '#x').'</p>'))
        ->toContain('Ěščřžýáíé')
        ->toContain('Příliš žluťoučký kůň');
});

it('writes the identity back so rendered output can be rendered again', function () {
    $article = MentionArticle::create(['title' => 'Stálý']);

    $once = renderer()->render(mentionSpan(MentionArticle::class, $article->id, '#x'));

    expect(renderer()->render($once))->toContain('#Stálý');
});

// ─── extract() ──────────────────────────────────────────────────────────────

it('extracts every mention in document order', function () {
    $html = mentionSpan(MentionArticle::class, 1, '#A').' a '.mentionSpan('user', 9, '@B', '@');

    $references = renderer()->extract($html);

    expect($references)->toHaveCount(2)
        ->and($references[0]->type)->toBe(MentionArticle::class)
        ->and($references[0]->id)->toBe('1')
        ->and($references[0]->key())->toBe(MentionArticle::class.':1')
        ->and($references[1]->trigger)->toBe('@')
        ->and($references[1]->label)->toBe('@B');
});

it('extracts nothing from content that has none', function () {
    expect(renderer()->extract('<p>nic</p>'))->toBe([]);
});

it('ignores a mention node missing the identity it needs', function () {
    $html = '<span data-type="mention" data-mention-type="" data-id="">#nic</span>';

    expect(renderer()->extract($html))->toBe([])
        ->and(renderer()->render($html))->toBe($html);
});

it('leaves a broken mention node alone while resolving the ones beside it', function () {
    $article = MentionArticle::create(['title' => 'Platný']);

    $rendered = renderer()->render(
        '<span data-type="mention" data-mention-type="" data-id="">#nic</span>'
        .mentionSpan(MentionArticle::class, $article->id, '#starý'),
    );

    expect($rendered)->toContain('#nic')->toContain('#Platný');
});

it('has no fresh label for a registration that only knows the url', function () {
    app(MentionRegistry::class)
        ->register(MentionPage::class)
        ->url(fn (Model $page): string => '/stranky/'.$page->getKey());

    $page = MentionPage::create(['name' => 'Kontakty']);

    // A URL without a name is not enough to rewrite the text, so the document's
    // own label stands — and it is not linked to a name nobody vouched for.
    expect(renderer()->render(mentionSpan(MentionPage::class, $page->id, '#Staré')))
        ->toContain('#Staré')
        ->toContain('wire-mention--unresolved');
});

it('falls back to the record own screen when nothing else names a url', function () {
    app(ResourceRegistry::class)->registerMany([MentionArticleResource::class]);
    app()->bind(ResolvesPageUrls::class, fn (): ResolvesPageUrls => new FakePageUrls);

    app(MentionRegistry::class)->register(MentionPage::class)->titleAttribute('name');

    $page = MentionPage::create(['name' => 'Kontakty']);

    expect(renderer()->render(mentionSpan(MentionPage::class, $page->id, '#staré')))
        ->toContain('#Kontakty')
        ->toContain('href="/panel/pages/'.$page->id.'"');
});

it('has no link for a record whose resource is not routed, or has none', function () {
    app()->bind(ResolvesPageUrls::class, fn (): ResolvesPageUrls => new FakePageUrls(routed: false));

    app(MentionRegistry::class)->register(MentionPage::class)->titleAttribute('name');

    $page = MentionPage::create(['name' => 'Kontakty']);

    // Registered but unrouted…
    app(ResourceRegistry::class)->registerMany([MentionArticleResource::class]);
    expect(renderer()->render(mentionSpan(MentionPage::class, $page->id, '#x')))
        ->toContain('#Kontakty')
        ->not->toContain('<a ');

    // …and a model no resource owns at all.
    expect(app(ResolvesRecordUrls::class)->urlForRecord(new MentionPage))->toBeNull();
});
