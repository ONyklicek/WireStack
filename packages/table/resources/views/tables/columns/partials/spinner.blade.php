{{-- Loading spinner. Delegates to the canonical core owner; $class overrides size/color. --}}
@include('wire-core::partials.spinner', ['class' => $class ?? 'h-4 w-4 text-primary-500'])
