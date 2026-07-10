{{-- Shared chevron overlay for select-like header filters (single/multi/boolean).
     Centered via an inset-y-0 flex wrapper (robust regardless of whether the
     `top-1/2` utility is generated); the control reserves room with `pr-7`
     (see NyonCode\WireTable\Support\FilterControl::classes(withChevron: true)). --}}
<span class="pointer-events-none absolute inset-y-0 right-2 flex items-center">
    <svg class="h-3.5 w-3.5 text-gray-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
        <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
    </svg>
</span>
