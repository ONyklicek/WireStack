<?php

declare(strict_types=1);

namespace NyonCode\WireModuleMedia\Livewire;

use Livewire\Attributes\On;

/**
 * The library, opened to choose a file rather than to keep them.
 *
 * A subclass rather than a flag passed at mount, for one reason: the modal and
 * the page must be two Livewire components. They share a document whenever the
 * library screen is open, and a component addressed by one name in two places is
 * a component whose events reach both — the configure event below would turn the
 * page itself into a picker.
 *
 * Everything else is inherited. The tree, the search, the folders, the uploads
 * and the empty state are the library's, because a picker that reimplements them
 * is a picker that slowly stops matching it.
 */
class MediaPicker extends MediaManager
{
    public bool $picking = true;

    /**
     * Take the terms of the choice from whoever opened the modal.
     *
     * The caller says these in the DOM event that opens the picker, and they
     * cannot be mount arguments: the modal is rendered once, on every page, long
     * before anybody knows what will be asked of it.
     */
    #[On('wire-media-picker-configure')]
    public function configurePicker(bool $multiple = false, string $accepts = ''): void
    {
        $this->multiple = $multiple;
        $this->accepts = $accepts;
        $this->selected = [];
        $this->detailId = null;
        $this->resetPage();
    }
}
