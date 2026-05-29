@extends('layouts.preview', [
    'title' => $title,
    'subtitle' => $subtitle,
    'captureOnly' => true,
])

@section('content')
    @livewire($component, ['variant' => $variant], key($variant))
@endsection
