<?php

declare(strict_types=1);

namespace NyonCode\WireModuleMedia\Livewire;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use NyonCode\WireCore\Notifications\Concerns\InteractsWithNotifications;
use NyonCode\WireModuleMedia\Actions\ReplaceOriginal;
use NyonCode\WireModuleMedia\Actions\StoreUpload;
use NyonCode\WireModuleMedia\Exceptions\MediaException;
use NyonCode\WireModuleMedia\Models\Media;
use NyonCode\WireModuleMedia\Models\MediaFolder;
use NyonCode\WireModuleMedia\Support\MediaAccess;
use NyonCode\WireModuleMedia\Support\MediaUsage;

/**
 * The library, as something you can actually work in.
 *
 * What was here before was a table of rows with an upload form beside it, which
 * is a *list of files* rather than a place to keep them: no folders, no way to
 * move anything, no way to drop a file onto the page. This is the file manager
 * that list implied — a folder tree, a grid, drag-and-drop in both senses (files
 * from the desktop onto the grid, files from the grid onto a folder), rename,
 * move, and delete with the selection.
 *
 * **Every refusal comes from the model.** Folders guard their own names, their
 * own cycles and their own emptiness and throw {@see MediaException}; this
 * component's job is to turn that into a notification rather than to re-derive
 * the rule. That is what keeps a console command or somebody else's code from
 * getting a different answer than the screen does.
 *
 * The uploads themselves are Livewire's `WithFileUploads` and the disk is the
 * one an application already configured — the module adds where a file is
 * *filed*, never where it is stored.
 */
class MediaManager extends Component
{
    use InteractsWithNotifications;
    use WithFileUploads;
    use WithPagination;

    /** The folder being looked at, or null for the root. */
    public ?int $folderId = null;

    public string $search = '';

    /** `grid` or `list` — remembered per person by the view, not by the server. */
    public string $view = 'grid';

    /**
     * `column:direction` — what the list's headers set and the toolbar picks from.
     *
     * One property rather than two, because it is one thing in the URL and a
     * filtered view is something you send to somebody. The four words it used to
     * hold (`newest`, `oldest`, `name`, `largest`) still work: a link somebody
     * bookmarked before the list had headers must not land on an unsorted page.
     */
    public string $sort = 'created_at:desc';

    /** What the four presets meant, so an old URL keeps meaning it. */
    private const SORT_ALIASES = [
        'newest' => 'created_at:desc',
        'oldest' => 'created_at:asc',
        'name' => 'name:asc',
        'largest' => 'size:desc',
    ];

    /**
     * Columns a header may sort by.
     *
     * An allow-list because `$sort` is a public property and anything on the
     * page can set it: without this, a column name is a fragment of SQL somebody
     * else chose.
     */
    private const SORTABLE = ['name', 'mime_type', 'size', 'width', 'folder_id', 'created_at'];

    /**
     * Narrow to one kind of file: `image`, `document`, or everything.
     *
     * Coarse on purpose. A library's filter is for "show me the pictures", and a
     * list of eleven mime types is a list nobody reads — the search box is what
     * finds a specific one.
     */
    public string $type = '';

    /** @var array<int, int> */
    public array $selected = [];

    /**
     * What was cut, waiting to be pasted somewhere.
     *
     * Separate from the selection because they answer different questions: the
     * selection is what you are looking at, the clipboard is what you decided to
     * move. Opening another folder changes the first and must not change the
     * second.
     *
     * @var array<int, int>
     */
    public array $clipboard = [];

    /** @var array<int, UploadedFile> */
    public array $uploads = [];

    /**
     * What happened to each file of the last batch.
     *
     * "3 failed" in a toast is a sentence that makes a person who dropped forty
     * photographs go and find the three themselves. This is the same
     * information, kept instead of counted: a row per file, with its own
     * outcome and, where it did not work, the reason.
     *
     * Component state, so it survives opening another folder — which is exactly
     * what somebody does while a long batch is still going.
     *
     * @var array<int, array{name: string, state: string, media: int|null}>
     */
    public array $tray = [];

    /** The image open in the editor, if any. */
    public ?int $editingId = null;

    /** `new` or `replace` — set from the editor immediately before it uploads. */
    public string $editorIntent = 'new';

    /**
     * What the editor produced.
     *
     * A property of its own rather than a second entry in `$uploads`, because
     * what happens to it is not what happens to a dropped file: it may become a
     * new row, or it may be written over an existing one.
     */
    public mixed $editorUpload = null;

    public string $newFolderName = '';

    public bool $creatingFolder = false;

    /** The file whose name is being edited, if any. */
    public ?int $renamingId = null;

    public string $renamingName = '';

    /** The file whose details are open in the side panel. */
    public ?int $detailId = null;

    public string $detailAlt = '';

    public string $detailTitle = '';

    /**
     * Picking mode: the library used as a chooser rather than as a screen.
     *
     * The same component either way, because a picker that is a *different*
     * component is a picker that slowly stops matching the library — different
     * empty state, different search, folders that work in one and not the other.
     * What changes is what a tile does when it is clicked and whether there is a
     * button to confirm with.
     *
     * `#[Locked]` because it is also what {@see self::mayMutate()} reads, and a
     * flag that decides an authorization question must not be one the browser
     * can send back. Nothing sets it at runtime — each class declares its own
     * value — so locking costs nothing.
     */
    #[Locked]
    public bool $picking = false;

    public bool $multiple = false;

    /** Only files whose mime type starts with one of these, when picking. */
    public string $accepts = '';

    /** @var array<string, string> */
    protected $queryString = [
        'folderId' => ['as' => 'folder', 'except' => null],
        'search' => ['except' => ''],
        // In the URL, so a filtered view is a thing you can send to somebody.
        'sort' => ['except' => 'created_at:desc'],
        'type' => ['except' => ''],
    ];

    public function mount(?int $folder = null): void
    {
        $this->folderId = $folder;
    }

    /* ── One file's details ───────────────────────────────────────────────── */

    /**
     * Open the panel for one file.
     *
     * The alt text and the title are loaded into their own properties rather
     * than bound through the model: a panel bound straight to a row writes on
     * every keystroke, and the library is the wrong place to discover that.
     */
    public function showDetail(int $id): void
    {
        $media = Media::findOrFail($id);

        // An id is not permission. The grid only draws tiles the policy allows,
        // but this method is reachable without the grid.
        if (MediaAccess::denies('view', $media)) {
            $this->notifyError(__('wire-module-media::messages.not_allowed'));

            return;
        }

        $this->detailId = $id;
        $this->detailAlt = (string) $media->alt;
        $this->detailTitle = (string) $media->title;
    }

    public function closeDetail(): void
    {
        $this->detailId = null;
    }

    public function saveDetail(): void
    {
        if ($this->detailId === null) {
            return;
        }

        // Loaded before the question, not after: `update` on this library is not
        // the same question as `update` on this file, and a policy that answers
        // per record has nothing to answer about until the record is in hand.
        $media = Media::findOrFail($this->detailId);

        if ($this->refuse('update', $media)) {
            return;
        }

        $media->update([
            'alt' => trim($this->detailAlt) ?: null,
            'title' => trim($this->detailTitle) ?: null,
        ]);

        $this->notifySuccess(__('wire-module-media::messages.details_saved'));
    }

    /* ── The editor ───────────────────────────────────────────────────────── */

    /**
     * Open the editor on one image.
     *
     * Asks `update` rather than `replace`: opening the editor is not yet a
     * decision to overwrite anything, and the file that comes out of it may
     * perfectly well be a new row. The larger question is asked at the moment
     * it is actually being answered — see {@see self::updatedEditorUpload()}.
     */
    public function openEditor(int $id): void
    {
        $media = Media::findOrFail($id);

        if ($this->refuse('update', $media)) {
            return;
        }

        // There are no pixels to resample in an SVG, and `processImage` hands
        // such a file straight back — so the button is not offered and this
        // refuses as well, because a hidden button is not a check.
        if (! $media->isImage() || $media->mime_type === 'image/svg+xml') {
            $this->notifyError(__('wire-module-media::messages.not_editable'));

            return;
        }

        $this->editingId = $id;
        $this->editorIntent = 'new';
    }

    public function closeEditor(): void
    {
        $this->editingId = null;
        $this->editorUpload = null;
    }

    public function editing(): ?Media
    {
        return $this->editingId === null ? null : Media::find($this->editingId);
    }

    /**
     * The edited picture has arrived. What happens to it is `editorIntent`.
     *
     * Two outcomes and two buttons on the screen, never a checkbox that changes
     * what one button does — that is how a person replaces a published
     * photograph while believing they exported a crop.
     *
     * **A new file is the ordinary one** and goes through {@see StoreUpload}
     * unchanged, so hashing, duplicate refusal, metadata read from the disk and
     * the thumbnail are all the code that already existed. **A replacement**
     * writes new bytes under the same row at the same path
     * ({@see ReplaceOriginal}) and is asked as its own ability. See ADR 0035.
     */
    public function updatedEditorUpload(): void
    {
        $original = $this->editing();

        if ($this->editorUpload === null || $original === null) {
            $this->closeEditor();

            return;
        }

        $this->editorIntent === 'replace'
            ? $this->replaceOriginal($original)
            : $this->storeDerivative($original);

        $this->closeEditor();
    }

    protected function replaceOriginal(Media $original): void
    {
        if ($this->refuse('replace', $original)) {
            return;
        }

        app(ReplaceOriginal::class)($original, $this->editorUpload)
            ? $this->notifySuccess(__('wire-module-media::messages.replaced'))
            // The old file is still there and the row still describes it: a
            // failed replacement must not read as a lost original.
            : $this->notifyError(__('wire-module-media::messages.replace_failed'));
    }

    protected function storeDerivative(Media $original): void
    {
        if ($this->refuse('create')) {
            return;
        }

        $media = (new StoreUpload)($this->editorUpload, $original->folder, $duplicate);

        if ($media === null) {
            $this->notifyError(trans_choice('wire-module-media::messages.upload_failed', 1, ['count' => 1]));

            return;
        }

        if ($duplicate) {
            // The same crop, made twice. The library hands back the row it has,
            // and saying so is better than a second tile that never appears.
            $this->notifyInfo(trans_choice('wire-module-media::messages.duplicate', 1, ['count' => 1]));

            return;
        }

        $media->update([
            'derived_from_id' => $original->getKey(),
            'name' => $this->derivedName($original),
            // A crop of a photograph is still that photograph, so what somebody
            // wrote about it is still true. Making them write it again is how
            // half the copies end up with none.
            'alt' => $original->alt,
        ]);

        $this->notifySuccess(__('wire-module-media::messages.derived_saved'));
    }

    protected function derivedName(Media $original): string
    {
        $name = (string) $original->name;
        $extension = pathinfo($name, PATHINFO_EXTENSION);
        $base = $extension === '' ? $name : substr($name, 0, -(strlen($extension) + 1));

        $suffix = ' ('.__('wire-module-media::messages.derived_suffix').')';

        return $extension === '' ? $base.$suffix : $base.$suffix.'.'.$extension;
    }

    /* ── Picking ──────────────────────────────────────────────────────────── */

    /**
     * Answer whoever opened the picker, with what was chosen.
     *
     * A browser event rather than a Livewire one, because the thing waiting for
     * it may not be a Livewire component at all — the rich text editor is Alpine
     * and TipTap, and it is the case this was built for.
     */
    public function confirmPick(): void
    {
        $chosen = Media::query()
            ->whereIn('id', $this->selected)
            ->get()
            ->map(static fn (Media $media): array => [
                'id' => $media->getKey(),
                'url' => $media->url(),
                'name' => $media->name,
                'alt' => $media->altText(),
                'title' => $media->title,
                'mime' => $media->mime_type,
                'width' => $media->width,
                'height' => $media->height,
            ])
            ->all();

        $this->dispatch('wire-media-picked', files: $chosen);

        $this->selected = [];
    }

    /** Clicking a tile while picking selects it, rather than opening its details. */
    public function pick(int $id): void
    {
        if (! $this->multiple) {
            $this->selected = [$id];
            $this->confirmPick();

            return;
        }

        $this->selected = in_array($id, $this->selected, true)
            ? array_values(array_diff($this->selected, [$id]))
            : [...$this->selected, $id];
    }

    /* ── Where we are ─────────────────────────────────────────────────────── */

    public function currentFolder(): ?MediaFolder
    {
        return $this->folderId === null ? null : MediaFolder::find($this->folderId);
    }

    /** @return Collection<int, MediaFolder> */
    public function breadcrumb(): Collection
    {
        $folder = $this->currentFolder();

        /** @var Collection<int, MediaFolder> $empty */
        $empty = new Collection;

        return $folder === null ? $empty : $folder->breadcrumb();
    }

    /**
     * The whole tree, in one query, as a parent-keyed map the view walks.
     *
     * One query rather than one per level: a sidebar that lazy-loads each branch
     * makes a tree of thirty folders thirty round trips, and this tree is small
     * enough to arrive whole.
     *
     * @return array<int|string, array<int, MediaFolder>>
     */
    public function tree(): array
    {
        $byParent = [];

        foreach (MediaFolder::query()->orderBy('name')->get() as $folder) {
            $byParent[$folder->parent_id ?? 'root'][] = $folder;
        }

        return $byParent;
    }

    /**
     * How many files are filed in each folder, and how many in the root.
     *
     * One grouped query for the whole tree, keyed by folder id with `root` for
     * the library itself. Asking per node would be one query per folder on a
     * sidebar that is drawn on every screen of the library.
     *
     * Files **in** the folder, not in its subtree: a number that counts a branch
     * disagrees with the number of tiles you see when you open it, and the tiles
     * are the thing being counted.
     *
     * @return array<int|string, int>
     */
    public function folderCounts(): array
    {
        return Media::query()
            ->selectRaw('folder_id, count(*) as total')
            ->groupBy('folder_id')
            ->get()
            ->mapWithKeys(static fn ($row): array => [$row->folder_id ?? 'root' => (int) $row->total])
            ->all();
    }

    public function openFolder(?int $id): void
    {
        $this->folderId = $id;
        $this->selected = [];
        $this->resetPage();
    }

    /* ── Folders ──────────────────────────────────────────────────────────── */

    public function createFolder(): void
    {
        if ($this->refuse('create')) {
            return;
        }

        $this->guarded(function (): void {
            $folder = MediaFolder::createIn($this->currentFolder(), $this->newFolderName);

            $this->newFolderName = '';
            $this->creatingFolder = false;

            $this->notifySuccess(__('wire-module-media::messages.folder_created', ['name' => $folder->name]));
        });
    }

    public function renameFolder(int $id, string $name): void
    {
        if ($this->refuse('update')) {
            return;
        }

        $this->guarded(function () use ($id, $name): void {
            MediaFolder::findOrFail($id)->rename($name);

            $this->notifySuccess(__('wire-module-media::messages.folder_renamed'));
        });
    }

    public function deleteFolder(int $id): void
    {
        if ($this->refuse('delete')) {
            return;
        }

        $this->guarded(function () use ($id): void {
            $folder = MediaFolder::findOrFail($id);
            $parentId = $folder->parent_id;

            $folder->deleteEmpty();

            if ($this->folderId === $id) {
                $this->openFolder($parentId);
            }

            $this->notifySuccess(__('wire-module-media::messages.folder_deleted'));
        });
    }

    /** Drag a folder onto another folder. */
    public function moveFolder(int $id, ?int $intoId): void
    {
        if ($this->refuse('update')) {
            return;
        }

        $this->guarded(function () use ($id, $intoId): void {
            MediaFolder::findOrFail($id)->moveTo($intoId === null ? null : MediaFolder::findOrFail($intoId));

            $this->notifySuccess(__('wire-module-media::messages.folder_moved'));
        });
    }

    /* ── Files ────────────────────────────────────────────────────────────── */

    /**
     * Store whatever was dropped or picked, into the folder being looked at.
     *
     * The disk, the directory and the limits are the application's configuration
     * and this does not second-guess them. What it adds is the row: the original
     * name, the mime type and the size read *from the disk* rather than from
     * what the browser claimed, because a browser's word about a file is a claim.
     */
    public function updatedUploads(): void
    {
        if ($this->refuse('create')) {
            $this->uploads = [];

            return;
        }

        $store = new StoreUpload;
        $folder = $this->currentFolder();
        $stored = 0;

        foreach ($this->uploads as $upload) {
            $media = $store($upload, $folder, $duplicate);

            $this->tray[] = [
                'name' => $upload->getClientOriginalName(),
                'state' => match (true) {
                    $media === null => 'failed',
                    $duplicate => 'duplicate',
                    default => 'stored',
                },
                // Kept on the duplicate row too, and that is the point of it: a
                // person who dropped a file and saw no new tile wants to be
                // shown the one the library already had.
                'media' => $media?->getKey(),
            ];

            if ($media !== null && ! $duplicate) {
                $stored++;
            }
        }

        $this->uploads = [];

        // One line, because a screen reader and a person looking elsewhere both
        // need to be told something landed. The detail is in the tray.
        if ($stored > 0) {
            $this->notifySuccess(trans_choice('wire-module-media::messages.uploaded_count', $stored, ['count' => $stored]));
        }
    }

    /**
     * Forget one file's row, so a retry of it does not read as two attempts.
     *
     * The browser sends the file again on its own — the server cannot re-send
     * one it never received — so all this does is take the failed row away
     * before the fresh one arrives.
     */
    public function forgetTrayEntry(string $name): void
    {
        $this->tray = array_values(array_filter(
            $this->tray,
            static fn (array $entry): bool => $entry['name'] !== $name || $entry['state'] !== 'failed',
        ));
    }

    /** Put the tray away. It says nothing that is not on the screen behind it. */
    public function dismissTray(): void
    {
        $this->tray = [];
    }

    /** @return array<string, int> */
    public function trayTotals(): array
    {
        $totals = ['stored' => 0, 'duplicate' => 0, 'failed' => 0];

        foreach ($this->tray as $entry) {
            $totals[$entry['state']] = ($totals[$entry['state']] ?? 0) + 1;
        }

        return $totals;
    }

    public function startRenaming(int $id): void
    {
        $media = Media::findOrFail($id);

        $this->renamingId = $id;
        $this->renamingName = (string) $media->name;
    }

    public function saveRename(): void
    {
        if ($this->renamingId === null) {
            return;
        }

        $media = Media::findOrFail($this->renamingId);

        if ($this->refuse('update', $media)) {
            return;
        }

        $media->rename($this->renamingName);

        $this->renamingId = null;

        $this->notifySuccess(__('wire-module-media::messages.renamed'));
    }

    /** Drag a file — or the whole selection — onto a folder. */
    public function moveTo(?int $folderId, ?int $mediaId = null): void
    {
        $this->moveIds($mediaId === null ? $this->selected : [$mediaId], $folderId);
    }

    /**
     * Move these files into that folder.
     *
     * The body of every move, because there are now three ways to ask for one —
     * a drag, the toolbar's list, and paste — and three copies of "check, move,
     * clear, say so" is two too many.
     *
     * @param  array<int, int>  $ids
     */
    protected function moveIds(array $ids, ?int $folderId): void
    {
        $movable = $this->permitted($ids, 'update');

        if ($movable->isEmpty()) {
            return;
        }

        $folder = $folderId === null ? null : MediaFolder::find($folderId);

        Media::query()->whereKey($movable->modelKeys())->update(['folder_id' => $folder?->id]);

        $this->selected = [];

        $moved = $movable->count();

        $this->notifySuccess(trans_choice('wire-module-media::messages.moved', $moved, ['count' => $moved]));
    }

    /**
     * Cut the selection, to be pasted into another folder.
     *
     * The keyboard equivalent of the drag, and not a lesser one: a drag across a
     * tree thirty folders deep is a gesture not everybody can make, and on a
     * touch screen it is not a gesture at all.
     */
    public function cutSelection(): void
    {
        if ($this->selected === []) {
            return;
        }

        $this->clipboard = $this->selected;

        $this->notifyInfo(trans_choice('wire-module-media::messages.cut', count($this->clipboard), ['count' => count($this->clipboard)]));
    }

    public function pasteHere(): void
    {
        $ids = $this->clipboard;

        // Emptied before the move rather than after: a paste that fails its
        // permission check must not leave a clipboard that pastes again on the
        // next keystroke.
        $this->clipboard = [];

        $this->moveIds($ids, $this->folderId);
    }

    /**
     * The destination picked from the toolbar's list.
     *
     * A method of its own rather than `moveTo($event.target.value)` in the
     * markup, because the select carries three different things in one string:
     * the placeholder, the root, and a folder id. Deciding which is which in a
     * Blade attribute is how that becomes a folder called "0".
     */
    public function moveSelectedTo(string $folder): void
    {
        if ($folder === '__') {
            return;
        }

        $this->moveTo($folder === '' ? null : (int) $folder);
    }

    public function deleteSelected(): void
    {
        $count = 0;

        // One at a time, not a mass delete: the model's `deleted` hook is what
        // removes the file from the disk, and a mass delete does not fire it —
        // which would leave the bytes behind with no row pointing at them.
        foreach ($this->permitted($this->selected, 'delete') as $media) {
            $media->delete();
            $count++;
        }

        $this->selected = [];

        if ($count > 0) {
            $this->notifySuccess(trans_choice('wire-module-media::messages.deleted', $count, ['count' => $count]));
        }
    }

    public function deleteOne(int $id): void
    {
        $this->selected = [$id];
        $this->deleteSelected();
    }

    public function toggleView(): void
    {
        $this->view = $this->view === 'grid' ? 'list' : 'grid';
    }

    public function updatedSort(): void
    {
        $this->resetPage();
    }

    /** The column being sorted by, resolved through the aliases and the allow-list. */
    public function sortColumn(): string
    {
        $sort = self::SORT_ALIASES[$this->sort] ?? $this->sort;
        $column = strtok($sort, ':') ?: 'created_at';

        return in_array($column, self::SORTABLE, true) ? $column : 'created_at';
    }

    public function sortDirection(): string
    {
        $sort = self::SORT_ALIASES[$this->sort] ?? $this->sort;

        return str_ends_with($sort, ':asc') ? 'asc' : 'desc';
    }

    /**
     * What clicking a header does: sort by it, or turn it around.
     *
     * A new column starts ascending except for the two where nobody means that
     * — the newest files and the largest ones are what a person clicking
     * "Uploaded" or "Size" is looking for.
     */
    public function sortBy(string $column): void
    {
        if (! in_array($column, self::SORTABLE, true)) {
            return;
        }

        $this->sort = $this->sortColumn() === $column
            ? $column.':'.($this->sortDirection() === 'asc' ? 'desc' : 'asc')
            : $column.':'.(in_array($column, ['created_at', 'size'], true) ? 'desc' : 'asc');

        $this->resetPage();
    }

    public function updatedType(): void
    {
        $this->resetPage();
    }

    /**
     * Select everything on this page, or clear the selection.
     *
     * **This page, not the whole library.** A checkbox that silently selects
     * four thousand rows behind a `Delete` button is the oldest way a bulk
     * action becomes an accident — what is selected has to be what is on screen.
     *
     * @param  array<int, int>  $ids
     */
    public function toggleAll(array $ids): void
    {
        $this->selected = $this->allSelected($ids) ? [] : array_values($ids);
    }

    /** @param array<int, int> $ids */
    public function allSelected(array $ids): bool
    {
        return $ids !== [] && array_diff($ids, $this->selected) === [];
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    /**
     * Whether anything on this screen is still waiting for a thumbnail.
     *
     * The screen refreshes itself while this is true, and stops the moment it is
     * not — which is the whole of the polling policy, written as a question the
     * view can ask rather than as an interval somebody has to remember to switch
     * off.
     *
     * Three conditions, and all three are needed to avoid a page that polls for
     * ever. Thumbnails have to be **switched on** and made **on a queue** —
     * inline they already exist by the time this renders, so a null one is a
     * file that cannot have one. And the file has to be **recent**: a PNG that
     * GD refused two months ago is null for good, and a screen open on that
     * folder would otherwise ask about it every few seconds until the tab is
     * closed.
     */
    public function awaitingThumbnails(LengthAwarePaginator $files): bool
    {
        $queued = config('wire-module-media.thumbnails.queue', false);

        if (! config('wire-module-media.thumbnails.enabled', true) || $queued === false || $queued === null) {
            return false;
        }

        return collect($files->items())->contains(
            static fn (Media $media): bool => $media->isImage()
                && $media->thumb_path === null
                && $media->created_at?->gt(now()->subMinutes(5)) === true,
        );
    }

    /**
     * The sentence in front of a delete, with what it is about to break in it.
     *
     * Composed here rather than in the view because it is two different
     * sentences — one for a file nothing is known to use, one for a file
     * something is — and choosing between them in a Blade attribute is how the
     * warning ends up saying "0".
     *
     * The count is a **floor** and the sentence says so: a URL pasted into a
     * template by hand is invisible to the library and always will be
     * ({@see MediaUsage}).
     */
    public function deleteWarning(int $uses): string
    {
        return $uses === 0
            ? __('wire-module-media::messages.confirm_delete')
            : __('wire-module-media::messages.confirm_delete_used', ['count' => $uses]);
    }

    public function render(): View
    {
        /** @var LengthAwarePaginator<int, Media> $files */
        $query = Media::query()
            // Nothing at all to somebody the policy refuses. A filter rather
            // than an early return, because the screen around it — the folder
            // tree, the empty state, the upload zone — is the same screen either
            // way, and a page that renders half of itself is harder to read than
            // one that renders empty.
            ->when(! MediaAccess::allows('viewAny'), fn ($q) => $q->whereRaw('1 = 0'))
            // The list draws a folder name per row, and without this that is a
            // query per row on a page of twenty-four.
            ->with('folder')
            ->when($this->search === '', fn ($q) => $q->where('folder_id', $this->folderId))
            // A search looks through the whole library on purpose. Searching
            // inside the folder you happen to be standing in is how a file
            // nobody can remember filing stays lost.
            ->when($this->search !== '', fn ($q) => $q->where('name', 'like', '%'.$this->search.'%'))
            // Only what the caller can use. A picker asked for an image that
            // offers a PDF is a picker that produces a broken page later, and
            // "later" is after somebody published it.
            // Grouped, and that is not style: `when()` hands the callback this
            // same builder, so an `orWhere` written straight onto it binds at
            // the top level — and `AND` binding tighter than `OR` then detaches
            // the folder and the search that came before it. Two accepted kinds
            // inside a folder listed every file of the second kind in the
            // library.
            ->when($this->accepts !== '', fn ($q) => $q->where(function ($inner) {
                foreach (explode(',', $this->accepts) as $index => $prefix) {
                    $prefix = trim($prefix);
                    $index === 0
                        ? $inner->where('mime_type', 'like', $prefix.'%')
                        : $inner->orWhere('mime_type', 'like', $prefix.'%');
                }
            }))
            ->when($this->type === 'image', fn ($q) => $q->where('mime_type', 'like', 'image/%'))
            ->when($this->type === 'document', fn ($q) => $q->where(
                fn ($inner) => $inner->whereNull('mime_type')->orWhere('mime_type', 'not like', 'image/%'),
            ))
            ->orderBy($this->sortColumn(), $this->sortDirection())
            // A second key, so a page boundary does not shuffle: forty files
            // uploaded in the same second are otherwise in whatever order the
            // database felt like, and page two can repeat a row from page one.
            ->orderBy('id', 'desc');

        $files = $query->paginate(24);

        // Loaded once and passed to both the panel and its usage list: two
        // lookups of the same row is one more than the panel needs.
        $detail = $this->detailId === null ? null : Media::find($this->detailId);

        return view('wire-module-media::manager', [
            'files' => $files,
            // One grouped query for the page rather than one per tile: a
            // library of forty files must not be forty extra queries to say
            // what a delete would break.
            'usageCounts' => MediaUsage::countsFor(
                collect($files->items())->map(static fn (Media $media): int => (int) $media->getKey())->all(),
            ),
            'detailUsages' => $detail === null ? [] : MediaUsage::for($detail),
            'folders' => $this->tree(),
            'folderCounts' => $this->folderCounts(),
            // Flat and by full path, for the "move to" list: a nested <select>
            // is not a thing, and a path reads the same as the tree does.
            'allFolders' => MediaFolder::query()->orderBy('path')->get(),
            'crumbs' => $this->breadcrumb(),
            'folder' => $this->currentFolder(),
            'detail' => $detail,
            'editing' => $editing = $this->editing(),
            // The replacement warning's number, and the reason ADR 0034 had to
            // land first: without it this is always zero and always reassuring.
            'editingUses' => $editing === null ? 0 : MediaUsage::countFor($editing),
            'awaitingThumbnails' => $this->awaitingThumbnails($files),
        ]);
    }

    /**
     * Run something that may refuse, and show the refusal.
     *
     * The model throws {@see MediaException} with a sentence saying what it will
     * not do and why; this turns that into a toast. Nothing here re-checks the
     * rule, which is what keeps the screen and every other caller answering the
     * same way.
     */
    /**
     * Whether the current user may not do that — and, if so, say so once.
     *
     * Every mutating method asks before it acts rather than the view hiding the
     * button, because a hidden button is not a check: a Livewire method is a
     * public endpoint and anything on the page can call it. The view may hide
     * the button as well, and it is right to, but this is the part that holds.
     *
     * A library with no policy registered refuses nothing, exactly as it did
     * before there was anything to ask — see {@see MediaAccess}.
     */
    protected function refuse(string $ability, ?Media $on = null): bool
    {
        if ($this->mayMutate($ability) && MediaAccess::allows($ability, $on ?? Media::class)) {
            return false;
        }

        $this->notifyError(__('wire-module-media::messages.not_allowed'));

        return true;
    }

    /**
     * Whether this screen is one that changes things at all.
     *
     * The library is a screen; the picker is a chooser wearing the same class
     * ({@see MediaPicker}), and it is mounted on *every* page of the panel
     * because a form field may need it. That makes every wire method on here a
     * public endpoint on every page — so a chooser that inherits `delete` is a
     * delete button on a screen nobody thought of as the library.
     *
     * `create` is the exception and stays open: uploading the file you came to
     * pick is what a picker is for. What it may not do is change or destroy what
     * is already there.
     */
    protected function mayMutate(string $ability): bool
    {
        return ! $this->picking || $ability === 'create';
    }

    /**
     * The rows of this selection the person may actually do that to.
     *
     * A selection is not one decision. A policy answering per record — "your own
     * uploads, not everybody's" — has to be able to refuse three files out of
     * ten and let the other seven through, and a single question about the class
     * cannot say that. So the rows are loaded and asked about one at a time.
     *
     * Silence is not one of the answers: if anything was held back, the person is
     * told once, because a delete that quietly does less than it said is worse
     * than one that refuses.
     *
     * @param  array<int, int>  $ids
     * @return Collection<int, Media>
     */
    protected function permitted(array $ids, string $ability): Collection
    {
        if ($ids === [] || ! $this->mayMutate($ability)) {
            if ($ids !== []) {
                $this->notifyError(__('wire-module-media::messages.not_allowed'));
            }

            return new Collection;
        }

        $found = Media::query()->whereIn('id', $ids)->get();
        $allowed = $found->filter(static fn (Media $one): bool => MediaAccess::allows($ability, $one));

        if ($allowed->count() < $found->count()) {
            $this->notifyError(__('wire-module-media::messages.not_allowed'));
        }

        return $allowed;
    }

    protected function guarded(callable $action): void
    {
        try {
            $action();
        } catch (MediaException $e) {
            $this->notifyError($e->getMessage());
        }
    }
}
