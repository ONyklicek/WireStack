@php
    $statePath = $field->getStatePath();
    $hasError = $errors->has($statePath);
    $columnSpan = $field->getColumnSpan();
@endphp

<div
    wire:key="field-{{ $statePath }}"
    @class([
        'wire-field relative',
        'sm:col-span-1' => $columnSpan === 1,
        'sm:col-span-2 md:col-span-2' => $columnSpan === 2 || $columnSpan === 'full',
    ])
>

    @if($field->getLabel() && !($hideLabel ?? false))
        <label for="{{ $field->getId() }}" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
            {{ $field->getLabel() }}
            @if($field->isRequired())
                <span class="text-red-500">*</span>
            @endif
        </label>
    @endif

    @php
        $hintAction = method_exists($field, 'getHintAction') ? $field->getHintAction() : null;
    @endphp
    @if((method_exists($field, 'getHint') && $field->getHint()) || $hintAction)
        <div class="mb-1 flex items-center justify-between gap-2">
            <p class="text-xs text-gray-500 dark:text-gray-400">
                {{ method_exists($field, 'getHint') ? $field->getHint() : '' }}
            </p>
            @if($hintAction)
                <button
                    type="button"
                    wire:click="callFieldAction('{{ $field->getStatePath() }}', '{{ $hintAction->getName() }}')"
                    wire:loading.attr="disabled"
                    wire:target="callFieldAction"
                    @if($hintAction->getTooltip()) title="{{ $hintAction->getTooltip() }}" @endif
                    class="inline-flex items-center gap-1 text-xs font-medium text-primary-600 hover:text-primary-700 dark:text-primary-400 dark:hover:text-primary-300"
                >
                    @if($hintAction->getIcon())
                        <x-wire::icon :name="$hintAction->getIcon()" class="w-3.5 h-3.5" />
                    @endif
                    @unless($hintAction->isHideLabel())
                        <span>{{ $hintAction->getLabel() }}</span>
                    @endunless
                </button>
            @endif
        </div>
    @endif
