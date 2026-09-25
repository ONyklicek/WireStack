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
    /** @var array<int, \Illuminate\Support\HtmlString> $headerActions  Drawn by the page, already authorized. */
@endphp

@if(($breadcrumbs ?? []) !== [] || $title || ($headerActions ?? []) !== [])
    <div class="space-y-1">
        {{-- The object, not <x-wire::breadcrumbs>: rule 5 keeps the component
             tags for consumers and has the framework draw without them. --}}
        {!! ComponentRenderer::render(new Breadcrumbs($breadcrumbs ?? [])) !!}

        {{-- The title and the page's actions share a row from `sm` up, the
             actions pushed to the end; on a phone they wrap under the title
             rather than squeezing it to a column of broken words. --}}
        <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2">
            @if($title)
                <h1 class="text-lg font-semibold tracking-tight text-gray-900 sm:text-xl lg:text-2xl dark:text-white">
                    {{ $title }}
                </h1>
            @endif

            @if(($headerActions ?? []) !== [])
                <div data-testid="page-header-actions" @wireEl('page-header-actions') class="flex flex-wrap items-center gap-2 sm:ms-auto">
                    @foreach($headerActions as $headerAction)
                        {{ $headerAction }}
                    @endforeach
                </div>
            @endif
        </div>

        @wireRenderHook('panels.page.header.end', ['title' => $title, 'breadcrumbs' => $breadcrumbs ?? []])
    </div>
@endif

{{-- Outside the block above, deliberately: the heading renders only when there
     is a title, a trail or an action, and a record's tabs do not depend on any
     of them. The partial is inert on the pages that pass none — the list, the
     create screen, the dashboard — so every page can include it without asking. --}}
@include('wire-panels::pages.partials.sub-nav')
