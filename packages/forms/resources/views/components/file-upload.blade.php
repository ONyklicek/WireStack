@php /** @var \NyonCode\WireForms\Components\FileUpload $field */
    $wireModifier = $field->getWireModelModifier();
    $wireAttr = 'wire:model' . ($wireModifier ? ".{$wireModifier}" : '');
    $acceptedTypes = $field->getAcceptedFileTypes();
@endphp

@include('wire-forms::partials.field-wrapper-start')

    <input
        type="file"
        id="{{ $field->getId() }}"
        {{ $wireAttr }}="{{ $field->getWireModelAttribute() }}"
        @if(!empty($acceptedTypes)) accept="{{ implode(',', $acceptedTypes) }}" @endif
        @if($field->isMultiple()) multiple @endif
        @if($field->isDisabled()) disabled @endif
        @if($field->isRequired()) required @endif
        @class([
            'block w-full text-sm text-gray-500',
            'file:mr-4 file:py-2 file:px-4',
            'file:rounded-md file:border-0',
            'file:text-sm file:font-medium',
            'file:bg-primary-50 file:text-primary-700',
            'hover:file:bg-primary-100',
            'dark:text-gray-400 dark:file:bg-gray-700 dark:file:text-gray-300',
            'border-red-500' => $errors->has($field->getStatePath()),
        ])
    />

@include('wire-forms::partials.field-wrapper-end')
