@extends('layouts.preview', [
    'title' => 'Wire Sortable',
    'subtitle' => 'Task board preview rendered through the sortable table view with visible drag handles and order state.',
])

@section('content')
    @livewire(\Workbench\App\Livewire\Previews\SortablePreview::class)
@endsection
