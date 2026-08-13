<?php

declare(strict_types=1);

namespace NyonCode\WireForms\Concerns;

use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
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
 * actually upload to temporary storage. The file stays a pending
 * `TemporaryUploadedFile` until the form is saved (store-on-submit), at which
 * point the SaveHandler moves it to permanent storage — which is what keeps
 * abandoned forms orphan-free, since temp uploads expire on their own.
 *
 * **Appending is Livewire's job, not ours.** Up to Livewire 3 `_finishUpload()`
 * defaulted to `$append = false`, so a multiple-file field lost its existing
 * entries on every upload and this trait merged them back in from a value it had
 * captured in `updating`. Livewire 4 flipped that default to `true` and merges
 * onto the current property value itself, so the hooks that used to do it were
 * removed — keeping them merged the existing entries twice.
 *
 * The "remove file" button is backed by {@see removeUploadedFile()}, and that one
 * still writes through {@see StateContainer::writeInto()}: `data_set()` cannot
 * write through the ArrayAccess bag an action modal keeps its state in.
 *
 * @phpstan-require-extends Component
 */
trait InteractsWithFileUploads
{
    use WithFileUploads;

    /**
     * Remove a file (a stored path or a still-pending upload) from a FileUpload
     * field's state by its index in the rendered list.
     *
     * Removes the reference from the form state. A stored physical file is left
     * on disk by default (cleanup is the application's concern) unless the field
     * opts in via {@see FileUpload::deletesFromDisk()} / {@see FileUpload::deleteUsing()},
     * in which case it is torn down here; a pending upload is simply dropped and
     * its temporary file expires on its own.
     */
    public function removeUploadedFile(string $statePath, int $index): void
    {
        $state = data_get($this, $statePath);

        if (is_array($state)) {
            $removed = $state[$index] ?? null;

            unset($state[$index]);

            StateContainer::writeInto($this, $statePath, array_values($state));

            $this->deleteRemovedStoredFile($statePath, $removed);

            return;
        }

        // Single-file field holding a bare value — any index clears it.
        StateContainer::writeInto($this, $statePath, null);

        $this->deleteRemovedStoredFile($statePath, $state);
    }

    /**
     * Physically tear down a just-removed stored file when its field opts into
     * disk cleanup. A pending {@see TemporaryUploadedFile}
     * or an empty slot is never a stored reference, so it is left alone — the
     * field itself decides whether (and how) a real stored file is deleted.
     */
    protected function deleteRemovedStoredFile(string $statePath, mixed $removed): void
    {
        if (! is_string($removed) || $removed === '') {
            return;
        }

        $field = $this->resolveFileUploadField($statePath);

        if ($field instanceof FileUpload && $field->isStoredReference($removed)) {
            $field->deleteStoredFile($removed);
        }
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
