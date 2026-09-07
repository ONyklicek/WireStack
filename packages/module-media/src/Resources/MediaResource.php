<?php

declare(strict_types=1);

namespace NyonCode\WireModuleMedia\Resources;

use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Actions\Action;
use NyonCode\WireCore\Core\Resources\Concerns\DescribesRecords;
use NyonCode\WireCore\Core\Resources\Contracts\DescribesResource;
use NyonCode\WireCore\Core\Resources\Contracts\ProvidesNavigation;
use NyonCode\WireCore\Core\Resources\Navigation\NavigationItem;
use NyonCode\WireCore\Foundation\Colors\Color;
use NyonCode\WireCore\Foundation\Routing\Contracts\ProvidesPages;
use NyonCode\WireCore\Foundation\Schema\Section;
use NyonCode\WireCore\Infolists\Components\BadgeEntry;
use NyonCode\WireCore\Infolists\Components\ImageEntry;
use NyonCode\WireCore\Infolists\Components\TextEntry;
use NyonCode\WireCore\Infolists\Contracts\ProvidesResourceInfolist;
use NyonCode\WireCore\Infolists\Infolist;
use NyonCode\WireForms\Components\FileUpload;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireForms\Contracts\ProvidesResourceForm;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WireModuleMedia\Models\Media;
use NyonCode\WireModuleMedia\Pages\CreateMedia;
use NyonCode\WireModuleMedia\Pages\ListMedia;
use NyonCode\WireModuleMedia\Pages\ViewMedia;
use NyonCode\WirePanels\Resources\Contracts\ProvidesResourceTable;
use NyonCode\WireTable\Columns\BadgeColumn;
use NyonCode\WireTable\Columns\ImageColumn;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Table;

/**
 * The file library.
 *
 * Upload, find, delete. Editing a file is deliberately absent: replacing the
 * bytes under a path that other records already point at is how a library
 * quietly changes what a published page shows, so a new file is a new row.
 *
 * The upload field is the framework's own `FileUpload`, so the disk, the
 * directory, the accepted types and the size limit are the ones an application
 * already configures — this resource only records what landed.
 */
class MediaResource implements DescribesResource, ProvidesNavigation, ProvidesPages, ProvidesResourceForm, ProvidesResourceInfolist, ProvidesResourceTable
{
    use DescribesRecords;

    public static function key(): string
    {
        return 'media';
    }

    public static function modelClass(): ?string
    {
        return Media::class;
    }

    public static function label(): string
    {
        return __('wire-module-media::messages.file');
    }

    public static function pluralLabel(): string
    {
        return __('wire-module-media::messages.files');
    }

    public static function pages(): array
    {
        return [
            'index' => ListMedia::class,
            'create' => CreateMedia::class,
            'view' => ViewMedia::class,
        ];
    }

    public static function navigation(): NavigationItem
    {
        return NavigationItem::make(fn (): string => __('wire-module-media::messages.files'))
            ->group((string) config('wire-module-media.navigation.group', 'content'))
            ->icon((string) config('wire-module-media.navigation.icon', 'outline:photo'));
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('path')
                    ->label(__('wire-module-media::messages.preview'))
                    ->disk((string) config('wire-module-media.disk', 'public')),
                TextColumn::make('name')->label(__('wire-module-media::messages.name'))->weight('medium')->searchable()->sortable(),
                BadgeColumn::make('mime_type')
                    ->label(__('wire-module-media::messages.type'))
                    ->color(Color::Gray)
                    ->searchable(),
                TextColumn::make('size')
                    ->label(__('wire-module-media::messages.size'))
                    ->state(static fn (Model $record): string => $record->humanSize())
                    // Sorted on the stored column, so the order is by bytes and
                    // not by the string "9 kB" sitting above "10 MB".
                    ->sortable(),
                TextColumn::make('created_at')->label(__('wire-module-media::messages.uploaded'))->dateTime()->sortable(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            // One section, and it is still worth having: it gives the upload a
            // card of its own rather than a control floating on the page
            // background, which is what every other screen in this stack shows.
            Section::make('upload')
                ->label(__('wire-module-media::messages.upload'))
                ->icon('outline:arrow-up-tray')
                ->schema([
                    FileUpload::make('path')
                        ->label(__('wire-module-media::messages.file'))
                        ->disk((string) config('wire-module-media.disk', 'public'))
                        ->directory((string) config('wire-module-media.directory', 'media'))
                        ->acceptedFileTypes((array) config('wire-module-media.accepts', []))
                        ->maxSize((int) config('wire-module-media.max_size', 10240))
                        ->columnSpanFull()
                        ->required(),
                    TextInput::make('name')
                        ->label(__('wire-module-media::messages.name'))
                        ->helperText(__('wire-module-media::messages.name_hint')),
                ]),
        ]);
    }

    /**
     * One file, read top to bottom: what it looks like, what it is, what
     * somebody wrote about it, and — folded away — where it actually lives.
     *
     * The flat version showed five entries in a column, three of them wrong: the
     * size was a raw byte count where the list beside it said "1.4 MB", the
     * upload date was an unformatted timestamp, and the preview was the 40px
     * thumbnail the grid uses, on a page whose entire job is to show you the
     * file. It also drew an `<img>` for a PDF, which is a broken-image icon.
     */
    public function infolist(Infolist $infolist): Infolist
    {
        $record = $infolist->getRecord();

        return $infolist->schema([
            // `make()` takes a key and `label()` the heading: passing the
            // translated string as the name sends it through `Str::headline()`,
            // which title-cases it — wrong in every language that is not English.
            Section::make('preview')
                ->label(__('wire-module-media::messages.preview'))
                ->icon('outline:photo')
                // Resolved once, from the record the infolist was handed: a
                // header action belongs to the section rather than to an entry,
                // so it has no record of its own to be given one later.
                ->headerActions(self::fileActions($record))
                ->schema([
                    ImageEntry::make('path')
                        ->label(__('wire-module-media::messages.preview'))
                        ->hiddenLabel()
                        // The scaled copy where there is one, and **nothing at
                        // all for a file that has no pixels** — a PDF rendered
                        // through `<img>` is a broken-image icon claiming the
                        // file is damaged.
                        ->state(static fn (Model $record): ?string => $record->isImage() ? $record->previewUrl() : null)
                        ->imageSize(240)
                        ->placeholder(__('wire-module-media::messages.no_preview'))
                        ->disk((string) config('wire-module-media.disk', 'public')),
                ]),

            Section::make('file')
                ->label(__('wire-module-media::messages.file'))
                ->icon('outline:document')
                ->columns(['default' => 1, 'sm' => 2, 'xl' => 3])
                ->schema([
                    TextEntry::make('name')
                        ->label(__('wire-module-media::messages.name'))
                        ->weight('semibold'),

                    BadgeEntry::make('mime_type')
                        ->label(__('wire-module-media::messages.type'))
                        ->color(Color::Gray)
                        ->placeholder('—'),

                    // The same string the list shows and the same one the
                    // library's own detail panel shows, because they are the
                    // same fact and `1468006` is not a size anybody reads.
                    TextEntry::make('size')
                        ->label(__('wire-module-media::messages.size'))
                        ->state(static fn (Model $record): string => $record->humanSize()),

                    TextEntry::make('dimensions')
                        ->label(__('wire-module-media::messages.dimensions'))
                        ->state(static fn (Model $record): ?string => $record->dimensions())
                        ->placeholder('—'),

                    TextEntry::make('folder')
                        ->label(__('wire-module-media::messages.folder'))
                        ->icon('outline:folder')
                        // A file in no folder is filed at the root, which is a
                        // place — not a missing value.
                        ->state(static fn (Model $record): string => (string) ($record->folder?->name
                            ?? __('wire-module-media::messages.library_root'))),

                    TextEntry::make('created_at')
                        ->label(__('wire-module-media::messages.uploaded'))
                        ->icon('outline:calendar-days')
                        ->dateTime()
                        ->placeholder('—'),
                ]),

            Section::make('description')
                ->label(__('wire-module-media::messages.description'))
                ->icon('outline:pencil-square')
                ->columns(['default' => 1, 'sm' => 2])
                ->schema([
                    // What a person wrote, as opposed to what the file is — and
                    // an empty alt is worth *saying*, because it is the one
                    // field here whose absence is a defect on every page the
                    // image appears on.
                    TextEntry::make('alt')
                        ->label(__('wire-module-media::messages.alt'))
                        ->placeholder(__('wire-module-media::messages.not_set')),

                    TextEntry::make('title')
                        ->label(__('wire-module-media::messages.title'))
                        ->placeholder(__('wire-module-media::messages.not_set')),
                ]),

            // Collapsed, the way the audit entry's request section is: this is
            // the half nobody opens the page for, and the half that answers the
            // question you only ask when something is wrong.
            Section::make('storage')
                ->label(__('wire-module-media::messages.storage'))
                ->icon('outline:server-stack')
                ->collapsed()
                ->columns(['default' => 1, 'sm' => 2])
                ->schema([
                    TextEntry::make('disk')->label(__('wire-module-media::messages.disk')),
                    TextEntry::make('path')
                        ->label(__('wire-module-media::messages.path'))
                        ->copyable(),
                ]),
        ]);
    }

    /**
     * Open and download, where the file can be reached at all.
     *
     * Links rather than callbacks, and that is the only thing that works here: a
     * {@see ViewMedia} is a `ViewPage`, which composes no host trait, so it has
     * no `callInfolistAction()` for a button to dispatch to. `downloadUrl()`
     * prefers the streamed route, because a private disk has no public address
     * and `download` on a missing href is a link that does nothing.
     *
     * @return array<int, Action>
     */
    protected static function fileActions(mixed $record): array
    {
        if (! $record instanceof Media) {
            return [];
        }

        $url = $record->url();

        if ($url === null) {
            return [];
        }

        return [
            Action::make('open')
                ->label(__('wire-module-media::messages.open'))
                ->icon('outline:arrow-top-right-on-square')
                ->outlined()
                ->url($url, openInNewTab: true),

            Action::make('download')
                ->label(__('wire-module-media::messages.download'))
                ->icon('outline:arrow-down-tray')
                ->outlined()
                ->extraAttributes(['download' => $record->name])
                ->url((string) $record->downloadUrl()),
        ];
    }
}
