@extends('layouts.preview', [
    'title' => 'Wire Table',
    'subtitle' => 'Live table preview with filters, header actions, selection, sortable columns, and row-level actions.',
])

@section('content')
    @livewire(\Workbench\App\Livewire\Previews\TablePreview::class)
@endsection
