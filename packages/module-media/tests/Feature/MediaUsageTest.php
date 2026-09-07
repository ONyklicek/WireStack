<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use NyonCode\WireModuleMedia\Concerns\HasMedia;
use NyonCode\WireModuleMedia\Concerns\SyncsMediaUsage;
use NyonCode\WireModuleMedia\Livewire\MediaManager;
use NyonCode\WireModuleMedia\Models\Media;
use NyonCode\WireModuleMedia\Support\ContentMedia;
use NyonCode\WireModuleMedia\Support\MediaUsage;

/*
 * Where a file is used.
 *
 * The rich text editor used to store a bare URL, so a photograph in twelve
 * articles reported zero uses — and the confirmation in front of every delete
 * was built on that zero. A warning that says "0" for a file that is in use is
 * not a weak warning, it is a false all-clear. See ADR 0034.
 */

beforeEach(function () {
    Storage::fake('public');

    foreach (glob(__DIR__.'/../../database/migrations/*.php') ?: [] as $migration) {
        (include $migration)->up();
    }

    Schema::create('mu_articles', function (Blueprint $table) {
        $table->id();
        $table->text('body')->nullable();
        $table->text('perex')->nullable();
        $table->timestamps();
    });
});

/** An application's own model, which has said which attributes hold content. */
class MuArticle extends Model
{
    use HasMedia;
    use SyncsMediaUsage;

    protected $table = 'mu_articles';

    protected $guarded = [];

    /** @var array<int, string> */
    protected array $mediaContent = ['body', 'perex'];
}

function muFile(string $name = 'hero.jpg'): Media
{
    return Media::create(['disk' => 'public', 'path' => 'media/2026/'.$name, 'name' => $name, 'mime_type' => 'image/jpeg']);
}

/* ── Reading the content ──────────────────────────────────────────────────── */

it('reads the ids the editor wrote', function () {
    $html = '<p>Ahoj</p><img src="/storage/a.jpg" data-media-id="7"><img src="/storage/b.jpg" data-media-id="12">';

    expect(ContentMedia::idsIn($html))->toBe([7, 12]);
});

it('counts one picture used three times in one article once', function () {
    // The question a warning answers is which records break, not how many tags do.
    $html = str_repeat('<img src="/storage/a.jpg" data-media-id="7">', 3);

    expect(ContentMedia::idsIn($html))->toBe([7]);
});

it('finds nothing in content that has none', function () {
    expect(ContentMedia::idsIn('<p>Just words</p>'))->toBe([])
        ->and(ContentMedia::idsIn(null))->toBe([])
        ->and(ContentMedia::idsIn(''))->toBe([]);
});

/* ── Syncing on save ──────────────────────────────────────────────────────── */

it('records what an article points at when it is saved', function () {
    $hero = muFile();

    $article = MuArticle::create(['body' => '<img src="/storage/media/2026/hero.jpg" data-media-id="'.$hero->id.'">']);

    expect(MediaUsage::countFor($hero))->toBe(1);

    $uses = MediaUsage::for($hero);

    expect($uses)->toHaveCount(1)
        ->and($uses[0]['collection'])->toBe(MediaUsage::CONTENT)
        ->and($uses[0]['label'])->toBe('MuArticle #'.$article->id);
});

it('takes the link away with the picture', function () {
    $hero = muFile();

    $article = MuArticle::create(['body' => '<img data-media-id="'.$hero->id.'">']);
    expect(MediaUsage::countFor($hero))->toBe(1);

    // Synced rather than appended: a count that only ever grows stops meaning
    // anything the first time somebody edits an article.
    $article->update(['body' => '<p>The picture is gone</p>']);

    expect(MediaUsage::countFor($hero))->toBe(0);
});

it('reads every attribute the model named, not just the first', function () {
    $one = muFile('one.jpg');
    $two = muFile('two.jpg');

    MuArticle::create([
        'body' => '<img data-media-id="'.$one->id.'">',
        'perex' => '<img data-media-id="'.$two->id.'">',
    ]);

    expect(MediaUsage::countFor($one))->toBe(1)
        ->and(MediaUsage::countFor($two))->toBe(1);
});

it('leaves a field attachment alone, because it is a different collection', function () {
    $cover = muFile('cover.jpg');
    $inline = muFile('inline.jpg');

    $article = MuArticle::create(['body' => '<img data-media-id="'.$inline->id.'">']);
    $article->attachMedia($cover, 'cover');

    // Saving again re-syncs the content collection and must not touch the field's.
    $article->update(['body' => '<img data-media-id="'.$inline->id.'">']);

    expect($article->media('cover'))->toHaveCount(1)
        ->and(MediaUsage::countFor($cover))->toBe(1)
        ->and(MediaUsage::countFor($inline))->toBe(1);
});

/* ── What the screens do with it ──────────────────────────────────────────── */

it('says what a delete is about to break, and that the number is a floor', function () {
    $hero = muFile();
    MuArticle::create(['body' => '<img data-media-id="'.$hero->id.'">']);

    $manager = Livewire::test(MediaManager::class)->instance();

    expect($manager->deleteWarning(1))->toContain('1')
        // A count presented as complete would be trusted, and would eventually
        // be wrong at the moment it mattered.
        ->and($manager->deleteWarning(1))->toContain('by hand')
        ->and($manager->deleteWarning(0))->not->toContain('by hand');
});

it('shows the uses in the panel, and says so when there are none', function () {
    $used = muFile('used.jpg');
    $lonely = muFile('lonely.jpg');
    MuArticle::create(['body' => '<img data-media-id="'.$used->id.'">']);

    Livewire::test(MediaManager::class)
        ->call('showDetail', $used->id)
        ->assertSee('MuArticle #')
        ->call('showDetail', $lonely->id)
        // Not silence, and not a claim: nothing in the library points at it.
        ->assertSee('That is not proof nobody does.', false);
});

it('counts a whole page of files in one query', function () {
    $a = muFile('a.jpg');
    $b = muFile('b.jpg');
    muFile('c.jpg');

    MuArticle::create(['body' => '<img data-media-id="'.$a->id.'">']);
    MuArticle::create(['body' => '<img data-media-id="'.$a->id.'">']);
    MuArticle::create(['body' => '<img data-media-id="'.$b->id.'">']);

    expect(MediaUsage::countsFor([$a->id, $b->id]))->toBe([$a->id => 2, $b->id => 1])
        ->and(MediaUsage::countsFor([]))->toBe([]);
});

/* ── The backfill ─────────────────────────────────────────────────────────── */

it('finds files in content written before the id was kept', function () {
    $hero = muFile();

    // No data-media-id anywhere: this is what every article already in the
    // database looks like.
    MuArticle::create(['body' => '<p>Look</p><img src="/storage/media/2026/hero.jpg" alt="">']);

    expect(MediaUsage::countFor($hero))->toBe(0);

    Artisan::call('wire-module-media:usage', ['--model' => MuArticle::class]);

    expect(MediaUsage::countFor($hero))->toBe(1);
});

it('writes nothing on a dry run', function () {
    $hero = muFile();
    MuArticle::create(['body' => '<img src="/storage/media/2026/hero.jpg">']);

    Artisan::call('wire-module-media:usage', ['--model' => MuArticle::class, '--dry-run' => true]);

    expect(MediaUsage::countFor($hero))->toBe(0);
});

it('refuses a model that has not said which attributes hold content', function () {
    $code = Artisan::call('wire-module-media:usage', ['--model' => Media::class]);

    expect($code)->toBe(1);
});

it('refuses a model class that does not exist', function () {
    expect(Artisan::call('wire-module-media:usage', ['--model' => 'App\\Nope']))->toBe(1);
});
