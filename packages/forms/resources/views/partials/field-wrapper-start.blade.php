@php
    $statePath = $field->getStatePath();
    $hasError = $errors->has($statePath);
    $columnSpan = $field->getColumnSpan();
    // extraAttributes() is declared by every field through HasExtraAttributes but
    // was rendered by nothing, so the setter did not do what it says. The outer
    // element is this wrapper, so it belongs here — once, for every field type.
    $extraAttributes = method_exists($field, 'getExtraAttributes') ? $field->getExtraAttributes() : [];
@endphp

<div
    wire:key="field-{{ $statePath }}"
    data-testid="form-field-{{ $statePath }}"
    data-field="{{ $statePath }}"
    @class([
        'wire-field relative',
        'sm:col-span-1' => $columnSpan === 1,
        'sm:col-span-2 md:col-span-2' => $columnSpan === 2 || $columnSpan === 'full',
    ])
    @foreach($extraAttributes as $attribute => $value)
        {{ $attribute }}="{{ $value }}"
    @endforeach
>

    @if($field->getLabel() && !($hideLabel ?? false) && !$field->isLabelHidden())
        <label for="{{ $field->getId() }}" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
            {{ $field->getLabel() }}
            @if($field->isRequired())
                <span class="text-red-500">*</span>
            @endif
        </label>
    @endif

    @php
        use NyonCode\WireCore\Foundation\Concerns\HasColor;

        $hintAction = method_exists($field, 'getHintAction') ? $field->getHintAction() : null;
        $hintText = method_exists($field, 'getHint') ? $field->getHint() : null;
        // hintIcon()/hintColor() are part of the hint vocabulary; the wrapper used
        // to render only the text, so both setters were silent no-ops.
        $hintIcon = method_exists($field, 'getHintIcon') ? $field->getHintIcon() : null;
        $hintColor = method_exists($field, 'getHintColor') ? $field->getHintColor() : null;
        $hintClass = $hintColor !== null
            ? HasColor::getTextColorClasses($hintColor)
            : 'text-gray-500 dark:text-gray-400';
    @endphp
    @if($hintText || $hintAction)
        <div class="mb-1 flex items-center justify-between gap-2">
            <p class="inline-flex items-center gap-1 text-xs {{ $hintClass }}">
                @if($hintIcon)
                    {!! icon($hintIcon, 'w-3.5 h-3.5', 'shrink-0') !!}
                @endif
                {{ $hintText }}
            </p>
            @if($hintAction)
                <button
                    type="button"
                    wire:click="callFieldAction('{{ $field->getStatePath() }}', '{{ $hintAction->getName() }}')"
                    wire:loading.attr="disabled"
                    wire:target="callFieldAction"
                    data-testid="field-action-{{ $statePath }}-{{ $hintAction->getName() }}"
                    @if($hintAction->getLabel()) aria-label="{{ $hintAction->getLabel() }}" @endif
                    @if($hintAction->getTooltip()) title="{{ $hintAction->getTooltip() }}" @endif
                    class="inline-flex items-center gap-1 text-xs font-medium text-primary-600 hover:text-primary-700 dark:text-primary-400 dark:hover:text-primary-300"
                >
                    @if($hintAction->getIcon())
                        {!! icon($hintAction->getIcon(), 'w-4 h-4', 'w-3.5 h-3.5') !!}
                    @endif
                    @unless($hintAction->isHideLabel())
                        <span>{{ $hintAction->getLabel() }}</span>
                    @endunless
                </button>
            @endif
        </div>
    @endif
