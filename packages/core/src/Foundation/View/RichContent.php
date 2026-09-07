<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\View;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use NyonCode\WireCore\Foundation\Mentions\MentionRenderer;
use NyonCode\WireForms\Components\TiptapEditor;

/**
 * Rich-text content, with its mentions read back from the database:
 * `<x-wire::rich-content :html="$post->body" />`.
 *
 * This is how content written in a {@see TiptapEditor}
 * with mentions is displayed. `{!! $post->body !!}` would print the identities
 * the document stores and no links at all — the mention's name and URL are
 * deliberately not in there, so that a renamed record reads renamed everywhere
 * it was ever mentioned and a link cannot outlive the permission behind it.
 *
 * Content with no mentions passes through untouched, so this is safe to use as
 * the default way to print editor output.
 *
 * The markup itself is trusted exactly as much as it was before: this resolves
 * mentions, it does not sanitise. What an application allowed into the column is
 * what it gets back out.
 */
class RichContent extends Component
{
    public string $content;

    /**
     * @param  string|null  $html  The stored document.
     * @param  string|null  $zone  The mount point the calling page read in `mount()`,
     *                             for mentions linked through a resource's own pages.
     */
    public function __construct(
        ?string $html = null,
        ?string $zone = null,
        public ?string $class = null,
    ) {
        $this->content = app(MentionRenderer::class)->render($html ?? '', $zone);
    }

    public function render(): View
    {
        return view('wire-core::foundation.rich-content');
    }
}
