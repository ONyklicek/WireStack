{{-- TextInputColumn editable cell --}}
{{-- Variables: $column, $record, $state --}}
@php
    $recordKey = $record->getKey();
    $columnName = $column->getName();
    $value = (string) ($state ?? '');
    $valueJson = json_encode($value);

    $hasPrefix = $column->getInputPrefix() !== null;
    $hasSuffix = $column->getInputSuffix() !== null;

    $inputClasses = $column->buildInputClasses($hasPrefix, $hasSuffix);
    $attrs = $column->buildInputAttributes();

    $saveOnBlur = $column->getSaveOnBlur() ? 'true' : 'false';
    $saveOnEnter = $column->getSaveOnEnter() ? 'true' : 'false';
    $liveValidation = $column->getLiveValidation() ? 'true' : 'false';
    $debounce = $column->getLiveDebounce();

    $wireKey = "tic-{$recordKey}-{$columnName}";
    $recordVersion = $record->updated_at ? (string) $record->updated_at->getTimestamp() : '0';

    $msgError = __('wire-table::messages.error');
    $msgSaveFailed = __('wire-table::messages.save_failed');
    $msgInvalid = __('wire-table::messages.invalid');
@endphp

<div wire:key="{{ $wireKey }}"
     x-data="{
        value: {{ $valueJson }},
        original: {{ $valueJson }},
        serverValue: {{ $valueJson }},
        recordVersion: '{{ $recordVersion }}',
        error: null,
        saving: false,
        success: false,
        focused: false,
        get dirty() { return this.value !== this.original },
        init() {
            if ({{ $liveValidation }}) {
                this.$watch('value', Alpine.debounce(() => {
                    if (this.dirty) this.doValidate();
                }, {{ $debounce }}))
            }
        },
        syncFromServer(newVal, newVersion) {
            if (this.saving) return;
            if (this.focused && this.dirty) return;
            this.value = newVal;
            this.original = newVal;
            this.serverValue = newVal;
            if (newVersion) this.recordVersion = newVersion;
            this.error = null;
        },
        onFocus() { this.focused = true; },
        onBlur() {
            this.focused = false;
            if ({{ $saveOnBlur }} && this.dirty) this.save();
        },
        onEnter() { if ({{ $saveOnEnter }} && this.dirty) this.save(); },
        onEscape() { this.value = this.original; this.error = null; this.$refs.input.blur(); },
        async save() {
            if (this.saving || !this.dirty) return;
            this.saving = true;
            this.error = null;
            try {
                const r = await $wire.updateTableCell('{{ $recordKey }}', '{{ $columnName }}', this.value, this.recordVersion);
                if (r?.success === false) {
                    this.error = r.message || r.errors?.[0] || this.$el.dataset.msgError;
                    if (r?.conflict) {
                        this.original = r.currentValue ?? this.original;
                        this.recordVersion = r.currentVersion ?? this.recordVersion;
                    }
                } else {
                    this.original = this.value;
                    this.serverValue = this.value;
                    if (r?.version) this.recordVersion = r.version;
                    this.success = true;
                    setTimeout(() => this.success = false, 1500);
                }
            } catch (e) {
                this.error = this.$el.dataset.msgSaveFailed;
            } finally {
                this.saving = false;
            }
        },
        async doValidate() {
            try {
                const r = await $wire.validateTableCell('{{ $recordKey }}', '{{ $columnName }}', this.value);
                this.error = (r && !r.valid) ? (r.errors?.[0] || this.$el.dataset.msgInvalid) : null;
            } catch (e) {}
        }
     }"
     x-effect="let sv = $el.dataset.serverValue, rv = $el.dataset.recordVersion; if (sv !== undefined && sv !== serverValue) syncFromServer(sv, rv)"
     data-server-value="{{ $value }}"
     data-record-version="{{ $recordVersion }}"
     data-msg-error="{{ $msgError }}"
     data-msg-save-failed="{{ $msgSaveFailed }}"
     data-msg-invalid="{{ $msgInvalid }}"
     class="relative"
>
    {{-- Prefix --}}
    @if($hasPrefix)
        <span class="absolute inset-y-0 left-0 flex items-center pl-2 text-gray-500 dark:text-gray-400 text-sm pointer-events-none">
            {{ $column->getInputPrefix() }}
        </span>
    @endif

    {{-- Input wrapper --}}
    <div class="relative">
        <input {!! $attrs !!}
               x-model="value"
               @focus="onFocus()"
               @blur="onBlur()"
               @keydown.enter.prevent="onEnter()"
               @keydown.escape="onEscape()"
               :disabled="saving"
               :class="{'border-red-500 focus:border-red-500 focus:ring-red-500': error, 'border-green-500 focus:border-green-500 focus:ring-green-500': success}"
               x-ref="input"
               class="{{ $inputClasses }}"
        >

        {{-- Saving spinner --}}
        <span x-show="saving" x-cloak class="absolute inset-y-0 right-0 flex items-center pr-2 pointer-events-none">
            @include('wire-table::tables.columns.partials.spinner')
        </span>

        {{-- Success check --}}
        <span x-show="success" x-cloak x-transition class="absolute inset-y-0 right-0 flex items-center pr-2 pointer-events-none">
            @include('wire-table::tables.columns.partials.check-icon')
        </span>

        {{-- Suffix --}}
        @if($hasSuffix)
            <span x-show="!saving && !success" class="absolute inset-y-0 right-0 flex items-center pr-2 text-gray-500 dark:text-gray-400 text-sm pointer-events-none">
                {{ $column->getInputSuffix() }}
            </span>
        @endif
    </div>

    {{-- Error message --}}
    <p x-show="error" x-cloak x-text="error" class="mt-1 text-xs text-red-600 dark:text-red-400"></p>

    {{-- Helper text --}}
    @if($column->getHelperText())
        <p x-show="!error" class="mt-1 text-xs text-gray-500 dark:text-gray-400">
            {{ $column->getHelperText() }}
        </p>
    @endif
</div>
