@php
    use NyonCode\WireForms\Components\FileUpload;
    assert($field instanceof FileUpload);

    $wireModifier = $field->getWireModelModifier();
    $wireAttr = 'wire:model' . ($wireModifier ? ".{$wireModifier}" : '');
    $acceptedTypes = $field->getAcceptedFileTypes();
    $isMultiple = $field->isMultiple();
    $fieldId = $field->getId();
    $maxSize = $field->getMaxSize();
    $filePreviews = $field->getFilePreviews(data_get($this, $field->getStatePath()));
    $canDelete = $field->isDeletable() && ! $field->isDisabled();
@endphp

@include('wire-forms::partials.field-wrapper-start')

<div
        x-data="{
        isDragging: false,
        handleDrop(e) {
            this.isDragging = false;
            const dropped = e.dataTransfer?.files;
            if (! dropped || ! dropped.length) return;
            // Feed the dropped files into the wire:model input so Livewire uploads them.
            const dt = new DataTransfer();
            Array.from(dropped).forEach(f => dt.items.add(f));
            this.$refs.fileInput.files = dt.files;
            this.$refs.fileInput.dispatchEvent(new Event('change', { bubbles: true }));
        },
        openPicker() {
            this.$refs.fileInput.click();
        }
    }"
        class="space-y-2"
>
    {{-- Drop zone --}}
    <div
            @click="openPicker()" data-testid="form-file-{{ $field->getStatePath() }}-dropzone"
            @dragover.prevent="isDragging = true"
            @dragleave.prevent="isDragging = false"
            @drop.prevent="handleDrop($event)"
            :class="{
            'border-primary-500 bg-primary-50 dark:bg-primary-900/10': isDragging,
            'border-gray-300 dark:border-gray-600': !isDragging,
        }"
            @class([
                'relative flex flex-col items-center justify-center px-6 py-8 border-2 border-dashed rounded-lg cursor-pointer',
                'hover:border-gray-400 dark:hover:border-gray-500 transition-colors duration-150',
                'bg-white dark:bg-gray-800',
                'border-red-500' => $errors->has($field->getStatePath()),
            ])
    >
        <div class="pointer-events-none text-center" wire:loading.remove
             wire:target="{{ $field->getWireModelAttribute() }}">
            <x-wire::icon name="outline:arrow-up-tray" class="mx-auto h-10 w-10 text-gray-400"
                          ::class="{ 'text-primary-500': isDragging }"/>
            <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                <span class="font-medium text-primary-600 dark:text-primary-400">{{ __('Click to upload') }}</span>
                {{ __('or drag and drop') }}
            </p>
            @if(!empty($acceptedTypes) || $maxSize)
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    @if(!empty($acceptedTypes))
                        {{ implode(', ', array_map(fn($t) => ltrim($t, '.'), $acceptedTypes)) }}
                    @endif
                    @if($maxSize)
                        {{ !empty($acceptedTypes) ? ' — ' : '' }}{{ __('max') }} {{ $maxSize >= 1024 ? round($maxSize / 1024, 1) . ' MB' : $maxSize . ' KB' }}
                    @endif
                </p>
            @endif
        </div>

        {{-- Loading indicator --}}
        <div class="pointer-events-none text-center" wire:loading wire:target="{{ $field->getWireModelAttribute() }}">
            @include('wire-core::partials.spinner', ['class' => 'mx-auto h-8 w-8 text-primary-500'])
            <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">{{ __('Uploading...') }}</p>
        </div>

        <input
                type="file"
                x-ref="fileInput"
                id="{{ $fieldId }}"
        {{ $wireAttr }}="{{ $field->getWireModelAttribute() }}"
        @if(!empty($acceptedTypes))
            accept="{{ implode(',', $acceptedTypes) }}"
        @endif
        @if($isMultiple)
            multiple
        @endif
        @if($field->isDisabled())
            disabled
        @endif
        @if($field->isRequired())
            required
        @endif
        class="sr-only"
        />
    </div>

    {{-- Files in the field state: stored paths + pending uploads (store-on-submit) --}}
    @if (! empty($filePreviews))
        <ul class="space-y-2">
            @foreach ($filePreviews as $preview)
                <li class="flex items-center gap-3 rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-800 p-3">
                    @if ($preview['url'] && $preview['isImage'])
                        <img src="{{ $preview['url'] }}" class="h-10 w-10 rounded object-cover shrink-0" alt="{{ $preview['name'] }}"/>
                    @else
                        <div class="flex h-10 w-10 items-center justify-center rounded bg-gray-100 dark:bg-gray-700 shrink-0">
                            <x-wire::icon name="outline:document" class="h-5 w-5 text-gray-400"/>
                        </div>
                    @endif

                    <div class="flex-1 min-w-0">
                        @if ($preview['stored'] && $preview['url'])
                            <a href="{{ $preview['url'] }}" target="_blank" rel="noopener"
                               class="text-sm font-medium text-gray-700 dark:text-gray-300 hover:text-primary-600 dark:hover:text-primary-400 truncate block">
                                {{ $preview['name'] }}
                            </a>
                        @else
                            <p class="text-sm font-medium text-gray-700 dark:text-gray-300 truncate">{{ $preview['name'] }}</p>
                        @endif
                        @unless ($preview['stored'])
                            <p class="text-xs text-gray-400 dark:text-gray-500">{{ __('Pending upload') }}</p>
                        @endunless
                    </div>

                    @if ($canDelete)
                        <button
                                type="button"
                                wire:click="removeUploadedFile('{{ $field->getStatePath() }}', {{ (int) $preview['index'] }})" data-testid="form-file-{{ $field->getStatePath() }}-remove-{{ (int) $preview['index'] }}"
                                class="shrink-0 p-1 text-gray-400 hover:text-red-500 transition-colors duration-150"
                        >
                            <x-wire::icon name="x-mark" class="h-4 w-4"/>
                            <span class="sr-only">{{ __('Remove') }}</span>
                        </button>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif

</div>

@include('wire-forms::partials.field-wrapper-end')
