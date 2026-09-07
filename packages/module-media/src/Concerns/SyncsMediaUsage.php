<?php

declare(strict_types=1);

namespace NyonCode\WireModuleMedia\Concerns;

use NyonCode\WireModuleMedia\Support\ContentMedia;
use NyonCode\WireModuleMedia\Support\MediaUsage;

/**
 * Keep a record's written content and the media library in step.
 *
 * A picture inserted through the rich text editor used to leave nothing behind
 * but a URL, so a photograph in twelve articles reported zero uses — and the
 * confirmation in front of every delete, and the warning in front of every
 * replacement, were built on that zero. This is what makes those true.
 *
 *   class Post extends Model
 *   {
 *       use HasMedia;
 *       use SyncsMediaUsage;
 *
 *       protected array $mediaContent = ['body', 'perex'];
 *   }
 *
 * **Opt-in per model.** A model that does not use this reports nothing, exactly
 * as before, so nothing changes by upgrading. It needs {@see HasMedia} beside
 * it, because the link it writes is the same link a field writes — one table,
 * one query, one mental model. See ADR 0034.
 */
trait SyncsMediaUsage
{
    public static function bootSyncsMediaUsage(): void
    {
        static::saved(static function (self $model): void {
            $model->syncMediaUsage();
        });
    }

    /**
     * Replace this record's content links with what its content now points at.
     *
     * Synced rather than appended: a picture taken out of an article has to take
     * its link with it, or the count only ever grows and stops meaning anything.
     */
    public function syncMediaUsage(): void
    {
        $ids = [];

        foreach ($this->mediaContentAttributes() as $attribute) {
            $value = $this->getAttribute($attribute);

            $ids = [...$ids, ...ContentMedia::idsIn(is_string($value) ? $value : null)];
        }

        $this->syncMedia(array_values(array_unique($ids)), MediaUsage::CONTENT);
    }

    /**
     * Which attributes hold written content.
     *
     * Public because the backfill command is a legitimate second caller: it has
     * to read the same list to know what to scan in content written before the
     * id was kept.
     *
     * Declared by the model rather than guessed at: a `text` column may be a
     * body of HTML or may be a note nobody writes pictures into, and scanning
     * every string attribute of every save to find out is a cost paid on rows
     * that could never match.
     *
     * @return array<int, string>
     */
    public function mediaContentAttributes(): array
    {
        /** @var array<int, string> */
        return property_exists($this, 'mediaContent') ? $this->mediaContent : [];
    }
}
