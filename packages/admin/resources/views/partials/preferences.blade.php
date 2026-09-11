{{-- The two preferences the shell itself owns: the theme, and the row density.

     One file, included from two places, because below `sm` they are not in the
     top bar at all. Seven controls in a 390-pixel strip is some seventy pixels
     more than there is — the bar overflowed, the page scrolled sideways, and the
     user menu (the last thing in the row) sat off the edge of the screen where
     nothing could reach it. A phone gets them at the foot of the drawer it
     already opens for the menu, which is where a phone looks for a setting; the
     top bar keeps them from `sm` up, where they fit.

     Three states for the theme, not a toggle, because the third is the one that
     carries its weight: a two-way switch forces a choice the moment it is
     touched and then keeps it for ever, so a laptop that dims itself in the
     evening stops being followed. The vocabulary is Theme's and Density's, not
     this file's.

     Parameters: `variant` (`topbar` | `nav`) picks the size and which breakpoint
     shows it; `only` (`theme` | `density`) renders one of the two, which is what
     lets the drawer put a caption beside each without this file interleaving
     `@if`s with its own opening tags.

     The `data-testid`s are suffixed in the drawer copy. Both copies are in the
     document at every width and CSS hides one of them, which `querySelector`
     knows nothing about: a driver clicking `[data-testid="admin-density-compact"]`
     would otherwise find the hidden one first and click nothing at all. The
     `data-wire` hook names are deliberately *not* suffixed — those are styling
     handles, and an application styling the theme switch means both of them. --}}
@php
    $nav = ($variant ?? 'topbar') === 'nav';
    $only = $only ?? null;
    $suffix = $nav ? '-nav' : '';
    // A thumb in the drawer, a cursor in the bar.
    $switch = $nav ? 'p-2' : 'p-1.5';
    // The drawer's copy is hidden from `sm` up and the bar's below it, so the
    // pair is never on the screen twice.
    $group = 'items-center gap-0.5 rounded-full border border-gray-200 p-0.5 dark:border-gray-700'
        .($nav ? ' inline-flex' : ' hidden sm:inline-flex');
@endphp

@if ($only !== 'density')
    <div
        x-data
        role="radiogroup"
        aria-label="{{ __('wire-admin::messages.theme') }}"
        data-testid="admin-theme{{ $suffix }}" @wireEl('admin-theme')
        class="{{ $group }}"
    >
        @foreach (\NyonCode\WireCore\Foundation\Enums\Theme::cases() as $themeOption)
            <button
                type="button"
                role="radio"
                x-on:click="$store.wireAdmin.setTheme('{{ $themeOption->value }}')"
                x-bind:aria-checked="$store.wireAdmin.theme === '{{ $themeOption->value }}' ? 'true' : 'false'"
                x-bind:class="$store.wireAdmin.theme === '{{ $themeOption->value }}'
                    ? 'bg-gray-100 text-gray-900 dark:bg-gray-700 dark:text-gray-50'
                    : 'text-gray-400 hover:text-gray-600 dark:hover:text-gray-300'"
                data-testid="admin-theme{{ $suffix }}-{{ $themeOption->value }}"
                title="{{ $themeOption->label() }}"
                class="inline-flex items-center rounded-full {{ $switch }} transition"
            >
                <span class="sr-only">{{ $themeOption->label() }}</span>
                {!! icon($themeOption->icon(), 'h-4 w-4') !!}
            </button>
        @endforeach
    </div>
@endif

{{-- The same shape as the theme switch beside it, and for the same reason: a
     person's own answer to a question the application only set a default for. --}}
@if ($only !== 'theme')
    <div
        x-data
        role="radiogroup"
        aria-label="{{ __('wire-core::messages.density') }}"
        data-testid="admin-density{{ $suffix }}" @wireEl('admin-density')
        class="{{ $group }}"
    >
        @foreach (\NyonCode\WireCore\Foundation\Enums\Density::cases() as $densityOption)
            <button
                type="button"
                role="radio"
                x-on:click="$store.wireAdmin.setDensity('{{ $densityOption->value }}')"
                x-bind:aria-checked="$store.wireAdmin.density === '{{ $densityOption->value }}' ? 'true' : 'false'"
                x-bind:class="$store.wireAdmin.density === '{{ $densityOption->value }}'
                    ? 'bg-gray-100 text-gray-900 dark:bg-gray-700 dark:text-gray-50'
                    : 'text-gray-400 hover:text-gray-600 dark:hover:text-gray-300'"
                data-testid="admin-density{{ $suffix }}-{{ $densityOption->value }}"
                title="{{ $densityOption->label() }}"
                class="inline-flex items-center rounded-full {{ $switch }} transition"
            >
                <span class="sr-only">{{ $densityOption->label() }}</span>
                {!! icon($densityOption->icon(), 'h-4 w-4') !!}
            </button>
        @endforeach
    </div>
@endif
