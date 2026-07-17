<?php

declare(strict_types=1);

use NyonCode\WireCore\Foundation\Schema\Section;

/*
 * aside() was a dead setter: the view never read it, so a section documented as
 * "side-by-side layout" always stacked its heading above the fields.
 */

function renderSection(Section $section): string
{
    return (string) $section->render();
}

it('puts the heading beside the fields when aside', function () {
    $html = renderSection(Section::make('Profile')->description('Who you are')->aside()->schema([]));

    expect($html)->toContain('md:grid-cols-3')
        ->and($html)->toContain('md:col-span-2');
});

it('stacks the heading above the fields by default', function () {
    $html = renderSection(Section::make('Profile')->description('Who you are')->schema([]));

    expect($html)->not->toContain('md:col-span-2');
});

it('drops the heading margin only in aside mode, where the grid gap spaces it', function () {
    expect(renderSection(Section::make('P')->aside()->schema([])))->toContain('md:mb-0')
        ->and(renderSection(Section::make('P')->schema([])))->not->toContain('md:mb-0');
});
