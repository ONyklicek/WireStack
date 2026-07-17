<?php

declare(strict_types=1);

namespace NyonCode\WireBoost\Support\Docs;

/**
 * Ranked, section-level search over the wireStack knowledge corpus.
 *
 * Scoring is BM25 over section bodies, plus bonuses for terms that hit a
 * section's heading or its document title, plus a bonus for a verbatim phrase
 * match. This replaces a raw `substr_count` over whole files, which had three
 * compounding faults: substrings matched inside unrelated words, long documents
 * always outranked precise short ones because nothing normalised for length, and
 * a heading — the strongest signal a doc offers about what a passage is *about* —
 * counted for exactly as much as a passing mention in a code sample.
 *
 * Everything is computed in-process from bundled Markdown: no embeddings, no
 * hosted service, no network. The index is built once per process and memoised.
 */
class DocsIndex
{
    /** Saturation: how fast repeated terms stop adding score. */
    private const K1 = 1.2;

    /** Length normalisation: 0 = ignore length, 1 = fully normalise. */
    private const B = 0.75;

    private const HEADING_WEIGHT = 2.5;

    private const TITLE_WEIGHT = 1.2;

    private const PHRASE_WEIGHT = 4.0;

    /** @var array<int, DocSection>|null */
    private ?array $sections = null;

    /** @var array<int, array<string, int>> Section index => term => occurrences. */
    private array $frequencies = [];

    /** @var array<int, array<string, bool>> Section index => terms in its heading. */
    private array $headings = [];

    /** @var array<int, array<string, bool>> Section index => terms in its doc title. */
    private array $titles = [];

    /** @var array<int, int> Section index => body length in terms. */
    private array $lengths = [];

    /** @var array<string, int> Term => number of sections containing it. */
    private array $documentFrequency = [];

    private float $averageLength = 0.0;

    public function __construct(
        private DocsCorpus $corpus,
        private DocParser $parser,
        private Tokenizer $tokenizer,
    ) {}

    public static function default(): self
    {
        return new self(DocsCorpus::default(), new DocParser, new Tokenizer);
    }

    public function corpus(): DocsCorpus
    {
        return $this->corpus;
    }

    /**
     * Every indexed section.
     *
     * @return array<int, DocSection>
     */
    public function sections(): array
    {
        $this->build();

        /** @var array<int, DocSection> $sections */
        $sections = $this->sections;

        return $sections;
    }

    /**
     * One section by its id ("docs/table/columns/index.md#shared-column-api").
     */
    public function section(string $id): ?DocSection
    {
        $id = trim($id);

        foreach ($this->sections() as $section) {
            if ($section->id === $id) {
                return $section;
            }
        }

        return null;
    }

    /**
     * Every section of one document, in document order.
     *
     * @return array<int, DocSection>
     */
    public function document(string $id): array
    {
        $id = trim($id);

        return array_values(array_filter(
            $this->sections(),
            static fn (DocSection $section): bool => $section->document === $id,
        ));
    }

    /**
     * Rank sections against a free-text query.
     *
     * @return array<int, array<string, mixed>>
     */
    public function search(string $query, ?string $package = null, int $limit = 5): array
    {
        $terms = $this->tokenizer->unique($query);

        if ($terms === []) {
            return [];
        }

        $this->build();

        $wanted = $package === null || trim($package) === ''
            ? null
            : $this->corpus->normalisePackage($package);

        $phrase = trim(strtolower($query));
        $scored = [];

        foreach ($this->sections() as $index => $section) {
            if ($wanted !== null && $section->package !== $wanted) {
                continue;
            }

            $score = $this->score($index, $terms, $phrase);

            if ($score <= 0.0) {
                continue;
            }

            $scored[] = ['index' => $index, 'score' => $score];
        }

        usort($scored, function (array $a, array $b): int {
            return $b['score'] <=> $a['score']
                ?: strcmp($this->sections()[$a['index']]->id, $this->sections()[$b['index']]->id);
        });

        $results = [];

        foreach (array_slice($scored, 0, max(1, $limit)) as $hit) {
            $section = $this->sections()[$hit['index']];

            $results[] = array_merge($section->toArray(), [
                'score' => round($hit['score'], 3),
                'snippet' => $this->snippet($section, $terms),
            ]);
        }

        return $results;
    }

    /**
     * BM25 over the body, plus heading/title/phrase bonuses.
     *
     * @param  array<int, string>  $terms
     */
    private function score(int $index, array $terms, string $phrase): float
    {
        $score = 0.0;
        $length = $this->lengths[$index] ?: 1;

        foreach ($terms as $term) {
            $idf = $this->idf($term);

            if ($idf <= 0.0) {
                continue;
            }

            $frequency = $this->frequencies[$index][$term] ?? 0;

            if ($frequency > 0) {
                $score += $idf * (($frequency * (self::K1 + 1)) / ($frequency + self::K1 * (1 - self::B + self::B * ($length / $this->averageLength))));
            }

            // A heading hit stands on its own: "### Sub-Rows" above prose that
            // never repeats the phrase is exactly the section that was wanted.
            if (isset($this->headings[$index][$term])) {
                $score += $idf * self::HEADING_WEIGHT;
            }

            if (isset($this->titles[$index][$term])) {
                $score += $idf * self::TITLE_WEIGHT;
            }
        }

        if ($score > 0.0 && $phrase !== '' && str_contains(strtolower($this->sections()[$index]->markdown()), $phrase)) {
            $score += self::PHRASE_WEIGHT;
        }

        return $score;
    }

    /**
     * Inverse document frequency — rare terms carry the query, ubiquitous ones
     * ("the", "wire", "column") fade towards zero on their own, which is why no
     * stop-word list is needed.
     */
    private function idf(string $term): float
    {
        $df = $this->documentFrequency[$term] ?? 0;

        if ($df === 0) {
            return 0.0;
        }

        $total = count($this->sections ?? []);

        return log(1 + (($total - $df + 0.5) / ($df + 0.5)));
    }

    private function build(): void
    {
        if ($this->sections !== null) {
            return;
        }

        $this->sections = [];

        foreach ($this->corpus->documents() as $id => $contents) {
            foreach ($this->parser->parse($id, $contents, $this->corpus->packageFor($id)) as $section) {
                $this->sections[] = $section;
            }
        }

        $total = 0;

        foreach ($this->sections as $index => $section) {
            $body = $this->tokenizer->tokenize($section->content);
            $frequencies = array_count_values($body);

            $this->frequencies[$index] = $frequencies;
            $this->lengths[$index] = count($body);
            $this->headings[$index] = array_fill_keys($this->tokenizer->unique($section->heading), true);
            $this->titles[$index] = array_fill_keys($this->tokenizer->unique($section->title), true);

            $total += count($body);

            $present = $frequencies + $this->headings[$index] + $this->titles[$index];

            foreach (array_keys($present) as $term) {
                $this->documentFrequency[$term] = ($this->documentFrequency[$term] ?? 0) + 1;
            }
        }

        $count = count($this->sections);
        $this->averageLength = $count > 0 ? max(1.0, $total / $count) : 1.0;
    }

    /**
     * The most on-topic line of the section, so a result is readable without a
     * follow-up fetch.
     *
     * @param  array<int, string>  $terms
     */
    private function snippet(DocSection $section, array $terms): string
    {
        $best = '';
        $bestHits = 0;

        foreach (preg_split('/\R/', $section->content) ?: [] as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '```')) {
                continue;
            }

            $found = array_intersect($terms, $this->tokenizer->unique($line));
            $hits = count($found);

            if ($hits > $bestHits) {
                $best = $line;
                $bestHits = $hits;
            }

            if ($best === '') {
                $best = $line;
            }
        }

        return mb_strlen($best) > 240 ? mb_substr($best, 0, 237).'...' : $best;
    }
}
