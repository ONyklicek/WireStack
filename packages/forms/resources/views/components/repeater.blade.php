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
                            <x-wire::icon name="outline:bars-3" class="w-4 h-4" />
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
                            <x-wire::icon name="chevron-down" class="w-4 h-4 transition-transform" ::class="{ 'rotate-180': !collapsed[{{ $index }}] }" />
                        </button>
                    @endif

                    @if($field->isDeletable() && ($field->getMinItems() === null || $itemCount > $field->getMinItems()))
                        <button
                            type="button"
                            wire:click="removeRepeaterItem('{{ $statePath }}', {{ $index }})"
                            class="p-1 text-red-400 hover:text-red-600"
                        >
                            <x-wire::icon name="trash" class="w-4 h-4" />
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
                    {{ $component }}
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
            <x-wire::icon name="plus" class="w-4 h-4" />
            {{ $field->getAddButtonLabel() }}
        </button>
    @endif
</div>
