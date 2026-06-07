{{-- Copyable button partial --}}
{{-- Variables: $content, $copyValue, $copyMessage --}}
<span class="inline-flex items-center gap-1.5 group" x-data="{ copied: false }">
    {!! $content !!}
    <button
        type="button"
        x-on:click="
            navigator.clipboard.writeText(@js((string) $copyValue));
            copied = true;
            setTimeout(() => copied = false, 2000);
        "
        class="opacity-0 group-hover:opacity-100 transition-opacity p-0.5 rounded hover:bg-gray-100 dark:hover:bg-gray-700"
        title="{{ __('wire-table::messages.copy') }}"
    >
        <template x-if="!copied">
            <x-wire::icon name="clipboard-document" size="w-4 h-4" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300" />
        </template>
        <template x-if="copied">
            <x-wire::icon name="check" size="w-4 h-4" class="text-emerald-500" />
        </template>
    </button>
    <span
        x-show="copied"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 translate-x-1"
        x-transition:enter-end="opacity-100 translate-x-0"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="text-xs text-emerald-600 dark:text-emerald-400 font-medium"
    >{{ $copyMessage }}</span>
</span>
