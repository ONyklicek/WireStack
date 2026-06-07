<?php

declare(strict_types=1);
use Illuminate\Support\Facades\Blade;

it('renders outline icons end-to-end through the blade component', function () {
    $solid = Blade::render('<x-wire::icon name="x-mark" size="w-5 h-5" />');
    $outline = Blade::render('<x-wire::icon name="outline:x-mark" size="w-5 h-5" />');

    expect($solid)->toContain('viewBox="0 0 20 20"')->toContain('fill="currentColor"');
    expect($outline)->toContain('viewBox="0 0 24 24"')
        ->toContain('stroke="currentColor"')->toContain('stroke-width="1.5"');
});

it('forwards dynamic attributes (Alpine bindings) onto the rendered svg', function () {
    // Lets a single <x-wire::icon> carry behaviour (rotation, toggling) instead
    // of forcing a hand-written inline <svg> for interactive chrome.
    $svg = Blade::render(
        '<x-wire::icon name="chevron-down" size="" class="w-4 h-4" ::class="{ \'rotate-180\': open }" x-show="open" />'
    );

    expect($svg)->toContain('x-show="open"')
        ->toContain('rotate-180')
        ->toContain('class="w-4 h-4"');
});
