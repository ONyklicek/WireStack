{{-- Modal Form Fields Renderer --}}
{{-- Uses Blade partials for each field type --}}
@php
    $fields = $modalData['fields'] ?? [];
    $validationErrors = $errors ?? null;
    $defaultWireModel = $wireModel ?? 'actionModalFormData';
@endphp

@if(!empty($fields))
    @include('wire-forms::fields._form-group', [
        'fields' => $fields,
        'validationErrors' => $validationErrors,
        'wireModel' => $defaultWireModel,
        'gridCols' => 1,
    ])
@endif
