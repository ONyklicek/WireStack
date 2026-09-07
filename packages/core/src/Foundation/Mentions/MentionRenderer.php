<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Mentions;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Foundation\Contracts\ResolvesRecordUrls;
use NyonCode\WireCore\Foundation\Mentions\Contracts\Mentionable;
use NyonCode\WireCore\Foundation\Support\MorphedModels;

/**
 * Turns the identities a rich-text document stores into the text a reader sees.
 *
 * A stored mention is deliberately not renderable on its own — it carries a
 * type, an id and a trigger, and no live URL at all:
 *
 * ```html
 * <span data-type="mention" data-mention-trigger="#"
 *       data-mention-type="article" data-id="12">#Ceník 2026</span>
 * ```
 *
 * so `{!! $post->body !!}` is no longer how such content is displayed. That is
 * the price of the guarantee: a renamed record reads renamed everywhere it was
 * ever mentioned, and a link can never outlive the permission that granted it.
 * The text inside the span is what it read as when it was written — a fallback
 * for a record that has since gone, discarded on every render that resolves.
 *
 * **One query per type, not per mention.** A document naming twelve articles and
 * three users costs two lookups: references are collected, grouped by their
 * stored type and fetched with a single `whereKey()` each.
 */
final class MentionRenderer
{
    /** Marks a mention node, matching TipTap's own `data-type` convention. */
    public const NODE_TYPE = 'mention';

    public function __construct(
        private readonly MentionRegistry $registry,
        private readonly ResolvesRecordUrls $urls,
    ) {}

    /**
     * Every mention the document holds, in document order and with duplicates
     * kept — callers that want identities (a notification sweep) can dedupe on
     * {@see MentionReference::key()}.
     *
     * @return array<int, MentionReference>
     */
    public function extract(string $html): array
    {
        if (! $this->mightHoldMentions($html)) {
            return [];
        }

        $dom = $this->parse($html);
        $references = [];

        foreach ($this->mentionNodes($dom) as $node) {
            $reference = $this->referenceFrom($node);

            if ($reference !== null) {
                $references[] = $reference;
            }
        }

        return $references;
    }

    /**
     * The document with every mention re-read from the database.
     *
     * Content holding no mentions is returned byte-for-byte: there is no reason
     * to put an application's markup through a DOM round-trip for nothing.
     *
     * @param  string|null  $zone  The mount point the calling page read in `mount()`,
     *                             for mentions linked through a resource's own pages.
     */
    public function render(string $html, ?string $zone = null): string
    {
        if (! $this->mightHoldMentions($html)) {
            return $html;
        }

        $dom = $this->parse($html);
        $nodes = $this->mentionNodes($dom);
        $references = [];

        foreach ($nodes as $node) {
            $reference = $this->referenceFrom($node);

            if ($reference !== null) {
                $references[$this->nodeId($node)] = $reference;
            }
        }

        if ($references === []) {
            return $html;
        }

        $resolved = $this->resolve($references, $zone);

        foreach ($nodes as $node) {
            $reference = $references[$this->nodeId($node)] ?? null;

            if ($reference === null) {
                continue;
            }

            $node->parentNode?->replaceChild(
                $this->renderNode($dom, $reference, $resolved[$reference->key()] ?? null),
                $node,
            );
        }

        return $this->serialize($dom);
    }

    // ─── Resolution ────────────────────────────────────────────────

    /**
     * @param  array<string, MentionReference>  $references
     * @return array<string, ResolvedMention> Keyed by {@see MentionReference::key()}.
     */
    private function resolve(array $references, ?string $zone): array
    {
        $idsByType = [];

        foreach ($references as $reference) {
            // Keyed by id so a type mentioned twelve times is still one `whereKey`.
            $idsByType[$reference->type][$reference->id] = $reference->id;
        }

        $resolved = [];

        foreach ($idsByType as $type => $ids) {
            foreach ($this->resolveType((string) $type, array_values($ids), $zone) as $key => $mention) {
                $resolved[$key] = $mention;
            }
        }

        return $resolved;
    }

    /**
     * @param  array<int, string>  $ids
     * @return array<string, ResolvedMention>
     */
    private function resolveType(string $type, array $ids, ?string $zone): array
    {
        $class = MorphedModels::classFor($type);

        if ($class === null) {
            // A type the application has since renamed or dropped. Every mention
            // of it falls back, which is exactly what the stored label is for.
            return [];
        }

        $entry = $this->registry->for($type);
        $query = $class::query();

        if ($entry !== null) {
            $query = $entry->applyQuery($query);
        }

        $resolved = [];

        foreach ($query->whereKey($ids)->get() as $record) {
            /** @var Model $record */
            $label = $record instanceof Mentionable
                ? $record->getMentionLabel()
                : $entry?->resolveLabel($record);

            // Nothing fresh to say. Falling back beats rendering a blank where a
            // name used to be — a model that is neither Mentionable nor
            // registered simply keeps the label it was written with.
            if ($label === null || $label === '') {
                continue;
            }

            $resolved[$type.':'.$record->getKey()] = new ResolvedMention(
                $label,
                $this->urlFor($record, $entry, $zone),
            );
        }

        return $resolved;
    }

    /**
     * A Mentionable answers for itself, null included: a record that says it has
     * no URL means it, and is rendered named but not clickable. Only a model
     * that never spoke gets the registry's closure, and then whatever owns
     * screens ({@see ResolvesRecordUrls}) as the last guess.
     */
    private function urlFor(Model $record, ?RegisteredMention $entry, ?string $zone): ?string
    {
        if ($record instanceof Mentionable) {
            return $record->getMentionUrl();
        }

        return $entry?->resolveUrl($record) ?? $this->urls->urlForRecord($record, $zone);
    }

    // ─── Nodes ─────────────────────────────────────────────────────

    private function renderNode(DOMDocument $dom, MentionReference $reference, ?ResolvedMention $mention): DOMElement
    {
        $url = $mention?->url;

        $element = $dom->createElement($url !== null && $url !== '' ? 'a' : 'span');

        if ($url !== null && $url !== '') {
            $element->setAttribute('href', $url);
        }

        $element->setAttribute('class', $mention === null
            ? 'wire-mention wire-mention--unresolved'
            : 'wire-mention');

        // The identity is written back so rendered output stays re-renderable —
        // and so a client-side reader (a notification badge, an analytics hook)
        // sees the same attributes the stored document uses.
        $element->setAttribute('data-type', self::NODE_TYPE);
        $element->setAttribute('data-mention-trigger', $reference->trigger);
        $element->setAttribute('data-mention-type', $reference->type);
        $element->setAttribute('data-id', $reference->id);

        // textContent, never markup: this is the one string in the document that
        // comes straight out of the database on every render.
        $element->textContent = $mention === null
            ? $reference->fallbackText()
            : $reference->trigger.$mention->label;

        return $element;
    }

    private function referenceFrom(DOMElement $node): ?MentionReference
    {
        $type = trim($node->getAttribute('data-mention-type'));
        $id = trim($node->getAttribute('data-id'));

        if ($type === '' || $id === '') {
            return null;
        }

        return new MentionReference(
            type: $type,
            id: $id,
            trigger: $node->getAttribute('data-mention-trigger'),
            label: $node->textContent,
        );
    }

    /**
     * @return array<int, DOMElement>
     */
    private function mentionNodes(DOMDocument $dom): array
    {
        $nodes = [];

        foreach ((new DOMXPath($dom))->query('//*[@data-type="'.self::NODE_TYPE.'"]') ?: [] as $node) {
            if ($node instanceof DOMElement) {
                $nodes[] = $node;
            }
        }

        return $nodes;
    }

    /** Object identity, so a node found twice is looked up once. */
    private function nodeId(DOMElement $node): string
    {
        return spl_object_hash($node);
    }

    // ─── Parsing ───────────────────────────────────────────────────

    /**
     * Cheap enough to run on every field of every row: content with no mention
     * marker never reaches the DOM parser at all.
     */
    private function mightHoldMentions(string $html): bool
    {
        return $html !== '' && str_contains($html, 'data-mention-type');
    }

    private function parse(string $html): DOMDocument
    {
        $dom = new DOMDocument('1.0', 'UTF-8');

        $previous = libxml_use_internal_errors(true);

        // The XML prologue is what makes libxml read the fragment as UTF-8 (it
        // assumes ISO-8859-1 otherwise, and `Ceník` comes back mojibake). The
        // wrapper keeps the fragment a fragment: NOIMPLIED stops libxml adding
        // <html><body>, and the wrapper gives the nodes a single parent to
        // serialize back out of. The return value is not read: libxml is asked to
        // keep its complaints to itself and parses malformed HTML into *something*
        // regardless, and a fragment that produced no root is handled where the
        // root is read, not here.
        $dom->loadHTML(
            '<?xml encoding="utf-8" ?><div data-wire-mention-root>'.$html.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD,
        );

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $dom;
    }

    private function serialize(DOMDocument $dom): string
    {
        $html = '';

        // The wrapper div is the document element — parse() always writes one, and
        // serialize() only runs on a document that already yielded mention nodes.
        foreach ($dom->documentElement->childNodes as $child) {
            $html .= $dom->saveHTML($child);
        }

        return $html;
    }
}
