<?php

declare(strict_types=1);

use NyonCode\WireCore\Actions\Action;
use NyonCode\WireCore\Foundation\Schema\Section;
use NyonCode\WireCore\Infolists\Components\TextEntry;
use NyonCode\WireCore\Infolists\Infolist;

/*
 * `url()` on an infolist action.
 *
 * It is part of the Action vocabulary and the infolist partial used to ignore
 * it: an action given one rendered as a button that dispatched
 * `callInfolistAction` and, having no callback to run, did nothing at all. It is
 * also the only affordance that works on a `ViewPage`, which composes no host
 * trait and therefore has no such method to dispatch to.
 */

it('renders an action with a url as a link', function () {
    $html = Infolist::make()
        ->record(['name' => 'Ada'])
        ->schema([
            TextEntry::make('name')->actions([
                Action::make('open')->label('Open')->url('https://example.test/a.jpg', openInNewTab: true),
            ]),
        ])
        ->toHtml();

    expect($html)->toContain('<a')
        ->and($html)->toContain('href="https://example.test/a.jpg"')
        ->and($html)->toContain('target="_blank"')
        ->and($html)->toContain('rel="noopener"')
        ->and($html)->toContain('data-testid="infolist-action-open"')
        // A link has nothing to dispatch, so it carries no click and no spinner.
        ->and($html)->not->toContain('wire:click="callInfolistAction(\'open\'');
});

it('carries the extra attributes a link needs, such as download', function () {
    $html = Infolist::make()
        ->record(['name' => 'Ada'])
        ->schema([
            TextEntry::make('name')->actions([
                Action::make('download')
                    ->label('Download')
                    ->extraAttributes(['download' => 'portrait.jpg'])
                    ->url('/media/1/download'),
            ]),
        ])
        ->toHtml();

    expect($html)->toContain('download="portrait.jpg"')
        // Same tab: a download that opens a tab leaves an empty one behind.
        ->and($html)->not->toContain('target="_blank"');
});

it('leaves an action without a url as the button it was', function () {
    $html = Infolist::make()
        ->record(['name' => 'Ada'])
        ->schema([
            TextEntry::make('name')->actions([Action::make('copy')->label('Copy')]),
        ])
        ->toHtml();

    expect($html)->toContain('<button')
        ->and($html)->toContain('wire:click')
        ->and($html)->not->toContain('href=');
});

it('applies the same rule to a section header action', function () {
    // The header actions of a section go through the same partial, which is what
    // makes "open this file" expressible on a read-only page at all.
    $html = Infolist::make()
        ->record(['name' => 'Ada'])
        ->schema([
            Section::make('file')
                ->label('File')
                ->headerActions([Action::make('open')->label('Open')->url('/media/1')])
                ->schema([TextEntry::make('name')]),
        ])
        ->toHtml();

    expect($html)->toContain('href="/media/1"')
        ->and($html)->toContain('data-testid="infolist-action-open"');
});
