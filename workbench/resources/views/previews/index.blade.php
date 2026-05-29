@extends('layouts.preview', [
    'title' => 'Wire component previews',
    'subtitle' => 'Internal runtime pages used to capture real screenshots from the package code.',
])

@section('content')
    <div class="grid gap-6 md:grid-cols-2 xl:grid-cols-4">
        @foreach ($screens as $preview)
            <a
                href="{{ '/previews/'.$preview['slug'] }}"
                class="block rounded-3xl border border-slate-200 bg-white p-6 shadow-sm transition hover:-translate-y-0.5 hover:bg-slate-50"
            >
                <p class="text-sm font-medium text-sky-700">{{ $preview['label'] }}</p>
                <p class="mt-3 text-sm leading-6 text-slate-600">{{ $preview['copy'] }}</p>
            </a>
        @endforeach
    </div>
@endsection
