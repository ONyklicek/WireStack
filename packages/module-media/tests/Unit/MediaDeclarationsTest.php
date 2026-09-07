<?php

declare(strict_types=1);

use NyonCode\WireModuleMedia\MediaModule;
use NyonCode\WireModuleMedia\Models\Media;
use NyonCode\WireModuleMedia\Models\MediaFolder;
use NyonCode\WireModuleMedia\Pages\CreateMedia;
use NyonCode\WireModuleMedia\Pages\ListMedia;
use NyonCode\WireModuleMedia\Pages\ViewMedia;
use NyonCode\WireModuleMedia\Resources\MediaResource;
use NyonCode\WireTable\Table;

it('names its pages, its label and its menu entry', function () {
    expect(MediaResource::pages())->toBe([
        'index' => ListMedia::class,
        'create' => CreateMedia::class,
        'view' => ViewMedia::class,
    ])
        ->and(MediaResource::modelClass())->toBe(Media::class)
        ->and(MediaResource::label())->not->toBe('')
        ->and(MediaResource::pluralLabel())->not->toBe('')
        ->and(MediaResource::navigation()->getGroup())->toBe('content');
});

it('lets an application rename the menu group it ships', function () {
    config()->set('wire-module-media.navigation.label', 'Assets');

    expect((new MediaModule)->navigation()?->getLabel())->toBe('Assets');
});

it('falls back to its own heading when the application renames nothing', function () {
    expect((new MediaModule)->navigation()?->getLabel())->not->toBe('');
});

it('reads the table name from configuration', function () {
    config()->set('wire-module-media.table', 'assets');

    expect((new Media)->getTable())->toBe('assets');
});

it('reads the folders table name from configuration too', function () {
    config()->set('wire-module-media.folders_table', 'asset_folders');

    expect((new MediaFolder)->getTable())->toBe('asset_folders');
});

it('still describes a table, for anything that wants the files as rows', function () {
    // The library's own screen is the manager rather than this table, but the
    // resource keeps describing one: a relation manager, an application's own
    // list page, or a picker all want the files as rows, and none of them wants
    // a folder tree. Dropping the declaration would take that away to tidy up
    // something nobody was looking at.
    $table = (new MediaResource)->table(new Table);

    expect(array_map(
        static fn (object $column): string => $column->getName(),
        $table->getColumns(),
    ))->toBe(['path', 'name', 'mime_type', 'size', 'created_at']);
});
