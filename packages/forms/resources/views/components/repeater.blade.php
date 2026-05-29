@php
    use NyonCode\WireForms\Components\Repeater;

    assert($field instanceof Repeater);

    $statePath = $field->getStatePath();
    $items = data_get($this, $statePath, []);
    if (!is_array($items)) $items = [];
    $itemCount = count($items);
@endphp

<div
    x-data="{
        collapsed: @js(array_fill(0, $itemCount, $field->isCollapsed())),
        toggleCollapse(index) {
            this.collapsed[index] = !this.collapsed[index];
        }
    }"
    @if($field->isReorderable())
        x-sortable
        x-on:sort-end.camel="
            let sorted = [];
            $el.querySelectorAll('[x-sortable-item]').forEach(el => {
                sorted.push(parseInt(el.getAttribute('x-sortable-item')));
            });
            $wire.reorderRepeaterItems('{{ $statePath }}', sorted);
        "
    @endif
    class="space-y-2"
>
    @if($field->getLabel())
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
            {{ $field->getLabel() }}
        </label>
    @endif

    @foreach($items as $index => $item)
        <div
            x-sortable-item="{{ $index }}"
            @class([
                'border border-gray-200 dark:border-gray-600 rounded-lg',
                'bg-white dark:bg-gray-800',
            ])
        >
            <div class="flex items-center justify-between px-4 py-2 border-b border-gray-200 dark:border-gray-600">
                <div class="flex items-center gap-2">
                    @if($field->isReorderable())
                        <button type="button" x-sortable-handle class="cursor-grab text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                            <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                            </svg>
                        </button>
                    @endif

                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
                        #{{ $index + 1 }}
                    </span>
                </div>

                <div class="flex items-center gap-1">
                    @if($field->isCollapsible())
                        <button
                            type="button"
                            @click="toggleCollapse({{ $index }})"
                            class="p-1 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                        >
                            <svg class="w-4 h-4 transition-transform" :class="{ 'rotate-180': !collapsed[{{ $index }}] }" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
                            </svg>
                        </button>
                    @endif

                    @if($field->isDeletable() && ($field->getMinItems() === null || $itemCount > $field->getMinItems()))
                        <button
                            type="button"
                            wire:click="removeRepeaterItem('{{ $statePath }}', {{ $index }})"
                            class="p-1 text-red-400 hover:text-red-600"
                        >
                            <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M8.75 1A2.75 2.75 0 006 3.75v.443c-.795.077-1.584.176-2.365.298a.75.75 0 10.23 1.482l.149-.022.841 10.518A2.75 2.75 0 007.596 19h4.807a2.75 2.75 0 002.742-2.53l.841-10.52.149.023a.75.75 0 00.23-1.482A41.03 41.03 0 0014 4.193V3.75A2.75 2.75 0 0011.25 1h-2.5z" clip-rule="evenodd" />
                            </svg>
                        </button>
                    @endif
                </div>
            </div>

            <div
                x-show="!collapsed[{{ $index }}]"
                x-collapse
                class="p-4 space-y-4"
            >
                @foreach($field->getItemSchema($index) as $component)
                    {!! $component->toHtml() !!}
                @endforeach
            </div>
        </div>
    @endforeach

    @if($field->isAddable() && ($field->getMaxItems() === null || $itemCount < $field->getMaxItems()))
        <button
            type="button"
            wire:click="addRepeaterItem('{{ $statePath }}')"
            class="flex items-center gap-1 px-3 py-2 text-sm font-medium text-primary-600 dark:text-primary-400 hover:text-primary-800 dark:hover:text-primary-300 border border-dashed border-gray-300 dark:border-gray-600 rounded-lg w-full justify-center hover:border-primary-300 dark:hover:border-primary-500 transition-colors"
        >
            <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                <path d="M10.75 4.75a.75.75 0 00-1.5 0v4.5h-4.5a.75.75 0 000 1.5h4.5v4.5a.75.75 0 001.5 0v-4.5h4.5a.75.75 0 000-1.5h-4.5v-4.5z" />
            </svg>
            {{ $field->getAddButtonLabel() }}
        </button>
    @endif
</div>
