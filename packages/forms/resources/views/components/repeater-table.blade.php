{{-- Repeater in its table layout: one column per schema field, headed once.
     Same state paths, add/remove/reorder wiring and Livewire methods as the card
     layout — only the arrangement differs. --}}
@php
    use NyonCode\WireForms\Components\Repeater;

    assert($field instanceof Repeater);

    $statePath = $field->getStatePath();
    $items = data_get($this, $statePath, []);
    if (!is_array($items)) $items = [];
    $itemCount = count($items);
    $headings = $field->getTableHeadings();
@endphp

<div class="space-y-2">
    @if($field->getLabel())
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
            {{ $field->getLabel() }}
        </label>
    @endif

    <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-600">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-600">
            <thead class="bg-gray-50 dark:bg-gray-800">
                <tr>
                    @if($field->isReorderable())
                        <th scope="col" class="w-8 px-2 py-2"><span class="sr-only">{{ __('Reorder') }}</span></th>
                    @endif

                    @foreach($headings as $heading)
                        <th scope="col" class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            {{ $heading }}
                        </th>
                    @endforeach

                    @if($field->isDeletable())
                        <th scope="col" class="w-10 px-2 py-2"><span class="sr-only">{{ __('Remove') }}</span></th>
                    @endif
                </tr>
            </thead>

            <tbody
                class="divide-y divide-gray-200 bg-white dark:divide-gray-600 dark:bg-gray-800"
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
            >
                @foreach($items as $index => $item)
                    <tr x-sortable-item="{{ $index }}">
                        @if($field->isReorderable())
                            <td class="px-2 py-2 align-top">
                                <button type="button" x-sortable-handle data-testid="form-repeater-{{ $statePath }}-reorder-{{ $index }}" aria-label="{{ __('Reorder') }}" class="cursor-grab text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                                    {!! icon('outline:bars-3', 'w-4 h-4', 'w-4 h-4') !!}
                                </button>
                            </td>
                        @endif

                        @foreach($field->getItemSchema($index) as $component)
                            @php
                                // The column header already names the field, so a
                                // per-cell label would repeat it on every row.
                                if (method_exists($component, 'hiddenLabel')) {
                                    $component->hiddenLabel();
                                }
                            @endphp
                            <td class="px-3 py-2 align-top">
                                @if($component->isVisible()){{ $component }}@endif
                            </td>
                        @endforeach

                        @if($field->isDeletable())
                            <td class="px-2 py-2 align-top">
                                @if($field->getMinItems() === null || $itemCount > $field->getMinItems())
                                    <button
                                        type="button"
                                        wire:click="removeRepeaterItem('{{ $statePath }}', {{ $index }})"
                                        data-testid="form-repeater-{{ $statePath }}-remove-{{ $index }}"
                                        aria-label="{{ __('Remove') }}"
                                        class="p-1 text-red-400 hover:text-red-600"
                                    >
                                        {!! icon('trash', 'w-4 h-4', 'w-4 h-4') !!}
                                    </button>
                                @endif
                            </td>
                        @endif
                    </tr>
                @endforeach

                @if($items === [])
                    <tr>
                        <td colspan="{{ count($headings) + ($field->isReorderable() ? 1 : 0) + ($field->isDeletable() ? 1 : 0) }}" class="px-3 py-4 text-center text-sm text-gray-500 dark:text-gray-400">
                            {{ __('No items yet') }}
                        </td>
                    </tr>
                @endif
            </tbody>
        </table>
    </div>

    @if($field->isAddable() && ($field->getMaxItems() === null || $itemCount < $field->getMaxItems()))
        <button
            type="button"
            wire:click="addRepeaterItem('{{ $statePath }}')"
            data-testid="form-repeater-{{ $statePath }}-add"
            class="flex w-full items-center justify-center gap-1 rounded-lg border border-dashed border-gray-300 px-3 py-2 text-sm font-medium text-primary-600 transition-colors hover:border-primary-300 hover:text-primary-800 dark:border-gray-600 dark:text-primary-400 dark:hover:border-primary-500 dark:hover:text-primary-300"
        >
            {!! icon('plus', 'w-4 h-4', 'w-4 h-4') !!}
            {{ $field->getAddButtonLabel() }}
        </button>
    @endif
</div>
