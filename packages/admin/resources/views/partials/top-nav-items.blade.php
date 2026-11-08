{{-- Rows inside a panel of the bar: each entry, and one level of its children
     indented under it — the third level is not drawn, as in the sidebar. --}}
<ul class="space-y-0.5">
    @foreach ($items as $key => $item)
        <li>
            {{-- A parent is the heading of its children, never a link — the same
                 rule as the sidebar: a page that belongs in the menu is one of
                 the children. --}}
            @if ($item->hasChildren())
                <p
                    data-testid="{{ $testid }}-heading"
                    @if ($key !== null) data-resource="{{ $key }}" @endif
                    class="flex items-center gap-2 px-3 pt-2 pb-1 text-[11px] font-semibold tracking-wider text-gray-500 uppercase dark:text-gray-400"
                >
                    @if ($item->getIcon())
                        {!! icon($item->getIcon(), 'h-4 w-4 shrink-0') !!}
                    @endif
                    <span class="truncate">{{ $item->getLabel() }}</span>
                </p>
            @else
                @include('wire-admin::partials.top-nav-link', ['item' => $item, 'itemKey' => $key, 'testid' => $testid, 'inBar' => false])
            @endif

            @if ($item->hasChildren())
                <ul class="ms-5 mt-0.5 space-y-0.5 border-s border-gray-200 ps-2 dark:border-gray-700">
                    @foreach ($item->getChildren() as $child)
                        <li>@include('wire-admin::partials.top-nav-link', ['item' => $child, 'itemKey' => null, 'testid' => $testid.'-child', 'inBar' => false])</li>
                    @endforeach
                </ul>
            @endif
        </li>
    @endforeach
</ul>
