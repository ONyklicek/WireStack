@extends('layouts.preview', [
    'title' => 'Wire component previews',
    'subtitle' => 'Internal runtime pages used to capture real screenshots from the package code.',
])

@section('content')
    <p class="mb-8 text-sm text-slate-500">
        {{ $total }} previews — every route registered by the workbench is listed here.
    </p>

    <div class="space-y-10">
        @foreach ($sections as $section => $previews)
            <section>
                <h2 class="mb-4 flex items-baseline gap-2 text-sm font-semibold uppercase tracking-wide text-slate-500">
                    {{ $section }}
                    <span class="text-xs font-normal normal-case text-slate-400">{{ count($previews) }}</span>
                </h2>

                <div class="grid gap-6 md:grid-cols-2 xl:grid-cols-4">
                    @foreach ($previews as $preview)
                        <a
                            href="{{ '/previews/'.$preview['slug'] }}"
                            class="block rounded-3xl border border-slate-200 bg-white p-6 shadow-sm transition hover:-translate-y-0.5 hover:bg-slate-50"
                        >
                            <p class="text-sm font-medium text-sky-700">{{ $preview['label'] }}</p>
                            <p class="mt-3 text-sm leading-6 text-slate-600">{{ $preview['copy'] }}</p>
                        </a>
                    @endforeach
                </div>
            </section>
        @endforeach
    </div>
@endsection
