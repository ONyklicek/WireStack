<?php

declare(strict_types=1);

namespace NyonCode\WireForms\Concerns;

use Illuminate\Http\UploadedFile;
use Livewire\Component;
use Livewire\WithFileUploads;
use NyonCode\WireCore\Core\State\StateContainer;
use NyonCode\WireForms\Components\FileUpload;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WireForms\Forms\WithForms;

/**
 * File-upload plumbing shared by every host that embeds a form — standalone
 * form components ({@see WithForms}) and table/form action modals.
 *
 * Composes Livewire's {@see WithFileUploads} so `<input wire:model>` file inputs
 * actually upload to temporary storage, then — via the `updated` lifecycle hook —
 * merges each finished upload into the field state (store-on-submit): the file
 * stays a pending `TemporaryUploadedFile` until the form is saved, at which
 * point the SaveHandler moves it to permanent storage. This keeps abandoned
 * forms orphan-free (temp uploads expire on their own).
 *
 *  - **single** fields keep the value Livewire just set;
 *  - **multiple** fields append new uploads to the already-present entries
 *    (existing paths + earlier pending uploads), captured in `updating`.
 *
 * The "remove file" button is backed by {@see removeUploadedFile()}.
 * Writes go through {@see StateContainer::writeInto()} so they work through the
 * ArrayAccess bag used by table action modals too.
 *
 * @phpstan-require-extends Component
 */
trait InteractsWithFileUploads
{
    use WithFileUploads;

    /**
     * Value at each state path captured before an update, so a multiple-file
     * field can merge new uploads with its already-stored paths.
     *
     * @var array<string, mixed>
     */
    protected array $fileUploadStateBeforeUpdate = [];

    public function updatingInteractsWithFileUploads(string $name, mixed $value): void
    {
        if ($this->containsUploadedFile($value)) {
            $this->fileUploadStateBeforeUpdate[$name] = data_get($this, $name);
        }
    }

    public function updatedInteractsWithFileUploads(string $name, mixed $value): void
    {
        // Only act on genuine file uploads — every other property update flows
        // through here too and must stay untouched.
        if (! $this->containsUploadedFile($value)) {
            return;
        }

        $old = $this->fileUploadStateBeforeUpdate[$name] ?? null;
        unset($this->fileUploadStateBeforeUpdate[$name]);

        $this->processFileUploadState($name, $old);
    }

    /**
     * Merge a freshly-uploaded file into the field state without moving it to
     * permanent storage — that happens on submit ({@see SaveHandler}). A
     * **multiple** field appends the new uploads to whatever was already there
     * (existing paths + earlier pending uploads, captured in `updating`); a
     * **single** field keeps the value Livewire just set. Returns true when a
     * multiple field was merged.
     */
    protected function processFileUploadState(string $statePath, mixed $old): bool
    {
        $field = $this->resolveFileUploadField($statePath);

        if (! $field instanceof FileUpload || ! $field->isMultiple()) {
            return false;
        }

        $existing = is_array($old) ? $old : ($old === null || $old === '' ? [] : [$old]);

        $value = data_get($this, $statePath);
        $incoming = is_array($value) ? $value : [$value];
        $incoming = array_filter(
            $incoming,
            static fn ($item): bool => $item instanceof UploadedFile || (is_string($item) && $item !== ''),
        );

        StateContainer::writeInto($this, $statePath, array_values(array_merge($existing, $incoming)));

        return true;
    }

    /**
     * Remove a file (a stored path or a still-pending upload) from a FileUpload
     * field's state by its index in the rendered list.
     *
     * Only removes the reference from the form state — a stored physical file is
     * left untouched (cleanup is the application's concern); a pending upload is
     * simply dropped and its temporary file expires on its own.
     */
    public function removeUploadedFile(string $statePath, int $index): void
    {
        $state = data_get($this, $statePath);

        if (is_array($state)) {
            unset($state[$index]);

            StateContainer::writeInto($this, $statePath, array_values($state));

            return;
        }

        // Single-file field holding a bare value — any index clears it.
        StateContainer::writeInto($this, $statePath, null);
    }

    private function containsUploadedFile(mixed $value): bool
    {
        if ($value instanceof UploadedFile) {
            return true;
        }

        if (is_array($value)) {
            foreach ($value as $item) {
                if ($item instanceof UploadedFile) {
                    return true;
                }
            }
        }

        return false;
    }

    private function resolveFileUploadField(string $statePath): ?FileUpload
    {
        foreach ($this->fileUploadForms() as $form) {
            $component = $form->findComponentByStatePath($statePath);

            if ($component instanceof FileUpload) {
                return $component;
            }
        }

        return null;
    }

    /**
     * Resolve the Form instances this host renders, across both the standalone
     * form hosts (getForms/resolveForm) and action-modal hosts.
     *
     * @return list<Form>
     */
    private function fileUploadForms(): array
    {
        $forms = [];

        if (method_exists($this, 'getForms') && method_exists($this, 'resolveForm')) {
            foreach ($this->getForms() as $name) {
                $form = $this->resolveForm($name);

                if ($form instanceof Form) {
                    $forms[] = $form;
                }
            }
        }

        foreach (['getActionModalFormInstance', 'getHaltModalFormInstance'] as $method) {
            if (method_exists($this, $method)) {
                $form = $this->{$method}();

                if ($form instanceof Form) {
                    $forms[] = $form;
                }
            }
        }

        return $forms;
    }
}
