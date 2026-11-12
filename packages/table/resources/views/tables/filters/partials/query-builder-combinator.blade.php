{{-- "All of" / "Any of" for a query-builder group: two radio buttons drawn as
     a segmented control. Live — it changes how every condition below combines. --}}
{{-- Variables: $path (state path of the combinator), $combinator --}}
<div class="inline-flex rounded-lg bg-gray-100 p-0.5 text-xs font-medium dark:bg-gray-700" role="radiogroup">
    @foreach(['and' => __('wire-table::messages.query_builder_all'), 'or' => __('wire-table::messages.query_builder_any')] as $option => $optionLabel)
        <label @class([
            'cursor-pointer rounded-md px-2.5 py-1 transition-colors',
            'bg-white text-gray-900 shadow-sm dark:bg-gray-800 dark:text-white' => $combinator === $option,
            'text-gray-600 hover:text-gray-900 dark:text-gray-300 dark:hover:text-white' => $combinator !== $option,
        ])>
            <input type="radio" class="sr-only" value="{{ $option }}" wire:model.live="{{ $path }}" @checked($combinator === $option)>
            {{ $optionLabel }}
        </label>
    @endforeach
</div>
