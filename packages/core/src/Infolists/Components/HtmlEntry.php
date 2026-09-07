<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Infolists\Components;

use NyonCode\WireCore\Foundation\Mentions\MentionRenderer;

/**
 * Rich-text entry — displays stored editor content as markup rather than as
 * escaped text, with its mentions read back from the database.
 *
 * The reason this exists as its own entry and not as a flag on {@see TextEntry}
 * is what it does with the value: a text entry escapes, and must keep escaping.
 * This one prints markup, which is a decision an application makes deliberately
 * about a column it controls — so it is a different entry with a different name,
 * not a switch that quietly turns escaping off.
 *
 * Mentions are resolved on every render ({@see MentionRenderer}): a renamed
 * record reads renamed here too, and a mention whose record is gone degrades to
 * plain text instead of a dead link.
 */
class HtmlEntry extends Entry
{
    protected ?string $zone = null;

    /**
     * The mount point the page was opened in, for mentions linked through a
     * resource's own pages. A page that read a zone in `mount()` passes it on;
     * everything else links through the default zone.
     */
    public function zone(?string $zone): static
    {
        $this->zone = $zone;

        return $this;
    }

    public function getZone(): ?string
    {
        return $this->zone;
    }

    /** The stored document with every mention resolved, ready to print. */
    public function getRenderedHtml(): string
    {
        $state = $this->getState();

        if ($state === null || $state === '' || ! is_scalar($state)) {
            return '';
        }

        return app(MentionRenderer::class)->render((string) $state, $this->zone);
    }

    /**
     * Content-driven and unique per record, exactly like a table's TextColumn —
     * a render memo over it is pure overhead. Opting out also keeps a resolved
     * mention from being shared with a row it was never looked up for.
     */
    protected function renderCacheSignature(): ?string
    {
        return null;
    }

    protected function viewName(): string
    {
        return 'wire-core::infolists.entries.html';
    }
}
