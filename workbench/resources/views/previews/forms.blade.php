@extends('layouts.preview', [
    'title' => 'Wire Forms',
    'subtitle' => 'Schema-driven form preview with section layouts, toggles, textarea content, and a reorderable repeater.',
])

@section('content')
    @livewire(\Workbench\App\Livewire\Previews\FormPreview::class)
@endsection
