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
                        <li class="group/row relative">
                            <a
                                @if ($item->getUrl()) href="{{ $item->getUrl() }}" @wireNavigate($item->getUrl()) @endif
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
                                <span data-rail-hide class="flex-1 truncate group-hover/row:pe-7 group-focus-within/row:pe-7">{{ $item->getLabel() }}</span>
                            </a>

                            {{-- The way back out of this section, where the pin
                                 went in: unpin a pinned entry, pin a recent one.
                                 Beside the link, not in it, and only while the
                                 row is pointed at or focused — like the pin on
                                 the rows below. --}}
                            <button
                                type="button"
                                data-rail-hide
                                wire:click="toggle(@js($key))"
                                title="{{ __('wire-admin::messages.'.($section === 'pinned' ? 'unpin' : 'pin')) }}"
                                aria-label="{{ __('wire-admin::messages.'.($section === 'pinned' ? 'unpin' : 'pin')) }}: {{ $item->getLabel() }}"
                                data-testid="admin-nav-{{ $section }}-toggle" @wireEl('admin-nav-'.$section.'-toggle')
                                data-resource="{{ $key }}"
                                class="absolute end-1.5 top-1/2 -translate-y-1/2 rounded-md p-1 text-gray-400 opacity-0 transition group-hover/row:opacity-100 group-focus-within/row:opacity-100 hover:bg-gray-900/5 hover:text-gray-700 focus-visible:opacity-100 dark:text-gray-500 dark:hover:bg-white/10 dark:hover:text-gray-200"
                            >{!! icon($section === 'pinned' ? 'outline:bookmark-slash' : 'outline:bookmark', 'h-4 w-4') !!}</button>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    @endforeach
</div>
