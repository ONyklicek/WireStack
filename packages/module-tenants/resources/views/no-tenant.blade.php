{{-- A tenant zone's own address for someone in no company. The place to
     register one, when the application allows it; a plain statement when not. --}}
{{-- Inside the layout the application gave its Livewire pages, so this screen
     looks like the rest of it rather than like a bare error page. --}}
@component((string) config('livewire.component_layout', 'components.layouts.app'), ['title' => __('wire-module-tenants::messages.no_company')])
<div class="mx-auto max-w-xl space-y-4 py-16 text-center" data-testid="tenants-none" @wireEl('tenants-none')>
    <h1 class="text-xl font-semibold text-gray-900 dark:text-white">{{ __('wire-module-tenants::messages.no_company') }}</h1>

    @if (\NyonCode\WireModuleTenants\Support\Registration::allows(auth()->user()))
        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('wire-module-tenants::messages.no_company_register') }}</p>
        <a href="{{ route('wire-module-tenants.register') }}" data-testid="tenants-none-register"
           class="inline-flex rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700">
            {{ __('wire-module-tenants::messages.register_heading') }}
        </a>
    @else
        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('wire-module-tenants::messages.no_company_ask') }}</p>
    @endif
</div>
@endcomponent
