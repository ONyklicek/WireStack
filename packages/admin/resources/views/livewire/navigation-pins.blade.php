{{-- Pinned and recent entries, above the groups. Absent while both are empty:
     an empty "Pinned" heading is a heading that explains nothing.

     The rows are copies of rows further down, so they carry their own names
     (`admin-nav-pinned-item`, `admin-nav-recent-item`) and no `data-nav-row`:
     the filter narrows the menu, not these, and a driver counting menu rows
     must not meet each pinned entry twice. --}}
<div
    x-on:wire-admin-pin.window="$wire.toggle($event.detail.key)"
    data-testid="admin-nav-memory" @wireEl('admin-nav-memory')
>
    @foreach (['pinned' => $pinned, 'recent' => $recent] as $section => $entries)
        @if ($entries !== [])
            <div class="mb-5" data-testid="admin-nav-{{ $section }}">
                <p data-rail-hide class="px-3 py-1.5 text-[11px] font-semibold tracking-wider text-gray-500 dark:text-gray-400 uppercase">
                    {{ __('wire-admin::messages.'.$section) }}
                </p>
                <ul class="mt-1 space-y-0.5">
                    @foreach ($entries as $key => $item)
                        <li>
                            <a
                                @if ($item->getUrl()) href="{{ $item->getUrl() }}" wire:navigate @endif
                                data-testid="admin-nav-{{ $section }}-item"
                                data-nav-focus
                                data-resource="{{ $key }}"
                                @if ($active->isActive($item, $key)) data-active="true" @endif
                                aria-label="{{ $item->getLabel() }}"
                                data-rail-row
                                class="group relative flex w-full items-center gap-3 rounded-lg px-3 py-2 text-sm text-gray-600 transition hover:bg-gray-100 hover:text-gray-900 dark:text-gray-300 dark:hover:bg-gray-800 dark:hover:text-white"
                            >
                                <span class="flex h-5 w-5 shrink-0 items-center justify-center">
                                    {!! icon($item->getIcon() ?? ($section === 'pinned' ? 'outline:bookmark' : 'outline:clock'), 'h-5 w-5') !!}
                                </span>
                                <span data-rail-hide class="flex-1 truncate">{{ $item->getLabel() }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    @endforeach
</div>
