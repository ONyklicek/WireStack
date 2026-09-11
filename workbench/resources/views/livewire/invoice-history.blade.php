{{-- A record page the framework does not ship, drawn with the parts it does:
     the same heading partial, and therefore the same breadcrumb trail and the
     same tab bar as the view and edit pages beside it. --}}
<div class="wire-resource-page space-y-4 sm:space-y-6">
    @include('wire-panels::pages.partials.header')

    <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
        <ol class="space-y-3 text-sm" data-testid="invoice-history">
            @foreach ([
                ['Issued', $invoice?->issued_at],
                ['Due', $invoice?->due_at],
                ['Status', $invoice?->status],
            ] as [$label, $value])
                <li class="flex items-baseline gap-3">
                    <span class="w-24 shrink-0 text-gray-500 dark:text-gray-400">{{ $label }}</span>
                    <span class="font-medium text-gray-900 dark:text-gray-100">{{ $value ?: '—' }}</span>
                </li>
            @endforeach
        </ol>
    </div>
</div>
