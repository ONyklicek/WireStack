<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use NyonCode\WireBoost\Exceptions\UnresolvableComponentException;
use NyonCode\WireBoost\Mcp\Tools\ValidateComponent;
use NyonCode\WireBoost\Mcp\WireBoostServer;
use NyonCode\WireBoost\Support\Validation\ComponentValidator;
use NyonCode\WireBoost\Tests\Fixtures\ActionsOnlyPage;
use NyonCode\WireBoost\Tests\Fixtures\AggregatePostTable;
use NyonCode\WireBoost\Tests\Fixtures\AwkwardPage;
use NyonCode\WireBoost\Tests\Fixtures\BrokenPostTable;
use NyonCode\WireBoost\Tests\Fixtures\DemoForm;
use NyonCode\WireBoost\Tests\Fixtures\DemoTable;
use NyonCode\WireBoost\Tests\Fixtures\LooseTable;
use NyonCode\WireBoost\Tests\Fixtures\NotAComponent;
use NyonCode\WireBoost\Tests\Fixtures\Post;
use NyonCode\WireBoost\Tests\Fixtures\PostPage;
use NyonCode\WireBoost\Tests\Fixtures\ThrowingTable;
use NyonCode\WireBoost\Tests\Fixtures\UnbuildablePage;
use NyonCode\WireBoost\Tests\Fixtures\UntypedTable;
use NyonCode\WireBoost\Tests\Fixtures\ValidPostTable;

beforeEach(function () {
    Schema::create('boost_authors', function ($table) {
        $table->increments('id');
        $table->string('name');
    });

    Schema::create('boost_posts', function ($table) {
        $table->increments('id');
        $table->string('title');
        $table->boolean('published')->default(false);
        $table->unsignedInteger('author_id')->nullable();
    });

    $this->validate = fn (string $class, mixed $record = null, ?string $action = null): array => app(ComponentValidator::class)->validate($class, $record, $action);

    PostPage::$formContexts = [];
});

afterEach(function () {
    Schema::dropIfExists('boost_posts');
    Schema::dropIfExists('boost_authors');
});

it('passes a component whose columns all resolve', function () {
    $result = ($this->validate)(ValidPostTable::class);

    expect($result['ok'])->toBeTrue()
        ->and($result['diagnostics'])->toBe([])
        ->and($result['model'])->toBe(Post::class)
        ->and($result['kind'])->toBe('table');
});

it('reports the table it checked attributes against', function () {
    expect(($this->validate)(ValidPostTable::class)['attributesChecked'])->toBe('boost_posts');
});

it('counts what it checked', function () {
    expect(($this->validate)(ValidPostTable::class)['checked'])
        ->toBe(['columns' => 7, 'actions' => 1]);
});

it('catches a column name the model cannot resolve', function () {
    $diagnostics = collect(($this->validate)(BrokenPostTable::class)['diagnostics'])
        ->where('rule', 'unknown-attribute');

    expect($diagnostics->pluck('target')->all())
        ->toBe(['columns.titel', 'columns.nonexistent_thing']);
});

it('suggests the column the author meant', function () {
    $typo = collect(($this->validate)(BrokenPostTable::class)['diagnostics'])
        ->firstWhere('target', 'columns.titel');

    expect($typo['suggestions'])->toContain('title')
        ->and($typo['severity'])->toBe('warning')
        ->and($typo['message'])->toContain('render blank');
});

it('offers no suggestion when nothing is close', function () {
    $unknown = collect(($this->validate)(BrokenPostTable::class)['diagnostics'])
        ->firstWhere('target', 'columns.nonexistent_thing');

    expect($unknown)->not->toHaveKey('suggestions');
});

it('catches a color that would silently render gray', function () {
    $color = collect(($this->validate)(BrokenPostTable::class)['diagnostics'])
        ->firstWhere('rule', 'unknown-color');

    expect($color['severity'])->toBe('error')
        ->and($color['message'])->toContain('silently render as gray')
        ->and($color['suggestions'])->toContain('blue');
});

it('catches an icon that is not registered', function () {
    $icon = collect(($this->validate)(BrokenPostTable::class)['diagnostics'])
        ->firstWhere('rule', 'unknown-icon');

    expect($icon['severity'])->toBe('error')
        ->and($icon['message'])->toContain('not registered');
});

it('does not flag a column that computes its own state', function () {
    $targets = collect(($this->validate)(ValidPostTable::class)['diagnostics'])->pluck('target');

    expect($targets)->not->toContain('columns.computed');
});

it('does not flag actions for not being model attributes', function () {
    $targets = collect(($this->validate)(ValidPostTable::class)['diagnostics'])->pluck('target');

    expect($targets)->not->toContain('actions.delete');
});

it('skips attribute checks when the table is unreachable', function () {
    Schema::dropIfExists('boost_posts');

    $result = ($this->validate)(BrokenPostTable::class);

    expect($result['attributesChecked'])->toBe('skipped (no reachable database table)')
        ->and(collect($result['diagnostics'])->pluck('rule'))->not->toContain('unknown-attribute')
        // Colors and icons need no database, so they are still checked.
        ->and(collect($result['diagnostics'])->pluck('rule'))->toContain('unknown-color');
});

it('validates a component with no model at all', function () {
    $result = ($this->validate)(DemoTable::class);

    expect($result)->not->toHaveKey('model')
        ->and($result['ok'])->toBeTrue();
});

it('validates a form component', function () {
    $result = ($this->validate)(DemoForm::class);

    expect($result['kind'])->toBe('form')
        ->and($result['checked'])->toHaveKey('schema');
});

it('reports a component that cannot be built as a finding, not a failure', function () {
    // Reporting problems is this tool's job, so it handles the build failure
    // itself instead of letting it become a failed call.
    $result = ($this->validate)(ThrowingTable::class);

    expect($result['ok'])->toBeFalse()
        ->and($result['diagnostics'][0]['rule'])->toBe('build-failed')
        ->and($result['diagnostics'][0]['severity'])->toBe('error')
        ->and($result['diagnostics'][0]['message'])->toContain('boom');
});

it('fails on a class that does not exist', function () {
    ($this->validate)('App\\Nope');
})->throws(UnresolvableComponentException::class, 'does not exist');

// --- Aggregates ------------------------------------------------------------

it('does not flag a rollup column for not being an attribute', function () {
    $targets = collect(($this->validate)(AggregatePostTable::class)['diagnostics'])->pluck('target');

    expect($targets)->not->toContain('columns.tags_count')
        ->and($targets)->not->toContain('columns.author_count')
        ->and($targets)->not->toContain('columns.author.count');
});

it('catches a rollup over a relation the model does not have', function () {
    $diagnostics = ($this->validate)(AggregatePostTable::class)['diagnostics'];

    expect($diagnostics)->toHaveCount(1)
        ->and($diagnostics[0]['rule'])->toBe('unknown-relation')
        ->and($diagnostics[0]['target'])->toBe('columns.nope_count')
        ->and($diagnostics[0]['severity'])->toBe('error')
        ->and($diagnostics[0]['message'])->toContain('[nope]')
        ->and($diagnostics[0]['message'])->toContain('count');
});

// --- Pages and their actions -------------------------------------------------

it('inspects a page that has only actions', function () {
    $result = ($this->validate)(ActionsOnlyPage::class);

    expect($result['kind'])->toBe('actions')
        ->and($result['ok'])->toBeTrue()
        ->and($result['checked'])->toBe(['pageActions' => 1])
        ->and($result['actionForms'])->toBe(['pageActions.backup' => 1])
        ->and($result)->not->toHaveKey('attributesChecked');
});

it('reports a record page built without a record, and still checks its actions', function () {
    $result = ($this->validate)(PostPage::class);
    $diagnostics = collect($result['diagnostics']);

    expect($diagnostics->firstWhere('target', 'infolist()')['message'])->toContain('mounted without a record')
        // The infolist failing is one finding; the header actions beside it are
        // still looked at.
        ->and($diagnostics->pluck('target'))->toContain('pageHeaderActions.publish');
});

it('builds a record page from the record key it is given', function () {
    $post = Post::query()->create(['title' => 'Hello']);

    $result = ($this->validate)(PostPage::class, $post->getKey());

    expect($result['record'])->toBe($post->getKey())
        ->and($result['checked'])->toHaveKey('schema')
        ->and(collect($result['diagnostics'])->pluck('target'))->not->toContain('infolist()')
        // The action's form was built against the page's record.
        ->and(PostPage::$formContexts[0])->toBeInstanceOf(Post::class)
        ->and(PostPage::$formContexts[0]->getKey())->toBe($post->getKey());
});

it('checks every action form on a page — header, grouped, wizard steps', function () {
    $post = Post::query()->create(['title' => 'Hello']);
    $result = ($this->validate)(PostPage::class, $post->getKey());
    $diagnostics = collect($result['diagnostics'])->keyBy('target');

    expect($diagnostics['pageHeaderActions.publish']['rule'])->toBe('unknown-icon')
        ->and($diagnostics['pageHeaderActions.publish.form.hint']['rule'])->toBe('unknown-color')
        ->and($diagnostics['pageHeaderActions.grouped.form.careful']['rule'])->toBe('unknown-icon')
        ->and($diagnostics['pageActions.onboard.form.plan']['rule'])->toBe('unknown-color')
        ->and($diagnostics['pageActions.onboard.form.plan']['suggestions'])->toContain('blue')
        ->and($result['actionForms'])->toBe([
            'pageHeaderActions.publish' => 2,
            'pageHeaderActions.grouped' => 1,
            'pageActions.onboard' => 2,
        ]);
});

it('reports an action form that cannot be built against that action alone', function () {
    $post = Post::query()->create(['title' => 'Hello']);
    $result = ($this->validate)(PostPage::class, $post->getKey());

    $failed = collect($result['diagnostics'])->firstWhere('target', 'pageActions.dns.form');

    expect($failed['rule'])->toBe('build-failed')
        ->and($failed['message'])->toContain('agent unreachable')
        ->and($result['actionForms'])->toHaveKey('pageActions.onboard');
});

it('checks a single action when one is named', function () {
    $post = Post::query()->create(['title' => 'Hello']);
    $result = ($this->validate)(PostPage::class, $post->getKey(), 'onboard');

    expect($result['action'])->toBe('onboard')
        ->and($result['checked'])->toBe(['pageActions' => 1])
        ->and($result['actionForms'])->toBe(['pageActions.onboard' => 2])
        ->and(collect($result['diagnostics'])->pluck('target')->all())->toBe(['pageActions.onboard.form.plan']);
});

it('names the action meant when the one asked for does not exist', function () {
    $post = Post::query()->create(['title' => 'Hello']);
    $diagnostic = collect(($this->validate)(PostPage::class, $post->getKey(), 'onbaord')['diagnostics'])
        ->firstWhere('rule', 'unknown-action');

    expect($diagnostic['severity'])->toBe('error')
        ->and($diagnostic['suggestions'])->toContain('onboard');
});

it('checks the forms of a table\'s own row actions', function () {
    $result = ($this->validate)(BrokenPostTable::class);
    $color = collect($result['diagnostics'])->firstWhere('target', 'actions.flag.form.policy');

    expect($result['actionForms'])->toBe(['actions.flag' => 2])
        ->and($color['rule'])->toBe('unknown-color')
        ->and($color['suggestions'])->toContain('orange')
        // A form field is no model attribute: `reason` is not flagged.
        ->and(collect($result['diagnostics'])->pluck('target'))->not->toContain('actions.flag.form.reason');
});

it('says when a record key has nowhere to go', function () {
    $diagnostic = collect(($this->validate)(ActionsOnlyPage::class, 5)['diagnostics'])->firstWhere('rule', 'record-ignored');

    expect($diagnostic['severity'])->toBe('warning');
});

it('reports header actions that cannot be listed, and checks the rest', function () {
    $result = ($this->validate)(AwkwardPage::class);

    expect(collect($result['diagnostics'])->firstWhere('target', 'getHeaderActions()')['message'])
        ->toContain('header needs the request')
        ->and($result['checked'])->toBe(['pageActions' => 1]);
});

it('says when the record property refuses the key', function () {
    $diagnostic = collect(($this->validate)(AwkwardPage::class, 'not-a-number')['diagnostics'])
        ->firstWhere('rule', 'record-ignored');

    expect($diagnostic['severity'])->toBe('warning')
        ->and($diagnostic['message'])->toContain('int');
});

it('reports a component the container cannot make', function () {
    $result = ($this->validate)(UnbuildablePage::class);

    expect($result['ok'])->toBeFalse()
        ->and($result['kind'])->toBe('actions')
        ->and($result['diagnostics'][0]['rule'])->toBe('build-failed');
});

it('passes record and action through the mcp tool', function () {
    $post = Post::query()->create(['title' => 'Hello']);

    WireBoostServer::tool(ValidateComponent::class, [
        'component' => PostPage::class,
        'record' => (string) $post->getKey(),
        'action' => 'onboard',
    ])
        ->assertOk()
        ->assertSee('pageActions.onboard.form.plan');
});

it('fails on a class that builds nothing', function () {
    // NotAComponent::table() returns a string. PHP silently discards the extra
    // argument, so a name-only check would "build" it and report a baffling
    // TypeError instead of saying it is not a wire component.
    ($this->validate)(NotAComponent::class);
})->throws(UnresolvableComponentException::class, 'no table(), form() or infolist()');

it('recognises a builder method that has no return type', function () {
    $result = ($this->validate)(UntypedTable::class);

    expect($result['kind'])->toBe('table')
        ->and($result['ok'])->toBeTrue();
});

it('ignores a table() that identifies nothing as a wire builder', function () {
    ($this->validate)(LooseTable::class);
})->throws(UnresolvableComponentException::class, 'no table(), form() or infolist()');

it('exposes validation through the mcp tool', function () {
    WireBoostServer::tool(ValidateComponent::class, ['component' => BrokenPostTable::class])
        ->assertOk()
        ->assertSee('unknown-color')
        ->assertSee('unknown-attribute');
});
