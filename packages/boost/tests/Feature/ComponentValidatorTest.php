<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use NyonCode\WireBoost\Exceptions\UnresolvableComponentException;
use NyonCode\WireBoost\Mcp\Tools\ValidateComponent;
use NyonCode\WireBoost\Mcp\WireBoostServer;
use NyonCode\WireBoost\Support\Validation\ComponentValidator;
use NyonCode\WireBoost\Tests\Fixtures\BrokenPostTable;
use NyonCode\WireBoost\Tests\Fixtures\DemoForm;
use NyonCode\WireBoost\Tests\Fixtures\DemoTable;
use NyonCode\WireBoost\Tests\Fixtures\LooseTable;
use NyonCode\WireBoost\Tests\Fixtures\NotAComponent;
use NyonCode\WireBoost\Tests\Fixtures\Post;
use NyonCode\WireBoost\Tests\Fixtures\ThrowingTable;
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

    $this->validate = fn (string $class): array => app(ComponentValidator::class)->validate($class);
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
