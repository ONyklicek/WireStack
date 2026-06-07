@php
    use NyonCode\WireForms\Components\FileUpload;
    assert($field instanceof FileUpload);

    $wireModifier = $field->getWireModelModifier();
    $wireAttr = 'wire:model' . ($wireModifier ? ".{$wireModifier}" : '');
    $acceptedTypes = $field->getAcceptedFileTypes();
    $isImage = $field->isImage() || $field->isAvatar();
    $isMultiple = $field->isMultiple();
    $fieldId = $field->getId();
    $maxSize = $field->getMaxSize();
@endphp

@include('wire-forms::partials.field-wrapper-start')

<div
        x-data="{
        isDragging: false,
        files: [],
        previews: [],
        isImage: @js($isImage),
        isMultiple: @js($isMultiple),
        handleDrop(e) {
            this.isDragging = false;
            const dt = e.dataTransfer;
            if (dt.files.length) {
                this.addFiles(dt.files);
            }
        },
        addFiles(fileList) {
            const newFiles = Array.from(fileList);
            if (this.isMultiple) {
                this.files = [...this.files, ...newFiles];
            } else {
                this.files = newFiles.slice(0, 1);
            }
            this.generatePreviews();
            this.syncToInput();
        },
        removeFile(index) {
            this.files.splice(index, 1);
            this.previews.splice(index, 1);
            this.syncToInput();
        },
        generatePreviews() {
            this.previews = [];
            this.files.forEach((file, i) => {
                if (this.isImage && file.type.startsWith('image/')) {
                    const reader = new FileReader();
                    reader.onload = (e) => { this.previews[i] = e.target.result; };
                    reader.readAsDataURL(file);
                } else {
                    this.previews[i] = null;
                }
            });
        },
        syncToInput() {
            const dt = new DataTransfer();
            this.files.forEach(f => dt.items.add(f));
            this.$refs.fileInput.files = dt.files;
            this.$refs.fileInput.dispatchEvent(new Event('change', { bubbles: true }));
        },
        formatSize(bytes) {
            if (bytes < 1024) return bytes + ' B';
            if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
            return (bytes / 1048576).toFixed(1) + ' MB';
        },
        openPicker() {
            this.$refs.fileInput.click();
        },
        onInputChange(e) {
            this.addFiles(e.target.files);
        }
    }"
        class="space-y-2"
>
    {{-- Drop zone --}}
    <div
            @click="openPicker()"
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
            <svg class="mx-auto h-8 w-8 text-primary-500 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none"
                 viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor"
                      d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
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
        @change="onInputChange($event)"
        class="sr-only"
        />
    </div>

    {{-- File preview list --}}
    <template x-if="files.length > 0">
        <ul class="space-y-2">
            <template x-for="(file, index) in files" :key="index">
                <li class="flex items-center gap-3 rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-800 p-3">
                    {{-- Image thumbnail --}}
                    <template x-if="previews[index]">
                        <img :src="previews[index]" class="h-10 w-10 rounded object-cover shrink-0" alt=""/>
                    </template>
                    {{-- File icon --}}
                    <template x-if="!previews[index]">
                        <div class="flex h-10 w-10 items-center justify-center rounded bg-gray-100 dark:bg-gray-700 shrink-0">
                            <x-wire::icon name="outline:document" class="h-5 w-5 text-gray-400"/>
                        </div>
                    </template>

                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-gray-700 dark:text-gray-300 truncate" x-text="file.name"></p>
                        <p class="text-xs text-gray-500 dark:text-gray-400" x-text="formatSize(file.size)"></p>
                    </div>

                    <button
                            type="button"
                            @click="removeFile(index)"
                            class="shrink-0 p-1 text-gray-400 hover:text-red-500 transition-colors duration-150"
                    >
                        <x-wire::icon name="x-mark" class="h-4 w-4"/>
                    </button>
                </li>
            </template>
        </ul>
    </template>
</div>

@include('wire-forms::partials.field-wrapper-end')
