<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Mentions;

/**
 * What a mention reads as *now* — the answer a render pass looked up, as opposed
 * to the {@see MentionReference} the document stored.
 */
final class ResolvedMention
{
    public function __construct(
        public readonly string $label,
        public readonly ?string $url = null,
    ) {}
}
