@extends('layouts.preview', [
    'title' => 'Wire Core',
    'subtitle' => 'Shared action, widget, and modal primitives that the table and form packages build on.',
])

@section('content')
    @livewire(\Workbench\App\Livewire\Previews\CorePreview::class)
@endsection
