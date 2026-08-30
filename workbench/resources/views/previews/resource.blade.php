@extends('layouts.preview', [
    'title' => $title,
    'subtitle' => $subtitle,
    'captureOnly' => true,
])

@section('content')
    @livewire($component, $params, key($component))
@endsection
