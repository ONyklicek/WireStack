{{-- Rows inside a panel of the bar: each entry, and one level of its children
     indented under it — the third level is not drawn, as in the sidebar. --}}
<ul class="space-y-0.5">
    @foreach ($items as $key => $item)
        <li>
            @include('wire-admin::partials.top-nav-link', ['item' => $item, 'itemKey' => $key, 'testid' => $testid, 'inBar' => false])

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
