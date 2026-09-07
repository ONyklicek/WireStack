<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireForms\Components\Mention;
use NyonCode\WireForms\Components\Mention\Source;
use NyonCode\WireForms\Components\Select;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireForms\Components\TiptapEditor;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WireForms\Forms\WithForms;
use NyonCode\WireForms\WireFormsServiceProvider;

class MentionTestArticle extends Model
{
    protected $table = 'mention_test_articles';

    protected $guarded = [];

    public $timestamps = false;
}

class MentionTestPage extends Model
{
    protected $table = 'mention_test_pages';

    protected $guarded = [];

    public $timestamps = false;
}

class MentionTestUser extends Model
{
    protected $table = 'mention_test_users';

    protected $guarded = [];

    public $timestamps = false;
}

class MentionEditorComponent extends Component
{
    use WithForms;

    /** @var array<string, mixed> */
    public array $data = ['body' => '', 'title' => ''];

    public function form(Form $form): Form
    {
        return $form
            ->statePath('data')
            ->schema([
                TiptapEditor::make('body')->mentions(
                    Mention::make('@')->source(
                        Source::make(MentionTestUser::class)->titleAttribute('name'),
                    ),
                    Mention::make('#')->sources([
                        Source::make(MentionTestArticle::class)
                            ->titleAttribute('title')
                            ->label('Články')
                            ->modifyOptionsQueryUsing(fn (Builder $query) => $query->where('published', true)),
                        Source::make(MentionTestPage::class)->titleAttribute('name'),
                    ]),
                ),
                TextInput::make('title'),
                Select::make('plain')->options(['a' => 'A']),
            ]);
    }

    public function render(): string
    {
        return '<div>{{ $this->form }}</div>';
    }
}

class PlainEditorComponent extends Component
{
    use WithForms;

    /** @var array<string, mixed> */
    public array $data = ['body' => ''];

    public function form(Form $form): Form
    {
        return $form->statePath('data')->schema([TiptapEditor::make('body')]);
    }

    public function render(): string
    {
        return '<div>{{ $this->form }}</div>';
    }
}

beforeEach(function () {
    foreach (['mention_test_articles', 'mention_test_pages', 'mention_test_users'] as $table) {
        Schema::dropIfExists($table);
    }

    Schema::create('mention_test_articles', function (Blueprint $table) {
        $table->id();
        $table->string('title');
        $table->boolean('published')->default(true);
    });

    Schema::create('mention_test_pages', function (Blueprint $table) {
        $table->id();
        $table->string('name');
    });

    Schema::create('mention_test_users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('email')->nullable();
    });

    Relation::morphMap([], false);
});

afterEach(fn () => Relation::morphMap([], false));

// ─── Source ─────────────────────────────────────────────────────────────────

test('a source matches on its title attribute and shapes a row for the list', function () {
    $article = MentionTestArticle::create(['title' => 'Ceník 2026']);
    MentionTestArticle::create(['title' => 'Něco jiného']);

    $results = Source::make(MentionTestArticle::class)->titleAttribute('title')->search('cen');

    expect($results)->toBe([[
        'type' => MentionTestArticle::class,
        'id' => (string) $article->id,
        'label' => 'Ceník 2026',
        'group' => 'MentionTestArticles',
    ]]);
});

test('a source with no title attribute has nothing to offer', function () {
    MentionTestArticle::create(['title' => 'Ceník']);

    expect(Source::make(MentionTestArticle::class)->search('cen'))->toBe([]);
});

test('a source can match one column and show another', function () {
    MentionTestUser::create(['name' => 'Jan Novák', 'email' => 'jan@example.com']);

    $results = Source::make(MentionTestUser::class)
        ->titleAttribute('name')
        ->searchAttribute('email')
        ->search('jan@');

    expect($results)->toHaveCount(1)
        ->and($results[0]['label'])->toBe('Jan Novák');
});

test('a source scopes its own query', function () {
    MentionTestArticle::create(['title' => 'Ceník veřejný', 'published' => true]);
    MentionTestArticle::create(['title' => 'Ceník tajný', 'published' => false]);

    $results = Source::make(MentionTestArticle::class)
        ->titleAttribute('title')
        ->modifyOptionsQueryUsing(fn (Builder $query) => $query->where('published', true))
        ->search('Ceník');

    expect($results)->toHaveCount(1)
        ->and($results[0]['label'])->toBe('Ceník veřejný');
});

test('a source caps its own contribution', function () {
    foreach (range(1, 9) as $i) {
        MentionTestArticle::create(['title' => 'Článek '.$i]);
    }

    expect(Source::make(MentionTestArticle::class)->titleAttribute('title')->search('Článek'))
        ->toHaveCount(Source::DEFAULT_LIMIT)
        ->and(Source::make(MentionTestArticle::class)->titleAttribute('title')->limit(2)->search('Článek'))
        ->toHaveCount(2)
        // A limit below one would be a list that can never offer anything.
        ->and(Source::make(MentionTestArticle::class)->titleAttribute('title')->limit(0)->search('Článek'))
        ->toHaveCount(1);
});

test('a source escapes the wildcards a person can type', function () {
    MentionTestArticle::create(['title' => '100% bavlna']);
    MentionTestArticle::create(['title' => 'Něco jiného']);

    // Unescaped, `%` would match every row in the table.
    expect(Source::make(MentionTestArticle::class)->titleAttribute('title')->search('%'))
        ->toHaveCount(1);
});

test('a source writes the morph alias, not the class, when one is mapped', function () {
    Relation::morphMap(['article' => MentionTestArticle::class]);

    MentionTestArticle::create(['title' => 'Ceník']);

    $results = Source::make(MentionTestArticle::class)->titleAttribute('title')->search('Cen');

    expect($results[0]['type'])->toBe('article');
});

test('a source is named after its model unless it is told otherwise', function () {
    expect(Source::make(MentionTestPage::class)->getLabel())->toBe('MentionTestPages')
        ->and(Source::make(MentionTestPage::class)->label('Stránky')->getLabel())->toBe('Stránky');
});

// ─── Mention ────────────────────────────────────────────────────────────────

test('one trigger merges several sources and keeps them in declaration order', function () {
    MentionTestArticle::create(['title' => 'Ceník článek']);
    MentionTestPage::create(['name' => 'Ceník stránka']);

    $results = Mention::make('#')->sources([
        Source::make(MentionTestArticle::class)->titleAttribute('title')->label('Články'),
        Source::make(MentionTestPage::class)->titleAttribute('name')->label('Stránky'),
    ])->search('Ceník');

    expect($results)->toHaveCount(2)
        ->and(array_column($results, 'group'))->toBe(['Články', 'Stránky'])
        ->and($results[0]['type'])->toBe(MentionTestArticle::class)
        ->and($results[1]['type'])->toBe(MentionTestPage::class);
});

test('a trigger caps the whole list however many sources feed it', function () {
    foreach (range(1, 6) as $i) {
        MentionTestArticle::create(['title' => 'Článek '.$i]);
        MentionTestPage::create(['name' => 'Článek '.$i]);
    }

    $mention = Mention::make('#')->sources([
        Source::make(MentionTestArticle::class)->titleAttribute('title')->limit(4),
        Source::make(MentionTestPage::class)->titleAttribute('name')->limit(4),
    ])->limit(6);

    expect($mention->search('Článek'))->toHaveCount(6);
});

test('an empty term is never a search', function () {
    MentionTestArticle::create(['title' => 'Ceník']);

    $mention = Mention::make('#')->source(
        Source::make(MentionTestArticle::class)->titleAttribute('title'),
    );

    expect($mention->search(''))->toBe([]);
});

test('a trigger reports its own shape to the client', function () {
    $mention = Mention::make('#')->allowSpaces()->limit(9);

    expect($mention->toAlpineConfig())->toBe([
        'trigger' => '#',
        'allowSpaces' => true,
        'limit' => 9,
    ])
        ->and(Mention::make('@')->isAllowingSpaces())->toBeFalse()
        ->and(Mention::make('@')->getLimit())->toBe(Mention::DEFAULT_LIMIT)
        ->and(Mention::make('@')->getTrigger())->toBe('@')
        ->and(Mention::make('@')->limit(0)->getLimit())->toBe(1);
});

test('a trigger with a single source still reports it as a list', function () {
    $source = Source::make(MentionTestUser::class)->titleAttribute('name');

    expect(Mention::make('@')->source($source)->getSources())->toBe([$source]);
});

// ─── The field ──────────────────────────────────────────────────────────────

test('the editor collects mentions passed one by one or as a list', function () {
    $a = Mention::make('@');
    $b = Mention::make('#');

    expect(TiptapEditor::make('body')->mentions($a, $b)->getMentions())->toBe([$a, $b])
        ->and(TiptapEditor::make('body')->mentions([$a, $b])->getMentions())->toBe([$a, $b])
        ->and(TiptapEditor::make('body')->getMentions())->toBe([]);
});

test('only an editor with mentions pays for the mention bundle', function () {
    expect(TiptapEditor::make('body')->needsMentionAddon())->toBeFalse()
        ->and(TiptapEditor::make('body')->withTables()->needsMentionAddon())->toBeFalse()
        ->and(TiptapEditor::make('body')->mentions(Mention::make('@'))->needsMentionAddon())->toBeTrue()
        // …and mentions alone never pull in the table/image addon.
        ->and(TiptapEditor::make('body')->mentions(Mention::make('@'))->needsExtensionAddon())->toBeFalse();
});

test('the editor finds a trigger by its character', function () {
    $at = Mention::make('@');
    $field = TiptapEditor::make('body')->mentions($at, Mention::make('#'));

    expect($field->findMention('@'))->toBe($at)
        ->and($field->findMention('!'))->toBeNull();
});

test('the editor hands the client its triggers and its state path', function () {
    $config = TiptapEditor::make('body')
        ->statePath('data')
        ->mentions(Mention::make('@'), Mention::make('#')->allowSpaces())
        ->getAlpineConfig();

    expect($config['statePath'])->toBe('data.body')
        ->and($config['mentions'])->toBe([
            ['trigger' => '@', 'allowSpaces' => false, 'limit' => Mention::DEFAULT_LIMIT],
            ['trigger' => '#', 'allowSpaces' => true, 'limit' => Mention::DEFAULT_LIMIT],
        ]);
});

// ─── The endpoint ───────────────────────────────────────────────────────────

test('the endpoint answers with the field own scoped sources', function () {
    MentionTestArticle::create(['title' => 'Ceník veřejný', 'published' => true]);
    MentionTestArticle::create(['title' => 'Ceník tajný', 'published' => false]);
    MentionTestPage::create(['name' => 'Ceník stránka']);

    $results = Livewire::test(MentionEditorComponent::class)
        ->call('searchEditorMentions', 'data.body', '#', 'Ceník')
        ->effects['returns'][0];

    expect(array_column($results, 'label'))->toBe(['Ceník veřejný', 'Ceník stránka'])
        ->and($results[0]['group'])->toBe('Články');
});

test('the endpoint refuses a trigger the field does not offer', function () {
    MentionTestUser::create(['name' => 'Jan']);

    $results = Livewire::test(MentionEditorComponent::class)
        ->call('searchEditorMentions', 'data.body', '!', 'Jan')
        ->effects['returns'][0];

    expect($results)->toBe([]);
});

test('the endpoint refuses a state path that is not an editor', function () {
    $component = Livewire::test(MentionEditorComponent::class);

    expect($component->call('searchEditorMentions', 'data.title', '@', 'Jan')->effects['returns'][0])->toBe([])
        ->and($component->call('searchEditorMentions', 'data.missing', '@', 'Jan')->effects['returns'][0])->toBe([]);
});

test('the endpoint never answers an empty term', function () {
    MentionTestUser::create(['name' => 'Jan']);

    $results = Livewire::test(MentionEditorComponent::class)
        ->call('searchEditorMentions', 'data.body', '@', '')
        ->effects['returns'][0];

    expect($results)->toBe([]);
});

// ─── Delivery ───────────────────────────────────────────────────────────────

test('the mention bundle ships in the package and is served like the others', function () {
    $dir = WireFormsServiceProvider::ASSETS_PATH.'/tiptap';

    expect(is_file($dir.'/tiptap-editor-mentions.js'))->toBeTrue()
        ->and(file_get_contents($dir.'/tiptap-editor-mentions.js'))->toContain('WireTiptapMentions');

    $this->get('/wire-forms/tiptap/tiptap-editor-mentions.js')->assertOk();
});

test('an editor that declares mentions registers a bundle the plain one does not', function () {
    // What @assets injects is a page-level concern Livewire hoists out of the
    // component, so this asserts the registration rather than the tag. That the
    // tag is the mention chunk, and that the chunk then works, is what the
    // browser driver (workbench/scripts/verify-mentions.mjs) is for.
    $mentionAssets = Livewire::test(MentionEditorComponent::class)->snapshot['memo']['assets'];
    $plainAssets = Livewire::test(PlainEditorComponent::class)->snapshot['memo']['assets'];

    expect($mentionAssets)->toHaveCount(count($plainAssets) + 1);
});
