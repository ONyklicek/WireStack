<?php

declare(strict_types=1);

use NyonCode\WireCore\Foundation\Schema\EmptyState;

it('exposes a fluent icon / heading / description / actions API', function () {
    $empty = EmptyState::make()
        ->icon('outline:inbox')
        ->heading('No projects yet')
        ->description('Create your first project to get started.')
        ->actions(['<button>New</button>']);

    expect($empty->getIcon())->toBe('outline:inbox')
        ->and($empty->getHeading())->toBe('No projects yet')
        ->and($empty->getDescription())->toBe('Create your first project to get started.')
        ->and($empty->getActionsHtml())->toBe(['<button>New</button>']);
});

it('accepts closures for heading and description', function () {
    $empty = EmptyState::make()
        ->heading(fn () => 'Nothing here')
        ->description(fn () => 'Try again later.');

    expect($empty->getHeading())->toBe('Nothing here')
        ->and($empty->getDescription())->toBe('Try again later.');
});

it('renders the shared empty-state surface with icon, text and actions', function () {
    $html = EmptyState::make()
        ->icon('outline:inbox')
        ->heading('No projects yet')
        ->description('Create your first project.')
        ->actions(['<button data-test="cta">New project</button>'])
        ->toHtml();

    expect($html)->toContain('rounded-full')
        ->toContain('No projects yet')
        ->toContain('Create your first project.')
        ->toContain('data-test="cta"');
});
