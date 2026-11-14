{{-- The option list every cell of a SelectColumn opens — drawn once per table
     (SelectColumn::renderCellPanel()), cloned by the first trigger opened. --}}
{{-- Variables: $column, $panelId, $options, $mobileBreakpoint --}}
@php
    $panelFooter = $column->hasCreateOptionForm()
        ? view('wire-table::tables.columns.partials.select-create-option', [
            'actionName' => $column->getCreateOptionActionName(),
            'label' => __('wire-forms::fields.create_option'),
            'testId' => 'table-select-'.$column->getName().'-create-option',
        ])->render()
        : null;
@endphp
@include('wire-core::partials.select-control', [
    'part' => 'panel',
    'panelId' => $panelId,
    'options' => $options,
    // The list's empty row, which clears the cell — only where it may be empty.
    'placeholder' => $column->isPlaceholderSelectable() ? ($column->getPlaceholder() ?? __('wire-table::messages.select_placeholder')) : null,
    'multiple' => $column->isMultiple(),
    'searchable' => $column->hasSearchableOptions(),
    'searchPrompt' => __('Search...'),
    'noResultsMessage' => __('No results found'),
    'touch' => $column->usesTouchOnMobile(),
    'sheetTitle' => $column->getLabel(),
    'mobileBreakpoint' => $mobileBreakpoint,
    'panelFooter' => $panelFooter,
])
