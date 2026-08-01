{{-- Builder: a repeater whose every item picks its own block type. Item chrome
     matches the repeater card; only the schema and the add trigger differ. --}}
@php
    use NyonCode\WireForms\Components\Builder;

    assert($field instanceof Builder);

    $statePath = $field->getStatePath();
    $items = data_get($this, $statePath, []);
    if (!is_array($items)) $items = [];
    $itemCount = count($items);
    $blocks = $field->getBlocks();
@endphp

<div
    {{-- x-data must stay byte-identical across Livewire morphs — see the repeater
         view: keying collapse state by index keeps the attribute text static as
         items are added and removed. `adding` drives the block picker. --}}
    x-data="{
        adding: false,
        collapsed: {},
        isCollapsed(index) {
            return this.collapsed[index] ?? {{ $field->isCollapsed() ? 'true' : 'false' }};
        },
        toggleCollapse(index) {
            this.collapsed[index] = !this.isCollapsed(index);
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
        @php
            $type = $field->getItemType($item);
            $block = $type !== null ? $field->getBlock($type) : null;
        @endphp

        <div
            x-sortable-item="{{ $index }}"
            wire:key="builder-{{ $statePath }}-{{ $index }}"
            class="rounded-lg border border-gray-200 bg-white dark:border-gray-600 dark:bg-gray-800"
        >
            <div class="flex items-center justify-between border-b border-gray-200 px-4 py-2 dark:border-gray-600">
                <div class="flex items-center gap-2">
                    @if($field->isReorderable())
                        <button type="button" x-sortable-handle data-testid="form-builder-{{ $statePath }}-reorder-{{ $index }}" aria-label="{{ __('Reorder') }}" class="cursor-grab text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                            {!! icon('outline:bars-3', 'w-4 h-4', 'w-4 h-4') !!}
                        </button>
                    @endif

                    <span class="flex items-center gap-1.5 text-sm font-medium text-gray-700 dark:text-gray-300">
                        @if($block?->getIcon())
                            {!! icon($block->getIcon(), 'w-4 h-4', 'text-gray-400') !!}
                        @endif
                        {{-- An item whose block is gone still names its stored type, so
                             the content can be recognised and moved rather than lost. --}}
                        {{ $block?->getLabel() ?? $type ?? __('Unknown block') }}
                    </span>
                </div>

                <div class="flex items-center gap-1">
                    @if($field->isCollapsible())
                        <button type="button" @click="toggleCollapse({{ $index }})" class="p-1 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                            {!! icon('chevron-down', 'w-4 h-4', 'w-4 h-4 transition-transform', '', [':class' => "{ 'rotate-180': !isCollapsed({$index}) }"]) !!}
                        </button>
                    @endif

                    @if($field->isDeletable() && ($field->getMinItems() === null || $itemCount > $field->getMinItems()))
                        <button
                            type="button"
                            wire:click="removeRepeaterItem('{{ $statePath }}', {{ $index }})"
                            data-testid="form-builder-{{ $statePath }}-remove-{{ $index }}"
                            aria-label="{{ __('Remove') }}"
                            class="p-1 text-red-400 hover:text-red-600"
                        >
                            {!! icon('trash', 'w-4 h-4', 'w-4 h-4') !!}
                        </button>
                    @endif
                </div>
            </div>

            <div x-show="!isCollapsed({{ $index }})" x-collapse class="space-y-4 p-4">
                @foreach($field->getItemSchema($index, $type ?? '') as $component)
                    @if($component->isVisible())
                        {{ $component }}
                    @endif
                @endforeach
            </div>
        </div>
    @endforeach

    @if($field->isAddable() && ($field->getMaxItems() === null || $itemCount < $field->getMaxItems()))
        <div class="relative">
            <button
                type="button"
                @click="adding = !adding"
                data-testid="form-builder-{{ $statePath }}-add"
                class="flex w-full items-center justify-center gap-1 rounded-lg border border-dashed border-gray-300 px-3 py-2 text-sm font-medium text-primary-600 transition-colors hover:border-primary-300 hover:text-primary-800 dark:border-gray-600 dark:text-primary-400 dark:hover:border-primary-500 dark:hover:text-primary-300"
            >
                {!! icon('plus', 'w-4 h-4', 'w-4 h-4') !!}
                {{ $field->getAddButtonLabel() }}
            </button>

            {{-- The picker is what makes this a builder rather than a repeater:
                 adding an item means choosing which block to add. --}}
            <div
                x-show="adding"
                x-cloak
                @click.outside="adding = false"
                class="absolute z-10 mt-1 w-full overflow-hidden rounded-lg border border-gray-200 bg-white shadow-lg dark:border-gray-600 dark:bg-gray-800"
            >
                @foreach($blocks as $block)
                    <button
                        type="button"
                        wire:click="addBuilderItem('{{ $statePath }}', '{{ $block->getName() }}')"
                        @click="adding = false"
                        data-testid="form-builder-{{ $statePath }}-add-{{ $block->getName() }}"
                        class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-gray-700 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-gray-700"
                    >
                        @if($block->getIcon())
                            {!! icon($block->getIcon(), 'w-4 h-4', 'text-gray-400') !!}
                        @endif
                        {{ $block->getLabel() }}
                    </button>
                @endforeach
            </div>
        </div>
    @endif
</div>
