<?php

declare(strict_types=1);

use NyonCode\WireCore\Foundation\Contracts\CanBeDehydrated;
use NyonCode\WireForms\Components\MorphToSelect;
use NyonCode\WireForms\Components\Repeater;
use NyonCode\WireForms\Components\Tags;
use NyonCode\WireForms\Components\TextInput;

/*
 * Each component that is not a column says so itself. Before this the save
 * handler enumerated them — so a field from a package it had never heard of had
 * no way to opt out, and the same rule was written three times.
 */

it('writes a plain field by default', function () {
    expect(TextInput::make('name')->isDehydrated())->toBeTrue();
});

it('lets an owner switch a field off', function () {
    expect(TextInput::make('name')->dehydrated(false)->isDehydrated())->toBeFalse();
});

it('keeps a column-backed repeater and drops a relationship one', function () {
    expect(Repeater::make('rows')->isDehydrated())->toBeTrue()
        ->and(Repeater::make('rows')->relationship('lines')->isDehydrated())->toBeFalse();
});

it('lets an owner switch a column-backed repeater off as well', function () {
    expect(Repeater::make('rows')->dehydrated(false)->isDehydrated())->toBeFalse();
});

it('keeps a column-backed Tags field and drops a relationship one', function () {
    expect(Tags::make('tags')->isDehydrated())->toBeTrue()
        ->and(Tags::make('tags')->relationship('tags', 'name')->isDehydrated())->toBeFalse();
});

it('never writes a morph select under its own name', function () {
    expect(MorphToSelect::make('commentable')->isDehydrated())->toBeFalse();
});

it('is asked through the contract, not the class', function () {
    expect(TextInput::make('name'))->toBeInstanceOf(CanBeDehydrated::class)
        ->and(Repeater::make('rows'))->toBeInstanceOf(CanBeDehydrated::class);
});
