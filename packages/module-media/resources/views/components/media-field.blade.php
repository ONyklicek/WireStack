@php
    use NyonCode\WireModuleMedia\Forms\MediaField;
    assert($field instanceof MediaField);
    // Read the way every other field reads its value: from the Livewire
    // component's state by path. A field object holds configuration, not the
    // answer somebody is in the middle of giving.
    $selected = $field->getSelectedMedia(data_get($this, $field->getStatePath()));
@endphp

@include('wire-forms::partials.field-wrapper-start')

{{-- The field holds ids and shows what they are. Opening the library is a DOM
     event rather than a component call: the modal is rendered once by the shell
     and this field may be one of several on the page, so the token below is what
     keeps two of them from answering each other's question. --}}
<div
    x-data="{
        token: 'field-{{ $field->getName() }}-' + Math.random().toString(36).slice(2),

        open() {
            const request = new CustomEvent('wire-media-picker:open', {
                cancelable: true,
                detail: {
                    token: this.token,
                    multiple: @js($field->isMultiple()),
                    accepts: @js($field->getAccepts()),
                },
            });

            window.dispatchEvent(request);

            // Nobody claimed it: the media module is not installed, and there is
            // nothing this field can do about that except say so rather than
            // leave a button that quietly does nothing.
            if (! request.defaultPrevented) {
                window.alert(@js(__('wire-module-media::messages.no_picker')));
            }
        },

        picked(event) {
            if (event.detail?.token !== this.token) return;

            const ids = (event.detail.files ?? []).map(file => file.id);

            @if ($field->isMultiple())
                $wire.set(@js($field->getWireModelAttribute()), [
                    ...($wire.get(@js($field->getWireModelAttribute())) ?? []),
                    ...ids,
                ].filter((id, index, all) => all.indexOf(id) === index));
            @else
                $wire.set(@js($field->getWireModelAttribute()), ids[0] ?? null);
            @endif
        },
    }"
    x-on:wire-media-picker:picked.window="picked($event)"
    data-testid="media-field" @wireEl('media-field')
    data-field="{{ $field->getName() }}"
>
    @if ($selected)
        <ul class="mb-2 flex flex-wrap gap-2">
            @foreach ($selected as $media)
                <li class="group relative" data-testid="media-field-item" @wireEl('media-field-item') data-media="{{ $media->id }}">
                    <div class="h-20 w-20 overflow-hidden rounded-lg border border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-800">
                        <x-wire::file-thumb
                            :name="$media->name"
                            :mime="$media->mime_type"
                            :url="$media->previewUrl('row')"
                            :srcset="$media->srcset('row')"
                            :placeholder="$media->placeholder"
                            :alt="$media->altText()"
                            size="md"
                        />
                    </div>

                    <button
                        type="button"
                        data-testid="media-field-remove" @wireEl('media-field-remove')
                        x-on:click="
                            @if ($field->isMultiple())
                                $wire.set(@js($field->getWireModelAttribute()), ($wire.get(@js($field->getWireModelAttribute())) ?? []).filter(id => parseInt(id) !== {{ $media->id }}))
                            @else
                                $wire.set(@js($field->getWireModelAttribute()), null)
                            @endif
                        "
                        class="absolute -end-1.5 -top-1.5 hidden rounded-full bg-white p-0.5 text-gray-400 shadow ring-1 ring-gray-200 group-hover:block hover:text-red-600 dark:bg-gray-900 dark:ring-gray-700"
                        title="{{ __('wire-module-media::messages.remove') }}"
                    >{!! icon('outline:x-mark', 'h-3.5 w-3.5') !!}</button>
                </li>
            @endforeach
        </ul>
    @endif

    @unless ($field->isDisabled())
        <button
            type="button"
            x-on:click="open()"
            data-testid="media-field-open" @wireEl('media-field-open')
            class="inline-flex items-center gap-2 rounded-lg border border-gray-300 border-dashed px-3 py-2 text-sm text-gray-600 transition hover:border-gray-400 hover:text-gray-800 dark:border-gray-600 dark:text-gray-300"
        >
            {!! icon('outline:photo', 'h-4 w-4') !!}
            {{ $selected && ! $field->isMultiple()
                ? __('wire-module-media::messages.replace')
                : __('wire-module-media::messages.choose') }}
        </button>
    @endunless
</div>

@include('wire-forms::partials.field-wrapper-end')
