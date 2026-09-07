<?php

declare(strict_types=1);

namespace NyonCode\WireModuleMedia\Concerns;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use NyonCode\WireModuleMedia\Models\Media;

/**
 * Files attached to a record: `use HasMedia;` on any model.
 *
 * The link is a row in `wire_mediables`, not a column on the model, and that is
 * what makes the library usable from anywhere: a product, a post, a user and a
 * ticket all attach the *same* file rather than each storing a path of their
 * own. One upload, one URL, one alt text — change the description once and every
 * page using it says the new thing.
 *
 *   class Post extends Model
 *   {
 *       use HasMedia;
 *   }
 *
 *   $post->attachMedia($media);                  // the default collection
 *   $post->attachMedia($cover, 'cover');         // a named one
 *   $post->media('gallery');                     // what is in one
 *
 * **Collections are named sets, not types.** `cover`, `gallery`, `attachments`
 * — a record carries several and a form asks for one, which is what lets one
 * model have a single hero image and a list of downloads without either
 * knowing about the other.
 *
 * Nothing here deletes a file. Detaching removes the link and leaves the file in
 * the library, because a file is the library's and not the record's — the record
 * that happened to be deleted last must not take everybody else's image with it.
 *
 * @phpstan-require-extends Model
 */
trait HasMedia
{
    /**
     * Every file attached to this record, across all collections.
     *
     * @return MorphToMany<Media, $this>
     */
    public function allMedia(): MorphToMany
    {
        return $this->morphToMany(Media::class, 'mediable', 'wire_mediables', 'mediable_id', 'media_id')
            ->withPivot(['collection', 'sort'])
            ->withTimestamps()
            ->orderBy('wire_mediables.sort');
    }

    /**
     * The files in one collection, in the order they were arranged.
     *
     * @return Collection<int, Media>
     */
    public function media(string $collection = 'default'): Collection
    {
        /** @var Collection<int, Media> $media */
        $media = $this->allMedia()->wherePivot('collection', $collection)->get();

        return $media;
    }

    /** The first file in a collection — what a `cover` is, without a second name for it. */
    public function firstMedia(string $collection = 'default'): ?Media
    {
        return $this->media($collection)->first();
    }

    /**
     * Attach one file, at the end of its collection.
     *
     * Attaching what is already there is a no-op rather than a duplicate row:
     * every picker double-fires eventually, and the result renders as the same
     * photo twice with nothing to say which copy to remove.
     */
    public function attachMedia(Media|int $media, string $collection = 'default'): static
    {
        $id = $media instanceof Media ? (int) $media->getKey() : $media;

        if ($this->allMedia()->wherePivot('collection', $collection)->whereKey($id)->exists()) {
            return $this;
        }

        $next = (int) $this->allMedia()->wherePivot('collection', $collection)->max('wire_mediables.sort');

        $this->allMedia()->attach($id, ['collection' => $collection, 'sort' => $next + 1]);

        return $this;
    }

    /**
     * Replace a collection with exactly these files, in this order.
     *
     * What a form field saves. Written as a replacement rather than as a diff
     * because the order is part of the answer, and a diff that preserved the old
     * order would quietly ignore a reordering nobody else can see.
     *
     * @param  array<int, Media|int>  $media
     */
    public function syncMedia(array $media, string $collection = 'default'): static
    {
        $this->allMedia()->wherePivot('collection', $collection)->detach();

        foreach (array_values($media) as $index => $item) {
            $id = $item instanceof Media ? (int) $item->getKey() : (int) $item;

            $this->allMedia()->attach($id, ['collection' => $collection, 'sort' => $index]);
        }

        return $this;
    }

    /** Remove the link. The file stays in the library. */
    public function detachMedia(Media|int $media, string $collection = 'default'): static
    {
        $id = $media instanceof Media ? (int) $media->getKey() : $media;

        $this->allMedia()->wherePivot('collection', $collection)->detach($id);

        return $this;
    }
}
