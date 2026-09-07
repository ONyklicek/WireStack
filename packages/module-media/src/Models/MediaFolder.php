<?php

declare(strict_types=1);

namespace NyonCode\WireModuleMedia\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use NyonCode\WireModuleMedia\Exceptions\MediaException;

/**
 * One folder in the library.
 *
 * **Logical, not physical.** A folder is a row that files point at; nothing on
 * the disk moves when a file changes folders or a folder is renamed. The disk
 * layout stays whatever `wire-module-media.directory` says it is, and a URL a
 * published page already links to keeps working — which is the trade being made
 * here, and it is deliberately the opposite of what a desktop file manager does.
 * Tidying the bucket is not worth a broken image on a live page.
 *
 * The full `path` is stored rather than walked, because a breadcrumb is drawn on
 * every screen of the library and walking parents is one query per level. The
 * cost is that renaming or moving a folder rewrites the paths of everything
 * below it, which is what {@see rewriteSubtree()} is for.
 *
 * @property int $id
 * @property int|null $parent_id
 * @property string $name
 * @property string $path
 */
class MediaFolder extends Model
{
    protected $guarded = [];

    public function getTable(): string
    {
        return (string) config('wire-module-media.folders_table', 'wire_media_folders');
    }

    /** @return BelongsTo<self, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** @return HasMany<self, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('name');
    }

    /** @return HasMany<Media, $this> */
    public function media(): HasMany
    {
        return $this->hasMany(Media::class, 'folder_id');
    }

    /**
     * Create a folder under a parent, or refuse and say why.
     *
     * @throws MediaException When the name is empty or already used beside it.
     */
    public static function createIn(?self $parent, string $name): self
    {
        $name = self::clean($name);

        self::guardName($parent?->id, $name);

        return self::create([
            'parent_id' => $parent?->id,
            'name' => $name,
            'path' => $parent === null ? $name : $parent->path.'/'.$name,
        ]);
    }

    /**
     * @throws MediaException When the name is empty or already used beside it.
     */
    public function rename(string $name): self
    {
        $name = self::clean($name);

        if ($name === $this->name) {
            return $this;
        }

        self::guardName($this->parent_id, $name, $this->id);

        $this->update([
            'name' => $name,
            'path' => $this->parentPath().$name,
        ]);

        $this->rewriteSubtree();

        return $this;
    }

    /**
     * Move this folder under another, or to the root when given null.
     *
     * @throws MediaException When the destination is inside this folder, or the
     *                        name is already used there.
     */
    public function moveTo(?self $parent): self
    {
        if ($parent !== null && ($parent->is($this) || $parent->isUnder($this))) {
            throw MediaException::folderMovedIntoItself($this->name);
        }

        self::guardName($parent?->id, $this->name, $this->id);

        $this->update([
            'parent_id' => $parent?->id,
            'path' => $parent === null ? $this->name : $parent->path.'/'.$this->name,
        ]);

        $this->rewriteSubtree();

        return $this;
    }

    /**
     * Delete it, but only once it is empty.
     *
     * @throws MediaException When anything is still inside.
     */
    public function deleteEmpty(): void
    {
        $folders = $this->children()->count();
        $files = $this->media()->count();

        if ($folders > 0 || $files > 0) {
            throw MediaException::folderNotEmpty($this->name, $folders, $files);
        }

        $this->delete();
    }

    /** Whether this folder sits anywhere below the given one. */
    public function isUnder(self $folder): bool
    {
        return str_starts_with($this->path, $folder->path.'/');
    }

    /**
     * This folder and every folder under it, by id.
     *
     * Read from the stored paths in one query rather than by walking children,
     * which is the whole reason the path is stored.
     *
     * @return array<int, int>
     */
    public function subtreeIds(): array
    {
        /** @var array<int, int> $ids */
        $ids = static::query()
            ->where('id', $this->id)
            ->orWhere('path', 'like', $this->path.'/%')
            ->pluck('id')
            ->all();

        return $ids;
    }

    /**
     * The folders on the way here, root first, this one last.
     *
     * Derived from the stored path rather than queried per level — one query for
     * a breadcrumb of any depth.
     *
     * @return Collection<int, self>
     */
    public function breadcrumb(): Collection
    {
        $paths = [];
        $carry = '';

        foreach (explode('/', $this->path) as $segment) {
            $carry = $carry === '' ? $segment : $carry.'/'.$segment;
            $paths[] = $carry;
        }

        /** @var Collection<int, self> $folders */
        $folders = static::query()->whereIn('path', $paths)->get()
            ->sortBy(static fn (self $folder): int => mb_substr_count($folder->path, '/'))
            ->values();

        return $folders;
    }

    /**
     * Rewrite the stored path of everything below this folder.
     *
     * Called after a rename or a move, because the path each descendant stores
     * begins with the one that just changed. Done as a walk rather than as one
     * `replace()` UPDATE: the string function differs across the databases this
     * package supports, and a folder tree is small enough that being portable
     * costs nothing worth measuring.
     */
    protected function rewriteSubtree(): void
    {
        foreach ($this->children()->get() as $child) {
            $child->update(['path' => $this->path.'/'.$child->name]);
            $child->rewriteSubtree();
        }
    }

    protected function parentPath(): string
    {
        $parent = $this->parent()->first();

        return $parent === null ? '' : $parent->path.'/';
    }

    /**
     * @throws MediaException When the name is already used beside it.
     */
    protected static function guardName(?int $parentId, string $name, ?int $exceptId = null): void
    {
        $taken = static::query()
            ->where('parent_id', $parentId)
            ->where('name', $name)
            ->when($exceptId !== null, static fn ($query) => $query->whereKeyNot($exceptId))
            ->exists();

        if ($taken) {
            throw MediaException::folderNameTaken($name);
        }
    }

    /**
     * @throws MediaException When nothing is left of the name.
     */
    protected static function clean(string $name): string
    {
        // Slashes out, because the name is a segment of the stored path and one
        // inside it would invent a level nothing knows about.
        $name = trim(str_replace(['/', '\\'], ' ', $name));

        if ($name === '') {
            throw MediaException::folderNameEmpty();
        }

        return $name;
    }
}
