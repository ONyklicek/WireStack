{{-- The heading block every resource page opens with: the trail back, then the
     name of what you are looking at.

     One partial rather than the same six lines copied into five page views —
     they had already drifted (the dashboard grew no breadcrumbs at all), and a
     heading that is typeset differently on the edit page than on the list is a
     heading a reader re-reads.

     The type scale steps with the viewport: a 20px title fills a phone's width
     the way a 24px one fills a desktop's, so it is not one size clamped for the
     smallest screen. --}}
@php
    use NyonCode\WireCore\Core\Resources\View\Breadcrumbs;
    use NyonCode\WireCore\Foundation\View\ComponentRenderer;

    /** @var string|null $title */
    /** @var array<int, mixed> $breadcrumbs */
@endphp

@if(($breadcrumbs ?? []) !== [] || $title)
    <div class="space-y-1">
        {{-- The object, not <x-wire::breadcrumbs>: rule 5 keeps the component
             tags for consumers and has the framework draw without them. --}}
        {!! ComponentRenderer::render(new Breadcrumbs($breadcrumbs ?? [])) !!}

        @if($title)
            <h1 class="text-lg font-semibold tracking-tight text-gray-900 sm:text-xl lg:text-2xl dark:text-white">
                {{ $title }}
            </h1>
        @endif
    </div>
@endif
