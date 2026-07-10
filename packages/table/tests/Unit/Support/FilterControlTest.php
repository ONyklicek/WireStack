<?php

declare(strict_types=1);

use NyonCode\WireTable\Support\FilterControl;

it('shares one base control style matching the wire-forms field look', function () {
    $base = FilterControl::classes();

    expect($base)->toContain('h-9')            // uniform height
        ->toContain('rounded-md')
        ->toContain('shadow-sm')               // matches the forms input
        ->toContain('border-gray-300')
        ->toContain('text-sm')
        ->toContain('focus:ring-primary-500')
        ->not->toContain('appearance-none');   // plain inputs keep their look
});

it('adds the chevron affordances for select-like controls', function () {
    $chevron = FilterControl::classes(withChevron: true);

    expect($chevron)->toContain('h-9')          // same shell as the base
        ->toContain('appearance-none')          // hide the native arrow
        ->toContain('bg-none')                  // strip the forms-plugin chevron
        ->toContain('pr-9');                    // room for the shared overlay
});
