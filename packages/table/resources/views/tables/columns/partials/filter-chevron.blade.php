{{-- Shared chevron overlay for select-like header filters (single/multi/boolean).
     Centered via an inset-y-0 flex wrapper (robust regardless of whether the
     `top-1/2` utility is generated); the control reserves room with `pr-7`
     (see NyonCode\WireTable\Support\FilterControl::classes(withChevron: true)). --}}
<span class="pointer-events-none absolute inset-y-0 right-2 flex items-center">
    {{-- Themeable via the canonical icon owner instead of a hardcoded SVG. --}}
    {!! app(\NyonCode\WireCore\Foundation\Icons\IconManager::class)->render('chevron-down', 'h-3.5 w-3.5', 'text-gray-400') !!}
</span>
