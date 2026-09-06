/**
 * FileUpload's plain dropzone: the half that has no image processing to do.
 *
 * A file dropped on the zone is fed into the `wire:model` input rather than
 * uploaded directly — Livewire owns the upload, and a `DataTransfer` is the only
 * way to put a dropped file into an `<input type="file">` that it watches.
 *
 * The processing field uses `wireImageUpload` instead (image-processor.js),
 * which offers the same three names to the same markup: a view branching on the
 * config must not branch on the vocabulary too.
 */
const wireFileDropzone = () => ({
    isDragging: false,

    handleDrop(event) {
        this.isDragging = false

        const dropped = event.dataTransfer?.files

        if (! dropped || ! dropped.length) return

        const transfer = new DataTransfer()

        Array.from(dropped).forEach((file) => transfer.items.add(file))

        this.$refs.fileInput.files = transfer.files
        this.$refs.fileInput.dispatchEvent(new Event('change', { bubbles: true }))
    },

    openPicker() {
        this.$refs.fileInput.click()
    },
})

export default wireFileDropzone
