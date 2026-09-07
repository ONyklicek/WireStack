<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Mentions\Contracts;

use NyonCode\WireCore\Foundation\Mentions\MentionReference;
use NyonCode\WireCore\Foundation\Mentions\MentionRegistry;

/**
 * A record that can be mentioned inside rich text, and says how it reads.
 *
 * Mentions are never rendered from what was stored: the document holds an
 * identity ({@see MentionReference}) and
 * the label is looked up again on every render, so renaming a record renames it
 * everywhere it was ever mentioned. Which means something has to know the fresh
 * name — and the record itself is the one thing that always does.
 *
 * A model that does not implement this is still mentionable: it is resolved
 * through {@see MentionRegistry} instead,
 * which is the escape hatch for models the application does not own. Only when
 * neither exists does the renderer fall back to the label stored in the
 * document — correct at the time it was written, and not after.
 */
interface Mentionable
{
    /** The record's name as it should read inside the text, without the trigger. */
    public function getMentionLabel(): string;

    /**
     * Where the mention links to, or null for a mention that is named but not
     * clickable. Never a stored value — this is called on every render.
     */
    public function getMentionUrl(): ?string;
}
