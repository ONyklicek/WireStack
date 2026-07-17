@php /** @var \NyonCode\WireForms\Components\CheckboxList $field */
    $wireModifier = $field->getWireModelModifier();
    $wireAttr = 'wire:model' . ($wireModifier ? ".{$wireModifier}" : '');
    $options = $field->getOptions();
    $columns = $field->getColumns();
    // Per-breakpoint map (['md' => 2, 'lg' => 3]) → literal grid-cols classes;
    // a plain int keeps the mobile-first reflow arms below.
    $columnsClass = is_array($columns) ? \NyonCode\WireCore\Foundation\Support\ResponsiveGrid::cols($columns) : '';
    // groups(['Fruit' => ['apple' => 'Apple'], …]) renders a heading per group.
    // Without an explicit map, grouped() alone has nothing to group by, so the
    // list stays flat rather than inventing a grouping.
    $groups = $field->isGrouped() ? $field->getGroups() : [];
@endphp

@include('wire-forms::partials.field-wrapper-start')

    <div
        x-data="{
            search: '',
            selectAll() { @this.set('{{ $field->getWireModelAttribute() }}', {{ json_encode(array_keys($options)) }}) },
            deselectAll() { @this.set('{{ $field->getWireModelAttribute() }}', []) },
        }"
        class="border border-gray-300 dark:border-gray-600 rounded-md overflow-hidden"
    >
        @if($field->isSearchable())
            <div class="p-2 border-b border-gray-200 dark:border-gray-700">
                <input
                    type="text"
                    x-model="search"
                    data-testid="form-checklist-{{ $field->getStatePath() }}-search"
                    placeholder="{{ $field->getSearchPrompt() }}"
                    class="block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 hover:border-gray-400 dark:hover:border-gray-500 transition-colors duration-150 dark:bg-gray-800 dark:border-gray-600 dark:text-white text-sm"
                />
            </div>
        @endif

        @if($field->isBulkToggleable())
            <div class="flex gap-2 px-3 py-2 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800">
                <button type="button" @click="selectAll()" data-testid="form-checklist-{{ $field->getStatePath() }}-select-all" class="text-xs text-primary-600 hover:text-primary-700 font-medium">
                    {{ $field->getSelectAllLabel() }}
                </button>
                <span class="text-gray-300 dark:text-gray-600">|</span>
                <button type="button" @click="deselectAll()" data-testid="form-checklist-{{ $field->getStatePath() }}-deselect-all" class="text-xs text-gray-500 hover:text-gray-700 dark:text-gray-400 font-medium">
                    {{ $field->getDeselectAllLabel() }}
                </button>
            </div>
        @endif

        <div class="max-h-60 overflow-y-auto p-3">
            {{-- Multi-column lists reflow down on narrow screens so option labels
                 stay readable on a phone (a 3–4 wide grid is unusable at 360px). --}}
            @php
                $gridClasses = \Illuminate\Support\Arr::toCssClasses([
                    'grid gap-2',
                    $columnsClass,
                    'grid-cols-1' => $columns === 1,
                    'grid-cols-1 sm:grid-cols-2' => $columns === 2,
                    'grid-cols-1 sm:grid-cols-3' => $columns === 3,
                    'grid-cols-2 sm:grid-cols-4' => $columns === 4,
                ]);
            @endphp

            @if($groups !== [])
                @foreach($groups as $groupLabel => $groupOptions)
                    <div class="mb-3 last:mb-0" data-testid="form-checklist-{{ $field->getStatePath() }}-group">
                        <p class="mb-1.5 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            {{ $groupLabel }}
                        </p>
                        <div class="{{ $gridClasses }}">
                            @foreach($groupOptions as $value => $label)
                                @include('wire-forms::partials.checkbox-list-option', compact('field', 'wireAttr', 'value', 'label'))
                            @endforeach
                        </div>
                    </div>
                @endforeach
            @else
                <div class="{{ $gridClasses }}">
                    @foreach($options as $value => $label)
                        @include('wire-forms::partials.checkbox-list-option', compact('field', 'wireAttr', 'value', 'label'))
                    @endforeach
                </div>
            @endif
        </div>
    </div>

@include('wire-forms::partials.field-wrapper-end')
