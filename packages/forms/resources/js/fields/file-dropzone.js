/**
 * A zone you can drop files on: FileUpload's, and the media library's.
 *
 * A file dropped on the zone is fed into the `wire:model` input rather than
 * uploaded directly — Livewire owns the upload, and a `DataTransfer` is the only
 * way to put a dropped file into an `<input type="file">` that it watches.
 *
 * The processing field uses `wireImageUpload` instead (image-processor.js),
 * which offers the same three names to the same markup: a view branching on the
 * config must not branch on the vocabulary too.
 *
 * ## Local previews
 *
 * With `{ previews: true }` the zone also draws what is being uploaded, from the
 * browser's own copy of it. The bytes are already in hand, so a dropped
 * photograph can be on screen before the round trip starts — which matters most
 * where the stored file takes a while to come back, as it does when a thumbnail
 * is made on a queue.
 *
 * It is a flag rather than a second controller because it is the same zone: the
 * media library grew previews first and did it inline, which was a second copy
 * of the drop handling above and had already begun to differ from it. What a
 * surface asks for here is *whether to draw*, never *how to drop*.
 *
 * **Object URLs are released when the upload settles.** Each one pins the whole
 * file in memory until it is revoked, and a library is exactly where somebody
 * drops forty photographs at once — so `finish`, `error` and `cancel` all clear
 * them, because a cancelled upload is still an upload that ended.
 */
const wireFileDropzone = (config = {}) => ({
    isDragging: false,

    /** @type {Array<{name: string, url: string|null}>} */
    pending: [],

    /**
     * The files of the last batch, kept so one of them can be sent again.
     *
     * Opt-in through `retain`, because holding `File` objects holds the files:
     * a field that uploads once has no use for them, and a library where
     * somebody drops forty photographs would rather not keep forty of them
     * alive for nothing. Where a retry exists, this is the only way to have
     * one — the server cannot re-send a file it never received.
     *
     * @type {Array<File>}
     */
    retained: [],

    progress: 0,

    init() {
        if (! config.previews) return

        // Livewire's own upload events, listened for on the element rather than
        // through a hook: the input is inside this component and the events
        // bubble from it, so a page with two zones on it keeps them apart.
        this.$el.addEventListener('livewire-upload-progress', (event) => {
            this.progress = event.detail?.progress ?? 0
        })

        ;['livewire-upload-finish', 'livewire-upload-error', 'livewire-upload-cancel'].forEach((name) => {
            this.$el.addEventListener(name, () => this.release())
        })
    },

    handleDrop(event) {
        this.isDragging = false

        const dropped = event.dataTransfer?.files

        if (! dropped || ! dropped.length) return

        this.take(dropped)
    },

    /** Hand a FileList to the input Livewire is watching, previewing it first. */
    take(files) {
        const transfer = new DataTransfer()

        Array.from(files).forEach((file) => transfer.items.add(file))

        this.preview(transfer.files)

        this.$refs.fileInput.files = transfer.files
        this.$refs.fileInput.dispatchEvent(new Event('change', { bubbles: true }))
    },

    /** Draw what is about to be uploaded. A no-op where previews were not asked for. */
    preview(files) {
        if (! config.previews) return

        this.release()

        if (config.retain) this.retained = Array.from(files)

        this.pending = Array.from(files).map((file) => ({
            name: file.name,
            // Images only: there is nothing to show for a PDF that its icon does
            // not already say.
            url: file.type.startsWith('image/') ? URL.createObjectURL(file) : null,
        }))

        this.progress = 0
    },

    /**
     * Send one file of the last batch again.
     *
     * Named rather than indexed: what the caller has is a row that says which
     * file it was, and an index into a list that has since been redrawn is an
     * index into the wrong file.
     */
    retry(name) {
        const file = this.retained.find((candidate) => candidate.name === name)

        if (file) this.take([file])

        return !! file
    },

    release() {
        this.pending.forEach((item) => item.url && URL.revokeObjectURL(item.url))
        this.pending = []
        this.progress = 0
    },

    openPicker() {
        this.$refs.fileInput.click()
    },
})

export default wireFileDropzone
