@php
    use NyonCode\WireForms\Components\FileUpload;
    use NyonCode\WireForms\WireFormsServiceProvider;

    assert($field instanceof FileUpload);

    $wireModifier = $field->getWireModelModifier();
    $wireAttr = 'wire:model' . ($wireModifier ? ".{$wireModifier}" : '');
    $acceptedTypes = $field->getAcceptedFileTypes();
    $isMultiple = $field->isMultiple();
    $fieldId = $field->getId();
    $maxSize = $field->getMaxSize();
    $filePreviews = $field->getFilePreviews(data_get($this, $field->getStatePath()));
    $canDelete = $field->isDeletable() && ! $field->isDisabled();

    // Crop/resize runs in the browser so an oversized original never travels.
    // Only then does the field need its own picker input; otherwise it keeps the
    // plain wire:model input and the untouched upload path.
    $processesImages = $field->processesImages();
    // An avatar is shown round — that is what makes it read as an avatar rather
    // than a thumbnail. The 1:1 crop that goes with it is applied on upload.
    $thumbShape = $field->isAvatar() ? 'rounded-full' : 'rounded';
    $imageConfig = $field->getImageProcessingConfig();
    $assetVersion = @filemtime(WireFormsServiceProvider::ASSETS_PATH.'/wire-forms-image.js') ?: null;
    $assetUrl = $processesImages
        ? route('wire-forms.asset', ['asset' => 'image']).($assetVersion ? '?id='.$assetVersion : '')
        : null;
@endphp

@if($processesImages)
    {{-- Pre-bundled, injected via @assets so it also runs for a field rendered
         into a Livewire-loaded modal, where a morphed <script> would not. --}}
    @assets
    <script src="{{ $assetUrl }}"></script>
    @endassets
@endif

@include('wire-forms::partials.field-wrapper-start')

<div
        @if($processesImages)
            {{-- Crop/resize before Livewire uploads: the shared component owns the
                 picker → process → hand to wire:model flow. --}}
            x-data="wireImageUpload(@js($imageConfig))"
        @else
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
        @endif
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

        @if($processesImages)
            {{-- The user picks here; only the processed file reaches Livewire's
                 input below. Two inputs rather than intercepting `change`:
                 Livewire listens on its own input, so swallowing and
                 re-dispatching would come down to listener order. --}}
            <input
                    type="file"
                    x-ref="picker"
                    id="{{ $fieldId }}"
                    @change="onPick($event)"
                    data-testid="form-file-{{ $field->getStatePath() }}-picker"
            @if(!empty($acceptedTypes))
                accept="{{ implode(',', $acceptedTypes) }}"
            @endif
            @if($isMultiple) multiple @endif
            @if($field->isDisabled()) disabled @endif
            @if($field->isRequired()) required @endif
            class="sr-only"
            />

            <input type="file" x-ref="fileInput" {{ $wireAttr }}="{{ $field->getWireModelAttribute() }}"
                   @if($isMultiple) multiple @endif class="sr-only" tabindex="-1" aria-hidden="true"/>
        @else
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
        @endif
    </div>

    {{-- Files in the field state: stored paths + pending uploads (store-on-submit) --}}
    @if (! empty($filePreviews))
        <ul class="space-y-2">
            @foreach ($filePreviews as $preview)
                <li class="flex items-center gap-3 rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-800 p-3">
                    @if ($preview['url'] && $preview['isImage'])
                        <img src="{{ $preview['url'] }}" class="h-10 w-10 {{ $thumbShape }} object-cover shrink-0" alt="{{ $preview['name'] }}"/>
                    @else
                        <div class="flex h-10 w-10 items-center justify-center {{ $thumbShape }} bg-gray-100 dark:bg-gray-700 shrink-0">
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


    @if($processesImages && $field->cropsInteractively())
        {{-- Crop frame. Deliberately not a library: the canvas pipeline already
             crops and downscales, so all that was missing was letting the user place
             the frame — a drag, not a 50 kB dependency. --}}
        <div
                x-show="cropping"
                x-cloak
                @keydown.escape.window="cancelCrop()"
                class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/70 p-4"
                data-testid="form-file-{{ $field->getStatePath() }}-cropper"
        >
            <div class="w-full max-w-lg rounded-lg bg-white p-4 shadow-xl dark:bg-gray-800" @click.stop>
                <p class="mb-3 text-sm font-medium text-gray-900 dark:text-white">{{ __('Choose the visible area') }}</p>

                <div
                        class="relative select-none overflow-hidden rounded"
                        @pointermove="onDrag($event)"
                        @pointerup.window="endDrag()"
                        @pointercancel.window="endDrag()"
                >
                    <img
                            x-ref="cropImage"
                            :src="cropUrl"
                            @load="fitFrame()"
                            alt=""
                            class="block max-h-[60vh] w-full object-contain"
                            draggable="false"
                    />

                    {{-- Everything outside the frame is dimmed by the frame's own
                         huge shadow, so no four-box overlay is needed. --}}
                    <div
                            x-show="frame.width > 0"
                            @pointerdown="startDrag($event)"
                            data-testid="form-file-{{ $field->getStatePath() }}-crop-frame"
                            class="absolute cursor-move border-2 border-white shadow-[0_0_0_9999px_rgba(0,0,0,0.5)] {{ $field->isAvatar() ? 'rounded-full' : '' }}"
                            :style="`left:${frame.left}px; top:${frame.top}px; width:${frame.width}px; height:${frame.height}px`"
                    ></div>
                </div>

                <div class="mt-4 flex justify-end gap-2">
                    <button type="button" @click="cancelCrop()"
                            data-testid="form-file-{{ $field->getStatePath() }}-crop-cancel"
                            class="rounded-md px-3 py-2 text-sm text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700">
                        {{ __('Cancel') }}
                    </button>
                    <button type="button" @click="confirmCrop()"
                            data-testid="form-file-{{ $field->getStatePath() }}-crop-confirm"
                            class="rounded-md bg-primary-600 px-3 py-2 text-sm font-medium text-white hover:bg-primary-700">
                        {{ __('Crop') }}
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>

@include('wire-forms::partials.field-wrapper-end')
