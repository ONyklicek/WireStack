<?php

declare(strict_types=1);

use App\Infolists\Components\MoneyEntry;
use App\Wire\Actions\ArchiveAction;
use App\Wire\Actions\ArchiveBulkAction;
use Illuminate\Support\Facades\File;
use NyonCode\WireCore\Actions\BulkAction;

/*
 * make:wire-entry and make:wire-action — loaded and used, not just read.
 */
afterEach(function () {
    File::deleteDirectory(app_path('Infolists'));
    File::deleteDirectory(app_path('Wire'));
    File::deleteDirectory(resource_path('views/infolists'));
});

it('writes an entry that renders its own view', function () {
    $this->artisan('make:wire-entry', ['name' => 'Money'])
        ->expectsOutputToContain('Created [App\\Infolists\\Components\\MoneyEntry]')
        ->assertSuccessful();

    require_once app_path('Infolists/Components/MoneyEntry.php');

    $html = MoneyEntry::make('total')->label('Total')->record(['total' => '99 EUR'])->render()->render();

    expect(MoneyEntry::make('total')->render()->name())->toBe('infolists.entries.money')
        ->and($html)->toContain('99 EUR')->toContain('Total')
        ->and(MoneyEntry::make('total')->placeholder('n/a')->record(['total' => null])->render()->render())->toContain('n/a');
});

it('writes a configured action with no view, named after the class', function () {
    $this->artisan('make:wire-action', ['name' => 'Archive'])
        ->expectsOutputToContain('Created [App\\Wire\\Actions\\ArchiveAction]')
        ->expectsOutputToContain('->actions([ArchiveAction::make()])')
        ->doesntExpectOutputToContain('View')
        ->assertSuccessful();

    require_once app_path('Wire/Actions/ArchiveAction.php');

    $action = ArchiveAction::make();

    expect($action->getName())->toBe('archive')
        ->and($action->getLabel())->toBe('Archive')
        ->and($action->getActionCallback())->toBeInstanceOf(Closure::class)
        ->and(ArchiveAction::make('stash')->getName())->toBe('stash')
        ->and(File::isDirectory(resource_path('views/wire')))->toBeFalse();
});

it('writes a bulk action with --bulk', function () {
    $this->artisan('make:wire-action', ['name' => 'Archive', '--bulk' => true])
        ->expectsOutputToContain('->bulkActions([ArchiveBulkAction::make()])')
        ->assertSuccessful();

    require_once app_path('Wire/Actions/ArchiveBulkAction.php');

    expect(ArchiveBulkAction::make())->toBeInstanceOf(BulkAction::class)
        ->and(ArchiveBulkAction::make()->getName())->toBe('archive');
});
