@php
    use NyonCode\WireCore\Foundation\View\Palette;
@endphp
{{-- The media library, from NyonCode\WireModuleMedia\Livewire\MediaManager.

     Three columns: the tree, what is in the open folder, and — when one is
     opened — that file's details. The whole middle column is one drop zone:
     dropping files from the desktop uploads them into the folder being looked
     at, and dragging a tile onto a folder in the tree files it there. Both use
     the same HTML5 drag transfer, with a key per kind (`wire/media`,
     `wire/folder`) so a tile dropped on a folder and a folder dropped on a
     folder can be told apart.

     The same view is the picker. What `$picking` changes is what a tile does
     when it is clicked, whether there is a bar to confirm with, whether tiles
     carry checkboxes at all, and whether the tree offers its housekeeping — not
     the markup around any of it, because a picker that is a *different* screen
     is one that slowly stops matching the library. --}}

{{-- Choosing exactly one file needs no checkboxes: the click is the answer
     ({@see MediaManager::pick()}), and the confirm bar below only appears for a
     multiple choice. Rendering them anyway left a single-file picker with a box
     you could tick and no way to finish — the tick selected, and nothing in the
     modal could act on it. --}}
@php($choosingOne = $picking && ! $multiple)

{{-- Cut and paste, as the keyboard's half of the drag. Guarded on the event's
     target, because ⌘X in the search box or a rename field has to keep meaning
     what it means everywhere else on the web. --}}
<div
    class="flex flex-col gap-4 lg:flex-row"
    data-testid="media-manager" @wireEl('media-manager')
    x-data
    x-on:keydown.window="
        const el = $event.target;
        if (el.matches('input, textarea, select, [contenteditable]')) return;
        if (! ($event.metaKey || $event.ctrlKey)) return;
        if ($event.key === 'x') { $event.preventDefault(); $wire.cutSelection(); }
        if ($event.key === 'v') { $event.preventDefault(); $wire.pasteHere(); }
    "
>

    {{-- ── The tree ────────────────────────────────────────────────────── --}}
    {{-- The rail's width is the person's, not the design's: a library whose
         folders are called "Kampaň jaro 2026 — tiskoviny" and one whose folders
         are called "A" want different widths, and only one of them is in the
         room. Remembered in `localStorage`, wrapped because a private window
         throws on the accessor itself. --}}
    {{-- The rail is resizable on the library screen, where it is the only thing
         competing with the grid for a whole window. Inside the picker it is a
         fixed 12rem: there is no room to negotiate in a modal that is 48rem
         wide, and 14rem of tree was leaving the grid two tiles across. --}}
    <div
        @class([
            'relative w-full shrink-0',
            'lg:w-[var(--wire-media-rail)]' => ! $picking,
            'lg:w-48' => $picking,
        ])
        data-testid="media-rail" @wireEl('media-rail')
        x-data="{
            width: 224,
            drag: null,
            // Below `lg` the tree is not a column but a disclosure: stacked, it
            // was 158px of navigation above the first file, on a screen where
            // the files are the reason the modal opened. The breakpoint is read
            // rather than assumed, because the same view is a full page too.
            narrow: window.matchMedia('(max-width: 1023.98px)').matches,
            folders: false,
            init() {
                const query = window.matchMedia('(max-width: 1023.98px)');
                query.addEventListener('change', (event) => {
                    this.narrow = event.matches;
                    // Rotating to landscape must not leave the tree collapsed
                    // behind a button that is no longer on screen.
                    if (! event.matches) this.folders = false;
                });

                try {
                    const stored = parseInt(localStorage.getItem('wire-media-rail') ?? '', 10);
                    if (stored >= 160 && stored <= 480) this.width = stored;
                } catch {}
            },
            start(event) {
                event.preventDefault();
                this.drag = { x: event.clientX, from: this.width };
            },
            move(event) {
                if (! this.drag) return;
                // Bounded: a rail dragged to nothing is a rail nobody can get
                // back, and one dragged past half the screen is a grid nobody
                // can use.
                const next = this.drag.from + (event.clientX - this.drag.x) * (document.dir === 'rtl' ? -1 : 1);
                this.width = Math.min(480, Math.max(160, Math.round(next)));
            },
            end() {
                if (! this.drag) return;
                this.drag = null;
                try { localStorage.setItem('wire-media-rail', String(this.width)) } catch {}
            },
        }"
        :style="`--wire-media-rail: ${width}px`"
        x-on:pointermove.window="move($event)"
        x-on:pointerup.window="end()"
    >
        {{-- The handle sits on the seam and only exists where there are two
             columns to be a seam between. --}}
        <div
            @class([
                'absolute inset-y-0 -end-2 z-10 hidden w-3 cursor-col-resize',
                'lg:block' => ! $picking,
            ])
            data-testid="media-rail-handle" @wireEl('media-rail-handle')
            role="separator"
            aria-orientation="vertical"
            x-on:pointerdown="start($event)"
            x-on:dblclick="width = 224; end()"
        >
            <span class="hover:bg-primary-300 absolute inset-y-4 start-1 w-1 rounded-full bg-transparent transition"></span>
        </div>

        {{-- What the tree costs on a narrow screen, spent on one row instead:
             the folder you are in, and a chevron to see the rest. `x-cloak`
             rather than a `lg:hidden`, so the button is never on screen for the
             frame before Alpine knows how wide the window is. --}}
        <button
            type="button"
            x-show="narrow"
            x-cloak
            x-on:click="folders = ! folders"
            :aria-expanded="folders ? 'true' : 'false'"
            data-testid="media-folder-toggle" @wireEl('media-folder-toggle')
            class="mb-2 flex w-full items-center gap-2 rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-800 dark:bg-gray-900"
        >
            {!! icon('outline:folder', 'h-4 w-4 shrink-0 text-gray-400') !!}
            <span class="min-w-0 flex-1 truncate text-start">{{ $folder?->name ?? __('wire-module-media::messages.library') }}</span>
            <span class="shrink-0 transition-transform" :class="folders ? '' : '-rotate-90'">
                {!! icon('outline:chevron-down', 'h-4 w-4 text-gray-400') !!}
            </span>
        </button>

        {{-- Inside the picker the modal bounds the height, so a rail that stops
             where its tree ends leaves the box ragged along the bottom. On the
             library screen it must not stretch: the grid there is as tall as the
             folder holds, and an empty tree box that tall reads as a bug.

             No `x-cloak` here on purpose: the tree is the *normal* state at every
             width that has room for it, and cloaking it would blank the column on
             a wide screen for as long as Alpine takes to boot. --}}
        <div x-show="! narrow || folders" @class([
            'rounded-xl border border-gray-200 bg-white p-3 dark:border-gray-800 dark:bg-gray-900',
            'lg:h-full' => $picking,
        ])>
            <div class="mb-2 flex items-center justify-between">
                <p class="text-[11px] font-semibold tracking-wider text-gray-400 uppercase">
                    {{ __('wire-module-media::messages.folders') }}
                </p>

                <button
                    type="button"
                    wire:click="$toggle('creatingFolder')"
                    data-testid="media-new-folder" @wireEl('media-new-folder')
                    class="focus-visible:ring-primary-500 rounded-lg p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-700 focus-visible:ring-2 focus-visible:outline-none dark:hover:bg-gray-800"
                    title="{{ __('wire-module-media::messages.new_folder') }}"
                >
                    <span class="sr-only">{{ __('wire-module-media::messages.new_folder') }}</span>
                    {!! icon('outline:folder-plus', 'h-4 w-4') !!}
                </button>
            </div>

            @if ($creatingFolder)
                <form wire:submit="createFolder" class="mb-2 flex gap-1">
                    <input
                        type="text"
                        wire:model="newFolderName"
                        data-testid="media-new-folder-name" @wireEl('media-new-folder-name')
                        placeholder="{{ __('wire-module-media::messages.folder_name') }}"
                        autofocus
                        class="min-w-0 flex-1 rounded-lg border border-gray-200 px-2 py-1 text-sm dark:border-gray-700 dark:bg-gray-800"
                    >
                    <button type="submit" class="bg-primary-600 rounded-lg px-2 py-1 text-sm text-white">
                        {{ __('wire-module-media::messages.create') }}
                    </button>
                </form>
            @endif

            {{-- Which branches are open is the browser's business, not the
                 server's: folding a folder away should not be a round trip, and
                 what somebody has open is theirs rather than the library's.
                 `localStorage` is wrapped because a private window throws on the
                 accessor itself. --}}
            <ul
                class="space-y-0.5"
                data-testid="media-tree" @wireEl('media-tree')
                x-data="{
                    open: [],
                    hover: null,
                    init() {
                        try { this.open = JSON.parse(localStorage.getItem('wire-media-tree') ?? '[]') } catch { this.open = [] }
                    },
                    isOpen(id) { return this.open.includes(id) },
                    toggle(id) {
                        this.open = this.isOpen(id) ? this.open.filter(o => o !== id) : [...this.open, id];
                        try { localStorage.setItem('wire-media-tree', JSON.stringify(this.open)) } catch {}
                    },
                    expandOnHover(id) {
                        if (this.isOpen(id) || this.hover) return;
                        // A pause, so passing over a folder on the way somewhere
                        // else does not unfold half the tree behind the cursor.
                        this.hover = setTimeout(() => { this.toggle(id); this.hover = null }, 600);
                    },
                    cancelHover() { clearTimeout(this.hover); this.hover = null },
                }"
                {{-- Choosing a folder is the end of the errand, so the
                     disclosure closes behind it — but only for a click that
                     actually opened one. Folding a branch open to look inside it
                     is the opposite of being finished. `narrow` and `folders`
                     are the rail's; Alpine scopes nest. --}}
                x-on:click="if (narrow && $event.target.closest('[data-testid=media-folder-open], [data-testid=media-folder-root]')) folders = false"
            >
                {{-- The root is a drop target too, which is the only way to get
                     something back out of a folder by dragging. --}}
                <li>
                    <button
                        type="button"
                        wire:click="openFolder(null)"
                        x-on:dragover.prevent="$el.classList.add('ring-2','ring-primary-400')"
                        x-on:dragleave="$el.classList.remove('ring-2','ring-primary-400')"
                        x-on:drop.prevent="
                            $el.classList.remove('ring-2','ring-primary-400');
                            const file = $event.dataTransfer.getData('wire/media');
                            const folderId = $event.dataTransfer.getData('wire/folder');
                            if (file === 'selection') $wire.moveTo(null, null);
                            else if (file) $wire.moveTo(null, parseInt(file));
                            else if (folderId) $wire.moveFolder(parseInt(folderId), null);
                        "
                        data-testid="media-folder-root" @wireEl('media-folder-root')
                        @class([
                            'flex w-full items-center gap-2 rounded-lg px-2 py-1.5 text-start text-sm transition',
                            'bg-primary-50 text-primary-700 dark:bg-primary-950/60 dark:text-primary-200' => $folder === null,
                            'text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800' => $folder !== null,
                        ])
                    >
                        {!! icon('outline:home', 'h-4 w-4 shrink-0 text-gray-400') !!}
                        <span class="flex-1 truncate">{{ __('wire-module-media::messages.library') }}</span>
                        <span class="font-mono text-[10px] text-gray-400 tabular-nums">{{ $folderCounts['root'] ?? 0 }}</span>
                    </button>
                </li>

                @include('wire-module-media::partials.folder-branch', [
                    'branch' => $folders['root'] ?? [],
                    'depth' => 0,
                    'current' => $folder?->id,
                ])
            </ul>
        </div>
    </div>

    {{-- ── The folder ──────────────────────────────────────────────────── --}}
    {{-- The drop zone is `wireFileDropzone`, the controller FileUpload already
         uses — asked for previews rather than reimplemented with them. The first
         version of this was sixty lines of `x-data` in this file that repeated
         its drop handling, and had already started to differ from it.

         The assets partial is included for the same reason every field view
         includes it: `@wireStackScripts` is additive, so a layout without the
         directive would leave `wireFileDropzone` unregistered and this zone
         silently inert. --}}
    @include('wire-forms::partials.field-assets')

    <div
        class="min-w-0 flex-1"
        x-data="wireFileDropzone({ previews: true, retain: true })"
        x-on:dragover.prevent="if ($event.dataTransfer.types.includes('Files')) isDragging = true"
        x-on:dragleave="isDragging = false"
        x-on:drop.prevent="handleDrop($event)"
    >
        {{-- `overflow-clip`, so the radius is kept by what is inside it too. The
             list rows run to both edges and the last one's hover paints a
             rectangle into the bottom corners; the toolbar below reaches the top
             ones, which is why it was carrying a `rounded-t-xl` of its own. Clip
             and not `overflow-hidden`: that toolbar is `sticky top-0` against the
             PAGE, and a scroll container here would leave it in flow. --}}
        <div class="overflow-clip rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">

            {{-- The toolbar stops scrolling with the files. Where you are, what
                 you are looking for and how it is shown are the three things you
                 reach for *because* you have scrolled, and a toolbar that has
                 gone off the top is one you have to scroll back for. --}}
            <div class="sticky top-0 z-20 flex flex-wrap items-center gap-2 rounded-t-xl border-b border-gray-200 bg-white p-3 dark:border-gray-800 dark:bg-gray-900">
                {{-- Every rung is a drop target, which is how a file goes
                     back up one level without hunting for the folder it came
                     from in a tree that may be thirty deep. --}}
                {{-- In the picker on a phone the folder button above already
                     names where you are, and two answers to one question in a
                     toolbar that is already wrapping is one too many — the
                     breadcrumb was the thing being truncated to "Li…". It stays
                     at every width on the library screen, which has the room and
                     where every rung is also a drop target. --}}
                <nav @class([
                    'min-w-0 flex-1 items-center gap-1 text-sm',
                    'flex' => ! $picking,
                    'hidden lg:flex' => $picking,
                ]) data-testid="media-breadcrumb" @wireEl('media-breadcrumb')>
                    @foreach ([null, ...$crumbs->all()] as $crumb)
                        @if (! $loop->first)
                            <span class="text-gray-300">/</span>
                        @endif

                        <button
                            type="button"
                            wire:click="openFolder({{ $crumb?->id ?? 'null' }})"
                            data-testid="media-crumb" @wireEl('media-crumb')
                            x-on:dragover.prevent="$el.classList.add('ring-2','ring-primary-400','rounded-sm')"
                            x-on:dragleave="$el.classList.remove('ring-2','ring-primary-400','rounded-sm')"
                            x-on:drop.prevent="
                                $el.classList.remove('ring-2','ring-primary-400','rounded-sm');
                                const file = $event.dataTransfer.getData('wire/media');
                                const folder = $event.dataTransfer.getData('wire/folder');
                                const into = {{ $crumb?->id ?? 'null' }};
                                if (file === 'selection') $wire.moveTo(into, null);
                                else if (file) $wire.moveTo(into, parseInt(file));
                                else if (folder && parseInt(folder) !== into) $wire.moveFolder(parseInt(folder), into);
                            "
                            @class([
                                'truncate px-1 py-0.5',
                                'font-medium text-gray-900 dark:text-gray-100' => $loop->last && ! $loop->first,
                                'text-gray-500 hover:text-gray-800 dark:hover:text-gray-200' => ! $loop->last || $loop->first,
                            ])
                        >{{ $crumb?->name ?? __('wire-module-media::messages.library') }}</button>
                    @endforeach
                </nav>

                <input
                    type="search"
                    wire:model.live.debounce.300ms="search"
                    data-testid="media-search" @wireEl('media-search')
                    placeholder="{{ __('wire-module-media::messages.search') }}"
                    class="w-40 rounded-lg border border-gray-200 px-3 py-1.5 text-sm dark:border-gray-700 dark:bg-gray-800"
                >

                <select
                    wire:model.live="type"
                    data-testid="media-type" @wireEl('media-type')
                    aria-label="{{ __('wire-module-media::messages.type') }}"
                    class="rounded-lg border border-gray-200 px-2 py-1.5 text-sm dark:border-gray-700 dark:bg-gray-800"
                >
                    {{-- Coarse on purpose: a library's filter is for "show me the
                         pictures", and a list of eleven mime types is a list
                         nobody reads. The search box finds a specific one. --}}
                    <option value="">{{ __('wire-module-media::messages.all_types') }}</option>
                    <option value="image">{{ __('wire-module-media::messages.images') }}</option>
                    <option value="document">{{ __('wire-module-media::messages.documents') }}</option>
                </select>

                <select
                    wire:model.live="sort"
                    data-testid="media-sort" @wireEl('media-sort')
                    aria-label="{{ __('wire-module-media::messages.sort') }}"
                    class="rounded-lg border border-gray-200 px-2 py-1.5 text-sm dark:border-gray-700 dark:bg-gray-800"
                >
                    <option value="created_at:desc">{{ __('wire-module-media::messages.sort_newest') }}</option>
                    <option value="created_at:asc">{{ __('wire-module-media::messages.sort_oldest') }}</option>
                    <option value="name:asc">{{ __('wire-module-media::messages.sort_name') }}</option>
                    <option value="size:desc">{{ __('wire-module-media::messages.sort_largest') }}</option>
                </select>

                {{-- Icon-only, so it carries its name for a screen reader as well
                     as a tooltip for a mouse. `title` alone is announced by some
                     readers and not others, which is not a standard to build on. --}}
                {{-- The list view is a table of six columns; on a phone it is
                     the grid or nothing, so in the picker the switch is one less
                     control in a toolbar with no room for it. The library screen
                     keeps it — somebody sorting by size wants the list even on a
                     phone, and that screen is not fighting a modal for width. --}}
                <button
                    type="button"
                    wire:click="toggleView"
                    data-testid="media-view-toggle" @wireEl('media-view-toggle')
                    @class([
                        'focus-visible:ring-primary-500 rounded-lg p-2 text-gray-500 hover:bg-gray-100 focus-visible:ring-2 focus-visible:outline-none dark:hover:bg-gray-800',
                        'hidden lg:block' => $picking,
                    ])
                    title="{{ __('wire-module-media::messages.view') }}"
                >
                    <span class="sr-only">{{ __('wire-module-media::messages.view') }}</span>
                    {!! icon($view === 'grid' ? 'outline:list-bullet' : 'outline:squares-2x2', 'h-4 w-4') !!}
                </button>

                <label
                    class="bg-primary-600 hover:bg-primary-700 inline-flex cursor-pointer items-center gap-2 rounded-lg px-3 py-1.5 text-sm font-medium text-white"
                    data-testid="media-upload" @wireEl('media-upload')
                >
                    {!! icon('outline:arrow-up-tray', 'h-4 w-4') !!}
                    {{ __('wire-module-media::messages.upload') }}
                    <input
                        type="file"
                        multiple
                        wire:model="uploads"
                        x-ref="fileInput"
                        x-on:change="preview($event.target.files)"
                        class="hidden"
                    >
                </label>

                {{-- Picking a whole folder, which is the difference between
                     "bulk add" and "add the shoot". The files are filed into the
                     folder being looked at, not into folders named after the
                     directories they came from: a folder here is a row, and
                     inventing four of them from a directory somebody happened to
                     drag is a decision this screen should not make quietly. --}}
                <label
                    class="inline-flex cursor-pointer items-center gap-1.5 rounded-lg border border-gray-200 px-2.5 py-1.5 text-sm text-gray-600 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800"
                    data-testid="media-upload-folder" @wireEl('media-upload-folder')
                    title="{{ __('wire-module-media::messages.upload_folder') }}"
                >
                    {!! icon('outline:folder-plus', 'h-4 w-4') !!}
                    <span class="sr-only">{{ __('wire-module-media::messages.upload_folder') }}</span>
                    <input
                        type="file"
                        multiple
                        webkitdirectory
                        wire:model="uploads"
                        x-on:change="preview($event.target.files)"
                        class="hidden"
                    >
                </label>
            </div>

            {{-- Confirming a choice, when the library was opened to make one and
                 more than one file may be chosen. A single-file picker needs no
                 bar: the click is the answer. --}}
            @if ($picking && $multiple && $selected)
                <div class="bg-primary-50 dark:bg-primary-950/40 flex items-center gap-2 px-3 py-2 text-sm" data-testid="media-pick-bar" @wireEl('media-pick-bar')>
                    <span class="text-primary-800 dark:text-primary-200">
                        {{ trans_choice('wire-module-media::messages.selected', count($selected), ['count' => count($selected)]) }}
                    </span>

                    <button
                        type="button"
                        wire:click="confirmPick"
                        data-testid="media-pick-confirm" @wireEl('media-pick-confirm')
                        class="bg-primary-600 hover:bg-primary-700 ms-auto rounded-lg px-3 py-1 font-medium text-white"
                    >{{ __('wire-module-media::messages.use_selected') }}</button>
                </div>
            @endif

            {{-- The drop hint. Rendered always and revealed by Alpine rather than
                 inserted on drop: an element that appears under the cursor
                 mid-drag cancels the drag in some browsers. --}}
            <div x-show="isDragging" x-cloak class="border-primary-400 bg-primary-50/60 dark:bg-primary-950/40 m-3 rounded-xl border-2 border-dashed p-8 text-center">
                <p class="text-primary-700 dark:text-primary-200 text-sm font-medium">
                    {!! icon('outline:cloud-arrow-up', 'mx-auto mb-2 h-8 w-8') !!}
                    {{ __('wire-module-media::messages.drop_here') }}
                </p>
            </div>

            <div x-show="! isDragging" class="p-3" @if ($awaitingThumbnails) wire:poll.3s @endif>
                {{-- What is actually happening, rather than the word "uploading":
                     a bar that moves is the difference between a slow upload and
                     a page somebody reloads because they think it hung. --}}
                <div x-show="pending.length" x-cloak class="mb-3">
                    <div class="mb-1 flex items-center justify-between text-xs text-gray-500">
                        <span>{{ __('wire-module-media::messages.uploading') }}</span>
                        <span x-text="progress + '%'"></span>
                    </div>
                    <div class="h-1 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                        <div class="bg-primary-600 h-full transition-[width] duration-200" x-bind:style="`width: ${progress}%`"></div>
                    </div>
                </div>

                @if ($files->isEmpty())
                    <div class="p-8 text-center" data-testid="media-empty" @wireEl('media-empty')>
                        {!! icon('outline:photo', 'mx-auto mb-3 h-10 w-10 text-gray-300') !!}
                        <p class="text-sm text-gray-500">{{ __('wire-module-media::messages.empty') }}</p>
                        <p class="mt-1 text-xs text-gray-400">{{ __('wire-module-media::messages.drop_hint') }}</p>
                    </div>
                @elseif ($view === 'grid')
                    @php($pageIds = $files->pluck('id')->all())

                    {{-- This page, not the whole library: a checkbox that
                         silently selects four thousand rows behind a Delete
                         button is the oldest way a bulk action becomes an
                         accident. --}}
                    @unless ($choosingOne)
                        <label class="mb-2 inline-flex cursor-pointer items-center gap-2 text-xs text-gray-500">
                            <input
                                type="checkbox"
                                wire:click="toggleAll({{ Js::from($pageIds) }})"
                                @checked($this->allSelected($pageIds))
                                data-testid="media-select-all" @wireEl('media-select-all')
                                class="text-primary-600 h-4 w-4 rounded-sm border-gray-300"
                            >
                            {{ __('wire-module-media::messages.select_all') }}
                        </label>
                    @endunless

                    {{-- A tile is one size, and the number of them across is
                         whatever fits.

                         Column *counts* per breakpoint were the wrong way round:
                         they hold the count and stretch the tile, so the same
                         photograph is thumbnail-sized on a laptop and enormous
                         on a widescreen — and the picker, which renders this
                         same grid inside a narrow modal, laid out to the
                         viewport rather than to the space it actually had.

                         `auto-fill` with a fixed track holds the tile instead
                         and lets the count go where it likes: ten across on a
                         wide monitor, three on a small one, the same size on
                         both. The `min(…, 100%)` floor is for a container
                         narrower than one tile — a phone, or the picker on one —
                         where a fixed track would otherwise overflow. --}}
                    {{-- Both tracks are written out in full rather than
                         interpolated: Tailwind reads these files as text, and a
                         class it never sees spelled out is a class it never
                         generates. The picker's is smaller because the modal is
                         fixed — a choice you have to scroll to make is a choice
                         made badly. --}}
                    {{-- In the picker the tiles scroll and nothing else does.
                         The modal box scrolls as a whole otherwise, which takes
                         the search box and Upload off the top of the screen at
                         exactly the moment a library big enough to need them is
                         the reason you are scrolling.

                         Two rows, and a slice of the third: cutting cleanly on a
                         row boundary is how a grid tells somebody there is
                         nothing below it. The peek is the scrollbar's job done
                         without a scrollbar.

                         On a phone it is `60svh` rather than two rows, because
                         two rows of a two-column grid is four files. `svh` and
                         not `vh`: `vh` on iOS is measured against a viewport
                         that still has the address bar in it, so a grid sized in
                         `vh` is always taller than the screen it is on. --}}
                    <ul @class([
                        'grid gap-3',
                        'grid-cols-[repeat(auto-fill,minmax(min(168px,100%),168px))]' => ! $picking,
                        'grid-cols-[repeat(auto-fill,minmax(min(116px,100%),116px))] max-h-[60svh] overflow-y-auto lg:max-h-[23rem]' => $picking,
                    ])>
                        {{-- The files being uploaded, drawn from the browser's own
                             copy. First in the grid because that is where they
                             will land: the list is newest first. --}}
                        <template x-for="item in pending" :key="item.name">
                            <li class="border-primary-300 relative overflow-hidden rounded-xl border border-dashed" data-testid="media-pending" @wireEl('media-pending')>
                                <div class="flex aspect-square items-center justify-center bg-gray-50 dark:bg-gray-800">
                                    <img x-show="item.url" x-bind:src="item.url" alt="" class="h-full w-full object-cover opacity-60">
                                    <span x-show="! item.url">{!! icon('outline:document', 'h-10 w-10 text-gray-300') !!}</span>
                                </div>
                                <div class="p-2">
                                    <p class="truncate text-xs font-medium text-gray-500" x-text="item.name"></p>
                                    <p class="text-[11px] text-gray-400">{{ __('wire-module-media::messages.uploading') }}</p>
                                </div>
                            </li>
                        </template>

                        @foreach ($files as $file)
                            <li
                                draggable="true"
                                {{-- A tile that is part of the selection drags
                                     the whole selection. Pulling the one tile
                                     under the cursor out of a selection of
                                     twelve is not what anybody who made that
                                     selection meant. --}}
                                x-on:dragstart="$event.dataTransfer.setData('wire/media', '{{ in_array($file->id, $selected, true) ? 'selection' : $file->id }}')"
                                data-testid="media-tile" @wireEl('media-tile')
                                data-media="{{ $file->id }}"
                                @class([
                                    'group relative cursor-grab overflow-hidden rounded-xl border transition active:cursor-grabbing',
                                    'border-primary-400 ring-primary-200 ring-2' => in_array($file->id, $selected, true),
                                    'border-gray-200 hover:border-gray-300 dark:border-gray-800' => ! in_array($file->id, $selected, true),
                                ])
                            >
                                @unless ($choosingOne)
                                    <label class="absolute start-2 top-2 z-10 cursor-pointer">
                                        <span class="sr-only">{{ __('wire-module-media::messages.select') }} {{ $file->name }}</span>
                                        <input
                                            type="checkbox"
                                            wire:model.live="selected"
                                            value="{{ $file->id }}"
                                            data-testid="media-select" @wireEl('media-select')
                                            class="text-primary-600 h-4 w-4 rounded-sm border-gray-300"
                                        >
                                    </label>
                                @endunless

                                {{-- One click, two meanings, decided by why the
                                     library is open: choosing a file when it was
                                     opened to choose one, looking at it
                                     otherwise. --}}
                                <button
                                    type="button"
                                    wire:click="{{ $picking ? 'pick('.$file->id.')' : 'showDetail('.$file->id.')' }}"
                                    data-testid="media-open" @wireEl('media-open')
                                    class="block w-full"
                                >
                                    {{-- The scaled copy where there is one — a
                                         grid of a hundred files must not be a
                                         hundred full-size photographs — and
                                         otherwise the file's own extension over
                                         its family's colour. One grey icon told
                                         a catalogue, a price list and a print
                                         archive apart not at all (ADR 0033). --}}
                                    <div class="flex aspect-square items-center justify-center overflow-hidden bg-gray-50 dark:bg-gray-800">
                                        <x-wire::file-thumb
                                            :name="$file->name"
                                            :mime="$file->mime_type"
                                            :url="$file->previewUrl('tile')"
                                            :srcset="$file->srcset('tile')"
                                            :width="$file->width"
                                            :height="$file->height"
                                            :placeholder="$file->placeholder"
                                            :alt="$file->altText()"
                                            size="lg"
                                        />
                                    </div>
                                </button>

                                <div class="p-2">
                                    @if ($renamingId === $file->id)
                                        <form wire:submit="saveRename">
                                            <input
                                                type="text"
                                                wire:model="renamingName"
                                                data-testid="media-rename-input" @wireEl('media-rename-input')
                                                autofocus
                                                class="w-full rounded-sm border border-gray-200 px-1 py-0.5 text-xs dark:border-gray-700 dark:bg-gray-800"
                                            >
                                        </form>
                                    @else
                                        <p class="truncate text-xs font-medium text-gray-700 dark:text-gray-200" title="{{ $file->name }}">{{ $file->name }}</p>
                                        <p class="text-[11px] text-gray-400">{{ $file->dimensions() ?? $file->humanSize() }}</p>
                                    @endif
                                </div>

                                @unless ($picking)
                                    <div class="absolute end-1 top-1 hidden gap-0.5 rounded-lg bg-white/90 p-0.5 shadow-sm group-hover:flex dark:bg-gray-900/90">
                                        <button
                                            type="button"
                                            wire:click="startRenaming({{ $file->id }})"
                                            data-testid="media-rename" @wireEl('media-rename')
                                            class="rounded-sm p-1 text-gray-500 hover:text-gray-800 dark:hover:text-gray-200"
                                            title="{{ __('wire-module-media::messages.rename') }}"
                                        >
                                            <span class="sr-only">{{ __('wire-module-media::messages.rename') }} {{ $file->name }}</span>
                                            {!! icon('outline:pencil-square', 'h-3.5 w-3.5') !!}
                                        </button>

                                        <button
                                            type="button"
                                            wire:click="deleteOne({{ $file->id }})"
                                            {{-- The count of known uses, in the
                                                 sentence rather than beside it:
                                                 "are you sure?" is clicked
                                                 through, "this is in 3 records"
                                                 is read (ADR 0034). --}}
                                            wire:confirm="{{ $this->deleteWarning($usageCounts[$file->id] ?? 0) }}"
                                            data-testid="media-delete" @wireEl('media-delete')
                                            class="rounded-sm p-1 text-gray-500 hover:text-red-600"
                                            title="{{ __('wire-module-media::messages.delete') }}"
                                        >
                                            <span class="sr-only">{{ __('wire-module-media::messages.delete') }} {{ $file->name }}</span>
                                            {!! icon('outline:trash', 'h-3.5 w-3.5') !!}
                                        </button>
                                    </div>
                                @endunless
                            </li>
                        @endforeach
                    </ul>
                @else
                    {{-- A list, not a strip of names. The headers sort, and
                         they sort by setting the same `sort` the toolbar and the
                         URL use — one property, so a filtered view is still
                         something you can send to somebody. --}}
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 text-left dark:border-gray-800">
                                @unless ($choosingOne)
                                    <th class="w-8 ps-2"></th>
                                @endunless
                                <th class="w-10"></th>
                                @foreach ([
                                    'name' => __('wire-module-media::messages.name'),
                                    'mime_type' => __('wire-module-media::messages.type'),
                                    'size' => __('wire-module-media::messages.size'),
                                    'width' => __('wire-module-media::messages.dimensions'),
                                    'folder_id' => __('wire-module-media::messages.folder'),
                                    'created_at' => __('wire-module-media::messages.uploaded'),
                                ] as $column => $label)
                                    @php($active = $this->sortColumn() === $column)
                                    <th
                                        scope="col"
                                        class="py-2 pe-2 text-[11px] font-semibold tracking-wider text-gray-400 uppercase"
                                        @if ($active) aria-sort="{{ $this->sortDirection() === 'asc' ? 'ascending' : 'descending' }}" @endif
                                    >
                                        <button
                                            type="button"
                                            wire:click="sortBy('{{ $column }}')"
                                            data-testid="media-sort-{{ $column }}"
                                            @class([
                                                'inline-flex items-center gap-1 hover:text-gray-700 dark:hover:text-gray-200',
                                                'text-gray-700 dark:text-gray-200' => $active,
                                            ])
                                        >
                                            {{ $label }}
                                            @if ($active)
                                                {!! icon($this->sortDirection() === 'asc' ? 'outline:chevron-up' : 'outline:chevron-down', 'h-3 w-3') !!}
                                            @endif
                                        </button>
                                    </th>
                                @endforeach
                                @unless ($picking)
                                    <th class="w-20"></th>
                                @endunless
                            </tr>
                        </thead>

                        <tbody>
                            @foreach ($files as $file)
                                <tr
                                    draggable="true"
                                    {{-- A tile that is part of the selection
                                         drags the whole selection. Dragging the
                                         one tile under the cursor out of a
                                         selection of twelve is not what anybody
                                         who made that selection meant. --}}
                                    x-on:dragstart="$event.dataTransfer.setData('wire/media', '{{ in_array($file->id, $selected, true) ? 'selection' : $file->id }}')"
                                    data-testid="media-row" @wireEl('media-row')
                                    data-media="{{ $file->id }}"
                                    class="border-b border-gray-100 last:border-0 hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-gray-800/60"
                                >
                                    @unless ($choosingOne)
                                        <td class="w-8 ps-2">
                                            <input type="checkbox" wire:model.live="selected" value="{{ $file->id }}" data-testid="media-select" @wireEl('media-select') class="text-primary-600 h-4 w-4 rounded-sm border-gray-300">
                                        </td>
                                    @endunless
                                    <td class="w-10 py-2">
                                        <button type="button" wire:click="{{ $picking ? 'pick('.$file->id.')' : 'showDetail('.$file->id.')' }}" data-testid="media-open" @wireEl('media-open')>
                                            <span class="flex h-8 w-8 items-center justify-center overflow-hidden rounded-sm">
                                                <x-wire::file-thumb
                                                    :name="$file->name"
                                                    :mime="$file->mime_type"
                                                    {{-- A 32-pixel row asked for
                                                         the 400-pixel tile until
                                                         there was a size for it. --}}
                                                    :url="$file->previewUrl('row')"
                                                    :srcset="$file->srcset('row')"
                                                    :placeholder="$file->placeholder"
                                                    :alt="$file->altText()"
                                                    size="sm"
                                                />
                                            </span>
                                        </button>
                                    </td>
                                    <td class="max-w-0 truncate py-2 pe-2 font-medium text-gray-700 dark:text-gray-200" title="{{ $file->name }}">{{ $file->name }}</td>
                                    {{-- The family, not the mime type: nobody
                                         reads `application/vnd.openxmlformats-…`
                                         and it is sixty characters of it. --}}
                                    <td class="py-2 pe-2 text-gray-500 dark:text-gray-400">{{ $file->kind()->label() }}</td>
                                    <td class="py-2 pe-2 font-mono text-xs text-gray-500 tabular-nums dark:text-gray-400">{{ $file->humanSize() }}</td>
                                    <td class="py-2 pe-2 font-mono text-xs text-gray-500 tabular-nums dark:text-gray-400">{{ $file->dimensions() ?? '—' }}</td>
                                    <td class="py-2 pe-2 text-gray-500 dark:text-gray-400">{{ $file->folder?->name ?? __('wire-module-media::messages.library') }}</td>
                                    <td class="py-2 pe-2 font-mono text-xs text-gray-500 tabular-nums dark:text-gray-400">{{ $file->created_at?->isoFormat('L') }}</td>
                                    @unless ($picking)
                                        <td class="w-20 py-2 pe-2 text-end">
                                            <button type="button" wire:click="startRenaming({{ $file->id }})" data-testid="media-rename" @wireEl('media-rename') class="rounded-sm p-1 text-gray-400 hover:text-gray-700">{!! icon('outline:pencil-square', 'h-4 w-4') !!}</button>
                                            <button type="button" wire:click="deleteOne({{ $file->id }})" wire:confirm="{{ $this->deleteWarning($usageCounts[$file->id] ?? 0) }}" data-testid="media-delete" @wireEl('media-delete') class="rounded-sm p-1 text-gray-400 hover:text-red-600">{!! icon('outline:trash', 'h-4 w-4') !!}</button>
                                        </td>
                                    @endunless
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif

                <div class="mt-3">{{ $files->links() }}</div>
            </div>

            {{-- What is selected, and what can be done with it. Only when
                 something is, because a bar that is always there is a bar that
                 stops meaning anything.

                 It sits at the bottom of the canvas and stays there while the
                 grid scrolls under it: a selection is made by scrolling through
                 files, and a bar at the top is a bar you have to scroll back to
                 in order to act on what you just chose. --}}
            @if ($selected && ! $picking)
                <div class="border-primary-200 bg-primary-50 dark:border-primary-900 dark:bg-primary-950/60 sticky bottom-0 z-20 flex flex-wrap items-center gap-2 border-t px-3 py-2 text-sm shadow-[0_-2px_8px_rgba(15,23,42,.06)]" data-testid="media-selection-bar" @wireEl('media-selection-bar')>
                    <span class="text-primary-800 dark:text-primary-200">
                        {{ trans_choice('wire-module-media::messages.selected', count($selected), ['count' => count($selected)]) }}
                    </span>

                    <select
                        wire:change="moveSelectedTo($event.target.value)"
                        data-testid="media-move-select" @wireEl('media-move-select')
                        class="ms-auto rounded-lg border border-gray-200 px-2 py-1 text-sm dark:border-gray-700 dark:bg-gray-800"
                    >
                        <option value="__">{{ __('wire-module-media::messages.move_to') }}</option>
                        <option value="">{{ __('wire-module-media::messages.library') }}</option>
                        @foreach ($allFolders as $option)
                            <option value="{{ $option->id }}">{{ $option->path }}</option>
                        @endforeach
                    </select>

                    <button
                        type="button"
                        wire:click="deleteSelected"
                        {{-- The whole selection's known uses, summed: a bulk
                             delete is where a file nobody remembers publishing
                             disappears from a page nobody is looking at. --}}
                        wire:confirm="{{ $this->deleteWarning(collect($selected)->sum(fn ($id) => $usageCounts[$id] ?? 0)) }}"
                        data-testid="media-delete-selected" @wireEl('media-delete-selected')
                        class="rounded-lg bg-red-600 px-3 py-1 text-white hover:bg-red-700"
                    >{{ __('wire-module-media::messages.delete') }}</button>
                </div>
            @endif
        {{-- What happened to each file of the last batch.

             "3 failed" in a toast makes the person who dropped forty photographs go
             and find the three themselves. This is the same information kept rather
             than counted, and it survives opening another folder — which is exactly
             what somebody does while a long batch is still going. --}}
        @if ($tray !== [])
            @php($totals = $this->trayTotals())

            <aside
                class="fixed end-4 bottom-4 z-40 w-72 overflow-hidden rounded-xl border border-gray-200 bg-white shadow-lg dark:border-gray-700 dark:bg-gray-900"
                data-testid="media-tray" @wireEl('media-tray')
                x-data="{ open: true }"
            >
                <div class="flex items-center gap-2 border-b border-gray-200 bg-gray-50 px-3 py-2 dark:border-gray-800 dark:bg-gray-800/60">
                    {!! icon('outline:arrow-up-tray', 'h-4 w-4 text-gray-400') !!}
                    <span class="flex-1 text-xs font-semibold">
                        {{ trans_choice('wire-module-media::messages.tray_title', count($tray), ['count' => count($tray)]) }}
                    </span>

                    <button type="button" x-on:click="open = ! open" class="rounded-sm p-1 text-gray-400 hover:text-gray-700 dark:hover:text-gray-200">
                        <span class="sr-only">{{ __('wire-module-media::messages.tray_collapse') }}</span>
                        <span x-show="open">{!! icon('outline:minus', 'h-3.5 w-3.5') !!}</span>
                        <span x-show="! open" x-cloak>{!! icon('outline:plus', 'h-3.5 w-3.5') !!}</span>
                    </button>

                    <button type="button" wire:click="dismissTray" data-testid="media-tray-dismiss" @wireEl('media-tray-dismiss') class="rounded-sm p-1 text-gray-400 hover:text-gray-700 dark:hover:text-gray-200">
                        <span class="sr-only">{{ __('wire-module-media::messages.close') }}</span>
                        {!! icon('outline:x-mark', 'h-3.5 w-3.5') !!}
                    </button>
                </div>

                <ul x-show="open" class="max-h-56 divide-y divide-gray-100 overflow-y-auto dark:divide-gray-800">
                    @foreach ($tray as $entry)
                        <li class="px-3 py-2" data-testid="media-tray-row" @wireEl('media-tray-row')>
                            <div class="flex items-center gap-2">
                                <span class="min-w-0 flex-1 truncate text-xs" title="{{ $entry['name'] }}">{{ $entry['name'] }}</span>

                                <span @class([
                                    'shrink-0 rounded-full px-1.5 py-0.5 font-mono text-[10px]',
                                    Palette::getBadgeColorClasses('success') => $entry['state'] === 'stored',
                                    Palette::getBadgeColorClasses('warning') => $entry['state'] === 'duplicate',
                                    Palette::getBadgeColorClasses('danger') => $entry['state'] === 'failed',
                                ])>{{ __('wire-module-media::messages.tray_'.$entry['state']) }}</span>
                            </div>

                            @if ($entry['state'] === 'duplicate' && $entry['media'])
                                <p class="mt-0.5 text-[11px] text-gray-400">
                                    {{ __('wire-module-media::messages.tray_duplicate_reason') }}
                                    <button type="button" wire:click="showDetail({{ $entry['media'] }})" class="text-primary-600 dark:text-primary-400 underline">{{ __('wire-module-media::messages.tray_open_existing') }}</button>
                                </p>
                            @elseif ($entry['state'] === 'failed')
                                {{-- The reason, not just the fact: a file the disk
                                     refused and a file over the size limit are two
                                     different things to do something about. --}}
                                <p class="mt-0.5 text-[11px] text-gray-400">
                                    {{ __('wire-module-media::messages.tray_failed_reason', ['size' => config('wire-module-media.max_size', 10240)]) }}

                                    {{-- A real retry: the browser still holds the
                                         file and sends that one again. The server
                                         could not do this — it never received it. --}}
                                    <button
                                        type="button"
                                        data-testid="media-tray-retry" @wireEl('media-tray-retry')
                                        x-on:click="
                                            if (! retry(@js($entry['name']))) return;
                                            $wire.forgetTrayEntry(@js($entry['name']));
                                        "
                                        class="text-primary-600 dark:text-primary-400 underline"
                                    >{{ __('wire-module-media::messages.tray_retry') }}</button>
                                </p>
                            @endif
                        </li>
                    @endforeach
                </ul>

                @if ($totals['failed'] > 0 || $totals['duplicate'] > 0)
                    <p class="border-t border-gray-200 px-3 py-1.5 text-[11px] text-gray-400 dark:border-gray-800">
                        {{ __('wire-module-media::messages.tray_summary', $totals) }}
                    </p>
                @endif
            </aside>
        @endif

        </div>
    </div>

    {{-- ── One file ────────────────────────────────────────────────────── --}}
    @if ($detail)
        {{-- Escape closes it, because a panel that opened over what somebody was
             looking at should close the way every other panel on the web does. --}}
        {{-- On a phone it is a sheet rather than a third column stacked under
             the grid: a panel about the file you just tapped, below two screens
             of tiles, is a panel nobody sees. The vocabulary is core's
             {@see MobileSheet} rather than a set of `max-lg:` classes written
             here — one owner for what a sheet is. --}}
        <div
            class="w-full shrink-0 lg:w-72"
            data-testid="media-detail" @wireEl('media-detail')
            x-data
            x-on:keydown.escape.window="$wire.closeDetail()"
        >
            <div
                {{-- The backdrop, below the breakpoint only. It closes the panel,
                     because tapping beside a sheet is how a sheet is dismissed. --}}
                class="{{ \NyonCode\WireCore\Foundation\Support\MobileSheet::backdropHide('lg') }} fixed inset-0 z-30 bg-gray-900/40"
                wire:click="closeDetail"
                data-testid="media-detail-backdrop" @wireEl('media-detail-backdrop')
            ></div>

            <div class="{{ \NyonCode\WireCore\Foundation\Support\MobileSheet::panel('lg') }} relative z-40 rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
                <span class="{{ \NyonCode\WireCore\Foundation\Support\MobileSheet::grabberShow('lg') }} justify-center pt-2">
                    <span class="h-1 w-10 rounded-full bg-gray-300 dark:bg-gray-700"></span>
                </span>
                <div class="flex items-center justify-between border-b border-gray-200 px-3 py-2 dark:border-gray-800">
                    <p class="truncate text-sm font-medium">{{ $detail->name }}</p>
                    <button
                        type="button"
                        wire:click="closeDetail"
                        data-testid="media-detail-close" @wireEl('media-detail-close')
                        class="rounded-sm p-1 text-gray-400 hover:text-gray-700 dark:hover:text-gray-200"
                    >
                        <span class="sr-only">{{ __('wire-module-media::messages.close') }}</span>
                        {!! icon('outline:x-mark', 'h-4 w-4') !!}
                    </button>
                </div>

                <div class="p-3">
                    <div class="mb-3 flex items-center justify-center rounded-lg bg-gray-50 p-2 dark:bg-gray-800">
                        @if ($detail->isImage() && $detail->previewUrl('preview'))
                            {{-- The preview copy, not the original: a panel
                                 showing a photograph at 160 pixels tall has no
                                 use for twelve megapixels of it. --}}
                            <img
                                src="{{ $detail->previewUrl('preview') }}"
                                alt="{{ $detail->altText() }}"
                                class="max-h-40 rounded-sm object-contain"
                                @if ($detail->placeholder) style="background-color: {{ $detail->placeholder }}" @endif
                                decoding="async"
                            >
                        @else
                            <x-wire::file-thumb
                                :name="$detail->name"
                                :mime="$detail->mime_type"
                                size="lg"
                                class="max-h-40 rounded-sm"
                            />
                        @endif
                    </div>

                    {{-- Alt text lives on the file, not beside each use of it: a
                         description of a photograph is true of the photograph,
                         and asking again at every insertion is how half the
                         copies end up empty. --}}
                    <form wire:submit="saveDetail" class="space-y-2">
                        <label class="block">
                            <span class="text-[11px] font-semibold tracking-wider text-gray-400 uppercase">{{ __('wire-module-media::messages.alt') }}</span>
                            <input
                                type="text"
                                wire:model="detailAlt"
                                data-testid="media-detail-alt" @wireEl('media-detail-alt')
                                class="mt-1 w-full rounded-lg border border-gray-200 px-2 py-1 text-sm dark:border-gray-700 dark:bg-gray-800"
                            >
                        </label>

                        <label class="block">
                            <span class="text-[11px] font-semibold tracking-wider text-gray-400 uppercase">{{ __('wire-module-media::messages.title') }}</span>
                            <input
                                type="text"
                                wire:model="detailTitle"
                                data-testid="media-detail-title" @wireEl('media-detail-title')
                                class="mt-1 w-full rounded-lg border border-gray-200 px-2 py-1 text-sm dark:border-gray-700 dark:bg-gray-800"
                            >
                        </label>

                        <button
                            type="submit"
                            data-testid="media-detail-save" @wireEl('media-detail-save')
                            class="bg-primary-600 hover:bg-primary-700 w-full rounded-lg px-3 py-1.5 text-sm font-medium text-white"
                        >{{ __('wire-module-media::messages.save') }}</button>
                    </form>

                    <dl class="mt-4 space-y-1 text-xs">
                        <div class="flex justify-between gap-2">
                            <dt class="text-gray-400">{{ __('wire-module-media::messages.type') }}</dt>
                            <dd class="truncate text-gray-600 dark:text-gray-300">{{ $detail->mime_type }}</dd>
                        </div>
                        <div class="flex justify-between gap-2">
                            <dt class="text-gray-400">{{ __('wire-module-media::messages.size') }}</dt>
                            <dd class="text-gray-600 dark:text-gray-300">{{ $detail->humanSize() }}</dd>
                        </div>
                        @if ($detail->dimensions())
                            <div class="flex justify-between gap-2">
                                <dt class="text-gray-400">{{ __('wire-module-media::messages.dimensions') }}</dt>
                                <dd class="text-gray-600 dark:text-gray-300">{{ $detail->dimensions() }}</dd>
                            </div>
                        @endif
                        <div class="flex justify-between gap-2">
                            <dt class="text-gray-400">{{ __('wire-module-media::messages.uploaded') }}</dt>
                            <dd class="text-gray-600 dark:text-gray-300">{{ $detail->created_at?->isoFormat('L') }}</dd>
                        </div>
                    </dl>

                    {{-- Where this file is used, as far as anything can know.
                         The editor keeps the media id on every picture it
                         inserts, so a body of text records itself on save; a URL
                         somebody pasted in by hand is invisible to this and
                         always will be, which is why the count is presented as a
                         floor rather than as an answer (ADR 0034). --}}
                    <div class="mt-4" data-testid="media-usage" @wireEl('media-usage')>
                        <p class="text-[11px] font-semibold tracking-wider text-gray-400 uppercase">
                            {{ trans_choice('wire-module-media::messages.known_uses', count($detailUsages), ['count' => count($detailUsages)]) }}
                        </p>

                        @if ($detailUsages === [])
                            <p class="mt-1 text-xs text-gray-400">{{ __('wire-module-media::messages.no_known_uses') }}</p>
                        @else
                            <ul class="mt-1 space-y-1 text-xs">
                                @foreach ($detailUsages as $use)
                                    <li class="flex items-center gap-2">
                                        <span class="rounded-sm bg-gray-100 px-1.5 py-0.5 font-mono text-[10px] text-gray-500 dark:bg-gray-800 dark:text-gray-400">{{ $use['collection'] }}</span>

                                        @if ($use['url'])
                                            <a href="{{ $use['url'] }}" class="text-primary-600 dark:text-primary-400 truncate hover:underline">{{ $use['label'] }}</a>
                                        @else
                                            <span class="truncate text-gray-600 dark:text-gray-300">{{ $use['label'] }}</span>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>

                            <p class="mt-1 text-[11px] text-gray-400">{{ __('wire-module-media::messages.uses_are_a_floor') }}</p>
                        @endif
                    </div>

                    @if ($detail->isImage() && $detail->mime_type !== 'image/svg+xml')
                        <button
                            type="button"
                            wire:click="openEditor({{ $detail->id }})"
                            data-testid="media-edit" @wireEl('media-edit')
                            class="mt-3 flex w-full items-center justify-center gap-1.5 rounded-lg border border-gray-200 px-3 py-1.5 text-sm text-gray-600 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800"
                        >
                            {!! icon('outline:pencil-square', 'h-4 w-4') !!}
                            {{ __('wire-module-media::messages.edit') }}
                        </button>
                    @endif

                    @if ($detail->derivedFrom || $detail->derivatives()->exists())
                        {{-- Which file this was cut from, or what has been cut
                             from it. Visible rather than implied by a name, so a
                             crop can be traced back to its original. --}}
                        <div class="mt-4" data-testid="media-derivatives" @wireEl('media-derivatives')>
                            @if ($detail->derivedFrom)
                                <p class="text-[11px] font-semibold tracking-wider text-gray-400 uppercase">{{ __('wire-module-media::messages.derived_from') }}</p>
                                <button type="button" wire:click="showDetail({{ $detail->derivedFrom->id }})" class="text-primary-600 dark:text-primary-400 mt-1 block max-w-full truncate text-xs hover:underline">{{ $detail->derivedFrom->name }}</button>
                            @endif

                            @if ($detail->derivatives()->exists())
                                <p class="mt-2 text-[11px] font-semibold tracking-wider text-gray-400 uppercase">{{ __('wire-module-media::messages.derivatives') }}</p>
                                <ul class="mt-1 space-y-0.5">
                                    @foreach ($detail->derivatives as $derivative)
                                        <li>
                                            <button type="button" wire:click="showDetail({{ $derivative->id }})" class="text-primary-600 dark:text-primary-400 block max-w-full truncate text-xs hover:underline">{{ $derivative->name }}</button>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                    @endif

                    @if ($detail->url())
                        <div class="mt-3 flex gap-2">
                            <a
                                href="{{ $detail->url() }}"
                                target="_blank"
                                rel="noopener"
                                data-testid="media-open-url" @wireEl('media-open-url')
                                class="inline-flex flex-1 items-center justify-center gap-1.5 rounded-lg border border-gray-200 px-3 py-1.5 text-sm text-gray-600 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800"
                            >
                                {!! icon('outline:arrow-top-right-on-square', 'h-4 w-4') !!}
                                {{ __('wire-module-media::messages.open') }}
                            </a>

                            <a
                                {{-- The streamed route where there is one: a
                                     private disk has no public URL to link to,
                                     and `download` on a missing href is a link
                                     that does nothing. --}}
                                href="{{ $detail->downloadUrl() }}"
                                download="{{ $detail->name }}"
                                data-testid="media-download" @wireEl('media-download')
                                class="inline-flex flex-1 items-center justify-center gap-1.5 rounded-lg border border-gray-200 px-3 py-1.5 text-sm text-gray-600 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800"
                            >
                                {!! icon('outline:arrow-down-tray', 'h-4 w-4') !!}
                                {{ __('wire-module-media::messages.download') }}
                            </a>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif

    @if ($editing)
        @include('wire-module-media::partials.editor')
    @endif
</div>
