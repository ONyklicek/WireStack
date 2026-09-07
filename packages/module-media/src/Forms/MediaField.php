<?php

declare(strict_types=1);

namespace NyonCode\WireModuleMedia\Forms;

use Illuminate\Database\Eloquent\Model;
use NyonCode\WireForms\Components\Field;
use NyonCode\WireForms\Contracts\SavesAfterRecord;
use NyonCode\WireModuleMedia\Concerns\HasMedia;
use NyonCode\WireModuleMedia\Models\Media;

/**
 * Files from the library, on any form: `MediaField::make('gallery')`.
 *
 * The difference from `FileUpload` is what the record ends up holding. A file
 * upload puts a *path* on the row, so the same photograph used by a product, a
 * post and an email is three files with three URLs and three descriptions that
 * drift. This attaches the library's row instead: one file, one URL, one alt
 * text, and changing the description once changes it everywhere it is used.
 *
 *   MediaField::make('cover'),
 *
 *   MediaField::make('gallery')
 *       ->multiple()
 *       ->collection('gallery')
 *       ->accepts('image/'),
 *
 * **It saves itself.** The name is a collection, never a column, so the field
 * implements {@see SavesAfterRecord}: its value is taken out of the data before
 * the record is written and written to the pivot afterwards, once the record has
 * a key. The model needs {@see HasMedia} and nothing else — no column, no
 * migration, no `afterSave` closure to remember.
 *
 * The picker it opens is the library itself, in a modal the shell renders once
 * per page. That is deliberate: a chooser that is a different screen from the
 * library is one that slowly stops matching it — different search, different
 * empty state, folders that work in one and not the other.
 */
class MediaField extends Field implements SavesAfterRecord
{
    protected bool $multiple = false;

    protected ?string $collection = null;

    protected string $accepts = '';

    /** Allow more than one file, in an order the person arranges. */
    public function multiple(bool $condition = true): static
    {
        $this->multiple = $condition;

        return $this;
    }

    /**
     * Which named set on the record this is.
     *
     * Defaults to the field's own name, which is what makes
     * `MediaField::make('gallery')` complete on its own — a second name for the
     * same thing is a second thing to keep in step.
     */
    public function collection(string $collection): static
    {
        $this->collection = $collection;

        return $this;
    }

    /**
     * Offer only files whose mime type starts with one of these.
     *
     * `'image/'`, or `'image/,application/pdf'`. A picker that offers a PDF
     * where an image is meant is a picker that produces a broken page later, and
     * later is after somebody published it.
     */
    public function accepts(string $accepts): static
    {
        $this->accepts = $accepts;

        return $this;
    }

    public function isMultiple(): bool
    {
        return $this->multiple;
    }

    public function getCollection(): string
    {
        return $this->collection ?? $this->getName();
    }

    public function getAccepts(): string
    {
        return $this->accepts;
    }

    /**
     * The chosen files, in the order they are held.
     *
     * Resolved from the state's ids rather than kept as models, because the
     * state survives a round trip and a model does not — and a row deleted from
     * the library between two renders should disappear from the field rather
     * than render as a broken tile.
     *
     * @return array<int, Media>
     */
    public function getSelectedMedia(mixed $state): array
    {
        $ids = $this->normalise($state);

        if ($ids === []) {
            return [];
        }

        $media = Media::query()->whereIn('id', $ids)->get()->keyBy('id');

        // Ordered by the state, not by the query: the order is the person's
        // answer, and `whereIn` does not promise to keep it.
        return array_values(array_filter(array_map(
            static fn (int $id): ?Media => $media->get($id),
            $ids,
        )));
    }

    /**
     * Write the collection on the record that was just saved.
     *
     * A replacement rather than a diff, because the order is part of the answer
     * and a diff that kept the old order would silently ignore a reordering.
     */
    public function saveAfterRecord(Model $record, mixed $state): void
    {
        // The trait is what makes a record able to hold media at all. Asked for
        // rather than assumed, so a model that has not opted in is left alone
        // instead of failing on a method it never declared.
        if (! in_array(HasMedia::class, class_uses_recursive($record), true)) {
            return;
        }

        /** @var Model&object{syncMedia: callable} $record */
        $record->syncMedia($this->normalise($state), $this->getCollection());
    }

    /**
     * Whatever the state holds, as a list of ids.
     *
     * The state is a list when the field takes several files and a single value
     * when it takes one, and it arrives as strings from the browser — so this is
     * the one place that decides what "the value" is.
     *
     * @return array<int, int>
     */
    public function normalise(mixed $state): array
    {
        $values = is_array($state) ? $state : ($state === null || $state === '' ? [] : [$state]);

        return array_values(array_filter(
            array_map(static fn (mixed $id): int => (int) $id, $values),
            static fn (int $id): bool => $id > 0,
        ));
    }

    protected function viewName(): string
    {
        return 'wire-module-media::components.media-field';
    }
}
