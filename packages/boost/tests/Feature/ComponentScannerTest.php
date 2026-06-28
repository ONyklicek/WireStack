<?php

declare(strict_types=1);

use NyonCode\WireBoost\Support\ComponentScanner;
use NyonCode\WireBoost\Tests\Fixtures\DemoForm;
use NyonCode\WireBoost\Tests\Fixtures\DemoInfolist;
use NyonCode\WireBoost\Tests\Fixtures\DemoTable;
use NyonCode\WireBoost\Tests\Fixtures\PlainComponent;

beforeEach(function () {
    $this->fixtures = realpath(__DIR__.'/../Fixtures');
    $this->scanner = new ComponentScanner;
});

it('discovers wire components beneath the given paths', function () {
    $components = collect($this->scanner->scan([$this->fixtures]));
    $classes = $components->pluck('class');

    expect($classes)->toContain(DemoTable::class, DemoForm::class, DemoInfolist::class)
        ->and($classes)->not->toContain(PlainComponent::class);

    $table = $components->firstWhere('class', DemoTable::class);
    expect($table['kinds'])->toBe(['table'])
        ->and($table['file'])->toEndWith('DemoTable.php');

    expect($components->firstWhere('class', DemoForm::class)['kinds'])->toBe(['form'])
        ->and($components->firstWhere('class', DemoInfolist::class)['kinds'])->toBe(['infolist']);
});

it('falls back to the configured scan paths', function () {
    config()->set('wire-boost.scan.paths', [$this->fixtures]);

    expect(collect($this->scanner->scan())->pluck('class'))->toContain(DemoTable::class);
});

it('returns an empty list when no directory is configured', function () {
    config()->set('wire-boost.scan.paths', ['/does/not/exist']);

    expect($this->scanner->scan())->toBe([]);
});
