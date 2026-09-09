{{-- One branch of the folder tree, drawn again for each level.

     Recursive by include rather than by a component, so the whole tree comes
     from the single query the manager already made — a component per node would
     be one more resolution per folder for markup this simple.

     Collapsing is Alpine's, not the server's: which branches somebody has open
     is not a thing the library needs to know, and a round trip to fold a folder
     away is a round trip nobody asked for. The state lives on the tree's root
     element and survives a reload through `localStorage`. --}}
@foreach ($branch as $node)
    @php($children = $folders[$node->id] ?? [])

    <li>
        <div
            draggable="true"
            x-on:dragstart="$event.dataTransfer.setData('wire/folder', '{{ $node->id }}')"
            x-on:dragover.prevent="
                $el.classList.add('ring-2','ring-primary-400');
                {{-- A branch you are dragging onto opens itself, because the
                     folder you want is very often inside it and letting go to
                     open it loses the drag. --}}
                @if ($children) expandOnHover({{ $node->id }}) @endif
            "
            x-on:dragleave="$el.classList.remove('ring-2','ring-primary-400'); cancelHover()"
            x-on:drop.prevent="
                $el.classList.remove('ring-2','ring-primary-400');
                cancelHover();
                const file = $event.dataTransfer.getData('wire/media');
                const folder = $event.dataTransfer.getData('wire/folder');
                // `selection` rather than an id: a tile that is part of the
                // selection drags all of it, and the server already knows what
                // that is.
                if (file === 'selection') $wire.moveTo({{ $node->id }}, null);
                else if (file) $wire.moveTo({{ $node->id }}, parseInt(file));
                else if (folder && parseInt(folder) !== {{ $node->id }}) $wire.moveFolder(parseInt(folder), {{ $node->id }});
            "
            data-testid="media-folder" @wireEl('media-folder')
            data-folder="{{ $node->id }}"
            @class([
                'group flex items-center gap-1 rounded-lg pe-1 text-sm transition',
                'bg-primary-50 text-primary-700 dark:bg-primary-950/60 dark:text-primary-200' => $current === $node->id,
                'text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800' => $current !== $node->id,
            ])
            style="padding-inline-start: {{ 0.25 + $depth * 0.75 }}rem"
        >
            @if ($children)
                <button
                    type="button"
                    x-on:click="toggle({{ $node->id }})"
                    data-testid="media-folder-twisty" @wireEl('media-folder-twisty')
                    class="shrink-0 rounded-sm p-0.5 text-gray-400 hover:text-gray-700 dark:hover:text-gray-200"
                    :aria-expanded="isOpen({{ $node->id }}) ? 'true' : 'false'"
                >
                    <span class="sr-only">{{ $node->name }}</span>
                    <span class="block transition-transform" :class="isOpen({{ $node->id }}) ? '' : '-rotate-90'">
                        {!! icon('outline:chevron-down', 'h-3 w-3') !!}
                    </span>
                </button>
            @else
                {{-- A leaf keeps the triangle's width, so the names line up. --}}
                <span class="w-4 shrink-0"></span>
            @endif

            <button
                type="button"
                wire:click="openFolder({{ $node->id }})"
                data-testid="media-folder-open" @wireEl('media-folder-open')
                class="flex min-w-0 flex-1 items-center gap-2 py-1.5 text-start"
            >
                {!! icon($current === $node->id ? 'outline:folder-open' : 'outline:folder', 'h-4 w-4 shrink-0 text-gray-400') !!}
                <span class="truncate">{{ $node->name }}</span>
            </button>

            {{-- The count, and — on the library screen — the two buttons in its
                 place on hover: a tree with two buttons on every row is a tree
                 you cannot read the names in, and a tree with no numbers is one
                 you have to open every branch of to find anything. --}}
            <span @class([
                'shrink-0 font-mono text-[10px] text-gray-400 tabular-nums',
                // The count steps aside for the two buttons on hover — but only
                // where there are two buttons to step aside for.
                'group-hover:hidden' => ! $picking,
            ])>
                {{ $folderCounts[$node->id] ?? 0 }}
            </span>

            {{-- Not while picking. Renaming a folder, and above all deleting
                 one, is library housekeeping — it has nothing to do with the
                 question the modal was opened to ask, and a delete two pixels
                 from the folder somebody meant to open is an accident waiting
                 for a slow afternoon. Uploading stays: adding the picture you
                 are about to insert is the whole point of being here. --}}
            @unless ($picking)
                <span class="hidden shrink-0 items-center group-hover:flex">
                    <button
                        type="button"
                        x-on:click="
                            const name = window.prompt(@js(__('wire-module-media::messages.folder_name')), @js($node->name));
                            if (name) $wire.renameFolder({{ $node->id }}, name);
                        "
                        data-testid="media-folder-rename" @wireEl('media-folder-rename')
                        class="rounded-sm p-1 text-gray-400 hover:text-gray-700 dark:hover:text-gray-200"
                        title="{{ __('wire-module-media::messages.rename') }}"
                    >{!! icon('outline:pencil-square', 'h-3.5 w-3.5') !!}</button>

                    <button
                        type="button"
                        wire:click="deleteFolder({{ $node->id }})"
                        data-testid="media-folder-delete" @wireEl('media-folder-delete')
                        class="rounded-sm p-1 text-gray-400 hover:text-red-600"
                        title="{{ __('wire-module-media::messages.delete') }}"
                    >{!! icon('outline:trash', 'h-3.5 w-3.5') !!}</button>
                </span>
            @endunless
        </div>

        @if ($children)
            <ul x-show="isOpen({{ $node->id }})" x-cloak>
                @include('wire-module-media::partials.folder-branch', [
                    'branch' => $children,
                    'depth' => $depth + 1,
                ])
            </ul>
        @endif
    </li>
@endforeach
