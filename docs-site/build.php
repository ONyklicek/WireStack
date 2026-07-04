<?php

declare(strict_types=1);

use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\MarkdownConverter;
use Symfony\Component\Yaml\Yaml;

require dirname(__DIR__).'/vendor/autoload.php';

$root = dirname(__DIR__);
$siteRoot = __DIR__;
$distRoot = $siteRoot.'/dist';

$pages = pageManifest($root);
$pageMap = [];

foreach ($pages as $page) {
    $pageMap[$page['source']] = $page;
}

// Documentation versions shown in the switcher.
//
// - `path`:      output sub-directory under dist/ ('' = the dist root). This is
//                also the URL segment the switcher links to.
// - `available`: whether the version has been (or will be) built. Unavailable
//                versions render as a disabled "coming soon" entry.
//
// Build a specific version with DOCS_BUILD_VERSION=<label>; with no env var the
// first available version is built (and served from the dist root).
$siteVersions = [
    ['label' => 'v1.x', 'badge' => 'Latest', 'path' => '', 'available' => true],
    ['label' => 'v2.x', 'badge' => 'Soon', 'path' => 'v2', 'available' => false],
];

// Resolve which version this run builds.
$activeVersionLabel = (string) (getenv('DOCS_BUILD_VERSION') ?: '');
$activeVersion = null;

foreach ($siteVersions as $version) {
    if ($activeVersionLabel !== '' && $version['label'] === $activeVersionLabel) {
        $activeVersion = $version;
        break;
    }
}

if ($activeVersion === null) {
    foreach ($siteVersions as $version) {
        if (! empty($version['available'])) {
            $activeVersion = $version;
            break;
        }
    }
}

$activeVersion ??= $siteVersions[0];
$activeVersionLabel = $activeVersion['label'];
$outputSubdir = trim((string) ($activeVersion['path'] ?? ''), '/');
$versionRoot = $outputSubdir === '' ? $distRoot : $distRoot.'/'.$outputSubdir;

// When rebuilding the root version, keep sibling version sub-directories intact
// so they don't get wiped; a sub-directory build only recreates its own folder.
$preserveSubdirs = [];
foreach ($siteVersions as $version) {
    $path = trim((string) ($version['path'] ?? ''), '/');
    if ($path !== '' && $path !== $outputSubdir) {
        $preserveSubdirs[] = $path;
    }
}

ensureDirectory($distRoot);

if ($outputSubdir === '') {
    recreateDirectoryPreserving($distRoot, $preserveSubdirs);
} else {
    recreateDirectory($versionRoot);
}

copyDirectory($siteRoot.'/assets', $versionRoot.'/assets');

$environment = new Environment([
    'renderer' => [
        'soft_break' => "\n",
    ],
]);
$environment->addExtension(new CommonMarkCoreExtension);
$environment->addExtension(new GithubFlavoredMarkdownExtension);
$converter = new MarkdownConverter($environment);

// Metadata for each captured runtime preview image.
$previewMeta = [
    'forms-overview' => ['title' => 'Form Layout', 'caption' => 'Sections, grid layout, toggle, textarea, and action footer.'],
    'forms-repeater' => ['title' => 'Repeater', 'caption' => 'Nested rows with add, remove, and reorder controls.'],
    'table-overview' => ['title' => 'Table Surface', 'caption' => 'Search, filters, actions, and full row rendering.'],
    'table-selection' => ['title' => 'Selection State', 'caption' => 'Bulk-selected rows with the active selection toolbar.'],
    'table-subrows' => ['title' => 'Sub-rows', 'caption' => 'Expanded invoice line items with sortable headers, row actions, and a per-invoice subtotal.'],
    'table-summary' => ['title' => 'Summary Footer', 'caption' => 'Per-row rollup totals, a sum + average footer, and the page/all scope toggle.'],
    'table-subrows-flatten' => ['title' => 'Flatten Mode', 'caption' => 'Every child record rendered inline as a regular table row.'],
    'table-subrows-limit' => ['title' => 'Show More', 'caption' => 'Limited child rows with the per-parent “show more” affordance.'],
    'table-subrows-filter' => ['title' => 'Sub-row Filters', 'caption' => 'A per-child interactive filter bar above the sub-row table.'],
    'sortable-overview' => ['title' => 'Sortable Table', 'caption' => 'Full reorderable table rendered through the sortable runtime.'],
    'sortable-detail' => ['title' => 'Reorder Detail', 'caption' => 'Closer view of row ordering and drag handles.'],
    'core-overview' => ['title' => 'Core Actions', 'caption' => 'Stats, actions, and shared runtime components.'],
    'core-modal' => ['title' => 'Modal Surface', 'caption' => 'Real modal rendering from the shared core component set.'],
    'widgets-overview' => ['title' => 'Widget Dashboard', 'caption' => 'A stats overview with sparklines and a Chart.js chart widget composed in a grid.'],
    'widgets-chart' => ['title' => 'Chart Widget', 'caption' => 'A single chart widget with heading, description, and a live quarter filter.'],
    'widgets-bar-chart' => ['title' => 'Bar Chart Widget', 'caption' => 'Pure-CSS bars: vertical finance, vertical system metrics with grid lines, and horizontal progress.'],
    'infolists-overview' => ['title' => 'Infolist Overview', 'caption' => 'Read-only record display with sections, a column grid, and formatted text/icon/badge entries.'],
    'infolists-entries' => ['title' => 'Infolist Entries', 'caption' => 'Every built-in entry — text, badge, list, boolean icon, color, key-value, and repeatable — bound to one record.'],
];

// Previews are attached only to pages where the screenshot is genuinely on
// topic. Everything else (field pages aside) renders without a preview.
$pagePreviews = [
    'docs/forms/overview.md' => ['forms-overview', 'forms-repeater'],
    'docs/forms/fields/repeater.md' => ['forms-repeater'],
    'docs/table/overview.md' => ['table-overview', 'table-selection'],
    'docs/table/actions.md' => ['table-selection'],
    'docs/table/summaries.md' => ['table-summary', 'table-subrows'],
    'docs/table/sub-rows.md' => ['table-subrows', 'table-subrows-limit', 'table-subrows-filter', 'table-subrows-flatten'],
    'docs/sortable/overview.md' => ['sortable-overview', 'sortable-detail'],
    'docs/sortable/row-sorting.md' => ['sortable-detail'],
    'docs/core/actions.md' => ['core-overview'],
    'docs/core/modals.md' => ['core-modal'],
    'docs/core/widgets.md' => ['widgets-overview', 'widgets-chart', 'widgets-bar-chart'],
    'docs/core/infolists.md' => ['infolists-overview', 'infolists-entries'],
];

// Extra variant previews appended after a field page's primary preview, so one
// field doc can showcase more than one rendering (e.g. Radio cards + buttons).
$fieldExtraPreviews = [
    'docs/forms/fields/radio.md' => [
        ['slug' => 'field-radio-segmented', 'title' => 'Segmented Variant', 'caption' => 'A compact segmented control — a pill highlight slides over a shared track.'],
        ['slug' => 'field-radio-buttons', 'title' => 'Buttons Variant', 'caption' => 'Separate buttons; the selected one is filled with the accent color.'],
        ['slug' => 'field-radio-color', 'title' => 'Any Accent Color', 'caption' => 'Every variant tints the selected option with ->color() — here a row of danger-colored cards.'],
        ['slug' => 'field-radio-sizes', 'title' => 'Sizes', 'caption' => 'The segmented and buttons variants scale with ->sm() / ->md() / ->lg().'],
    ],
];

$renderedPages = [];
$searchIndex = [];

foreach ($pages as $page) {
    $content = renderMarkdownPage(
        markdown: $page['markdown'],
        converter: $converter,
        currentPage: $page,
        pageMap: $pageMap,
        repoRoot: $root,
    );

    $currentFile = $versionRoot.'/'.$page['output'];
    $renderedPages[] = array_merge($page, $content);

    $previewItems = [];

    // Field doc pages get their own single, on-topic preview captured from the
    // runtime (assets/previews/field-<slug>.png) instead of a generic bundle.
    $fieldPreviewImage = fieldPreviewImage($page['sourceRelative'], $versionRoot);

    if ($fieldPreviewImage !== null) {
        $previewItems[] = [
            'image' => relativeAssetPath($currentFile, $versionRoot.'/'.$fieldPreviewImage),
            'title' => $content['title'],
            'caption' => trim($content['excerpt']) !== ''
                ? $content['excerpt']
                : 'Rendered live through the Wire Forms runtime.',
        ];

        // Optional additional variant previews for the same field doc page.
        foreach ($fieldExtraPreviews[$page['sourceRelative']] ?? [] as $extra) {
            $image = 'assets/previews/'.$extra['slug'].'.png';

            if (! is_file($versionRoot.'/'.$image)) {
                continue;
            }

            $previewItems[] = [
                'image' => relativeAssetPath($currentFile, $versionRoot.'/'.$image),
                'title' => $extra['title'],
                'caption' => $extra['caption'],
            ];
        }
    } else {
        foreach ($pagePreviews[$page['sourceRelative']] ?? [] as $slug) {
            $image = 'assets/previews/'.$slug.'.png';

            if (! is_file($versionRoot.'/'.$image) || ! isset($previewMeta[$slug])) {
                continue;
            }

            $previewItems[] = [
                'image' => relativeAssetPath($currentFile, $versionRoot.'/'.$image),
                'title' => $previewMeta[$slug]['title'],
                'caption' => $previewMeta[$slug]['caption'],
            ];
        }
    }

    $previewUrl = $previewItems[0]['image'] ?? null;

    $html = renderTemplate($siteRoot.'/templates/page.php', [
        'siteTitle' => 'Wire Docs',
        'page' => array_merge($page, $content, ['previewUrl' => $previewUrl, 'previewItems' => $previewItems]),
        'navSections' => buildNavSections($pages, $page['source'], $versionRoot),
        'versionMenu' => buildVersionMenu($siteVersions, $page['output'], $outputSubdir, $activeVersionLabel),
        'searchIndexUrl' => relativeAssetPath($currentFile, $versionRoot.'/search-index.json'),
        'cssUrl' => relativeAssetPath($currentFile, $versionRoot.'/assets/site.css'),
        'jsUrl' => relativeAssetPath($currentFile, $versionRoot.'/assets/site.js'),
        'homeUrl' => relativePageUrl($currentFile, $versionRoot.'/index.html'),
    ]);

    ensureDirectory(dirname($currentFile));
    file_put_contents($currentFile, $html);

    $searchIndex[] = [
        'title' => $content['title'],
        'section' => $page['section'],
        'url' => relativePageUrl($versionRoot.'/index.html', $currentFile),
        'excerpt' => $content['excerpt'],
        'text' => $content['plainText'],
    ];
}

$homeHtml = renderTemplate($siteRoot.'/templates/home.php', [
    'siteTitle' => 'Wire Docs',
    'navSections' => buildNavSections($pages, null, $versionRoot),
    'versionMenu' => buildVersionMenu($siteVersions, 'index.html', $outputSubdir, $activeVersionLabel),
    'searchIndexUrl' => 'search-index.json',
    'cssUrl' => 'assets/site.css',
    'jsUrl' => 'assets/site.js',
    'cards' => [
        [
            'title' => 'Wire Forms',
            'href' => relativePageUrl($versionRoot.'/index.html', $versionRoot.'/forms/overview/index.html'),
            'copy' => 'Standalone forms, layouts, validation, and nested repeaters.',
            'image' => 'assets/previews/forms-overview.png',
        ],
        [
            'title' => 'Wire Table',
            'href' => relativePageUrl($versionRoot.'/index.html', $versionRoot.'/table/overview/index.html'),
            'copy' => 'Searchable tables, actions, filters, exports, notifications, and responsive states.',
            'image' => 'assets/previews/table-overview.png',
        ],
        [
            'title' => 'Wire Sortable',
            'href' => relativePageUrl($versionRoot.'/index.html', $versionRoot.'/sortable/overview/index.html'),
            'copy' => 'Row and column reordering built on top of the table runtime.',
            'image' => 'assets/previews/sortable-overview.png',
        ],
        [
            'title' => 'Wire Core',
            'href' => relativePageUrl($versionRoot.'/index.html', $versionRoot.'/core/actions/index.html'),
            'copy' => 'Actions, widgets, modals, notifications, plugins, and shared foundations.',
            'image' => 'assets/previews/core-overview.png',
        ],
    ],
    'galleryCards' => [
        [
            'title' => 'Forms Layout',
            'href' => relativePageUrl($versionRoot.'/index.html', $versionRoot.'/forms/overview/index.html'),
            'copy' => 'Primary form rendering with sections and inputs.',
            'image' => 'assets/previews/forms-overview.png',
        ],
        [
            'title' => 'Forms Repeater',
            'href' => relativePageUrl($versionRoot.'/index.html', $versionRoot.'/forms/fields/repeater/index.html'),
            'copy' => 'Focused nested repeater with item actions.',
            'image' => 'assets/previews/forms-repeater.png',
        ],
        [
            'title' => 'Table Surface',
            'href' => relativePageUrl($versionRoot.'/index.html', $versionRoot.'/table/overview/index.html'),
            'copy' => 'Table toolbar, search, filters, and rows.',
            'image' => 'assets/previews/table-overview.png',
        ],
        [
            'title' => 'Table Selection',
            'href' => relativePageUrl($versionRoot.'/index.html', $versionRoot.'/table/actions/index.html'),
            'copy' => 'Bulk-selected rows with active selection state.',
            'image' => 'assets/previews/table-selection.png',
        ],
        [
            'title' => 'Sortable Overview',
            'href' => relativePageUrl($versionRoot.'/index.html', $versionRoot.'/sortable/overview/index.html'),
            'copy' => 'Full reorderable table runtime.',
            'image' => 'assets/previews/sortable-overview.png',
        ],
        [
            'title' => 'Sortable Detail',
            'href' => relativePageUrl($versionRoot.'/index.html', $versionRoot.'/sortable/row-sorting/index.html'),
            'copy' => 'Closer look at drag and order affordances.',
            'image' => 'assets/previews/sortable-detail.png',
        ],
        [
            'title' => 'Core Overview',
            'href' => relativePageUrl($versionRoot.'/index.html', $versionRoot.'/core/actions/index.html'),
            'copy' => 'Stats and shared action primitives.',
            'image' => 'assets/previews/core-overview.png',
        ],
        [
            'title' => 'Core Modal',
            'href' => relativePageUrl($versionRoot.'/index.html', $versionRoot.'/core/modals/index.html'),
            'copy' => 'Modal surface from the core runtime.',
            'image' => 'assets/previews/core-modal.png',
        ],
    ],
    'stats' => [
        ['label' => 'Docs pages', 'value' => (string) count($pages)],
        ['label' => 'Preview states', 'value' => '8'],
        ['label' => 'Core sections', 'value' => '5'],
    ],
    'quickLinks' => [
        ['label' => 'Getting Started', 'href' => relativePageUrl($versionRoot.'/index.html', $versionRoot.'/getting-started/index.html')],
        ['label' => 'Documentation Index', 'href' => relativePageUrl($versionRoot.'/index.html', $versionRoot.'/documentation/index.html')],
        ['label' => 'Forms', 'href' => relativePageUrl($versionRoot.'/index.html', $versionRoot.'/forms/overview/index.html')],
        ['label' => 'Table', 'href' => relativePageUrl($versionRoot.'/index.html', $versionRoot.'/table/overview/index.html')],
    ],
]);

file_put_contents($versionRoot.'/index.html', $homeHtml);
file_put_contents(
    $versionRoot.'/search-index.json',
    json_encode($searchIndex, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
);

echo "Built docs site ({$activeVersionLabel}) in {$versionRoot}\n";

/**
 * @return array<int, array<string, string>>
 */
function pageManifest(string $root): array
{
    $pages = [];

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root.'/docs', FilesystemIterator::SKIP_DOTS)
    );

    /** @var SplFileInfo $file */
    foreach ($iterator as $file) {
        if (! $file->isFile() || strtolower($file->getExtension()) !== 'md') {
            continue;
        }

        $source = $file->getRealPath();
        if (! is_string($source)) {
            continue;
        }

        $markdown = file_get_contents($source);
        if ($markdown === false) {
            throw new RuntimeException('Unable to read markdown file: '.$source);
        }

        $sourceRelative = substr($source, strlen($root) + 1);
        $document = parseMarkdownDocument($markdown, $sourceRelative);
        $frontMatter = $document['frontMatter'];
        $section = normalizeSection($frontMatter['section'] ?? inferSectionFromSource($sourceRelative));
        $title = trim((string) (
            $frontMatter['nav_title']
            ?? $frontMatter['title']
            ?? extractFirstMarkdownHeading($document['markdown'])
            ?? guessTitleFromFilename($sourceRelative)
        ));

        $pages[] = [
            'source' => $source,
            'sourceRelative' => $sourceRelative,
            'section' => $section,
            'sectionWeight' => sectionSortWeight($section),
            'navTitle' => $title !== '' ? $title : guessTitleFromFilename($sourceRelative),
            'order' => resolvePageOrder($frontMatter['order'] ?? null),
            // Pages can opt out of the sidebar with `nav: false` in front matter;
            // they are still built and reachable (e.g. from an index page's table).
            'nav' => ! in_array($frontMatter['nav'] ?? true, [false, 'false', 'no', 0], true),
            'preview' => resolvePreviewKey($frontMatter['preview'] ?? null),
            'summary' => trim((string) ($frontMatter['summary'] ?? $frontMatter['excerpt'] ?? '')),
            'output' => outputPathForSource($sourceRelative),
            'markdown' => $document['markdown'],
        ];
    }

    usort($pages, static fn (array $left, array $right): int => [
        $left['sectionWeight'],
        $left['order'],
        strtolower($left['navTitle']),
        $left['sourceRelative'],
    ] <=> [
        $right['sectionWeight'],
        $right['order'],
        strtolower($right['navTitle']),
        $right['sourceRelative'],
    ]);

    return $pages;
}

/**
 * @return array{frontMatter:array<string, mixed>, markdown:string}
 */
function parseMarkdownDocument(string $markdown, string $sourceRelative): array
{
    if (preg_match('/\A---\R(.*?)\R---\R?/s', $markdown, $matches) !== 1) {
        return [
            'frontMatter' => [],
            'markdown' => $markdown,
        ];
    }

    try {
        $frontMatter = Yaml::parse($matches[1]);
    } catch (Throwable $exception) {
        throw new RuntimeException('Invalid front matter in '.$sourceRelative.': '.$exception->getMessage(), 0, $exception);
    }

    return [
        'frontMatter' => is_array($frontMatter) ? $frontMatter : [],
        'markdown' => substr($markdown, strlen($matches[0])),
    ];
}

function extractFirstMarkdownHeading(string $markdown): ?string
{
    if (preg_match('/^\s*#\s+(.+?)\s*$/m', $markdown, $matches) !== 1) {
        return null;
    }

    return trim((string) preg_replace('/[*_`]+/', '', $matches[1]));
}

function inferSectionFromSource(string $sourceRelative): string
{
    return match (true) {
        str_starts_with($sourceRelative, 'docs/forms/fields/') => 'Fields',
        str_starts_with($sourceRelative, 'docs/forms/') => 'Forms',
        str_starts_with($sourceRelative, 'docs/table/') => 'Table',
        str_starts_with($sourceRelative, 'docs/sortable/') => 'Sortable',
        str_starts_with($sourceRelative, 'docs/core/schema/') => 'Schema',
        str_starts_with($sourceRelative, 'docs/core/') => 'Core',
        str_starts_with($sourceRelative, 'docs/boost/') => 'Boost',
        str_starts_with($sourceRelative, 'docs/') => 'Start Here',
        default => 'Start Here',
    };
}

function normalizeSection(string $section): string
{
    $normalized = strtolower(trim($section));

    return match ($normalized) {
        'start here', 'start-here', 'start_here' => 'Start Here',
        'forms' => 'Forms',
        'fields' => 'Fields',
        'table' => 'Table',
        'sortable' => 'Sortable',
        'schema' => 'Schema',
        'core' => 'Core',
        'boost' => 'Boost',
        default => ucwords(str_replace(['-', '_'], ' ', $section)),
    };
}

function sectionSortWeight(string $section): int
{
    return match ($section) {
        'Start Here' => 10,
        'Forms' => 20,
        'Fields' => 30,
        'Table' => 40,
        'Schema' => 45,
        'Sortable' => 50,
        'Core' => 60,
        'Boost' => 70,
        default => 999,
    };
}

function resolvePageOrder(mixed $value): int
{
    if (is_int($value)) {
        return $value;
    }

    if (is_string($value) && is_numeric($value)) {
        return (int) $value;
    }

    return 9999;
}

function resolvePreviewKey(mixed $value): ?string
{
    if (! is_string($value)) {
        return null;
    }

    $value = strtolower(trim($value));

    return match ($value) {
        '', 'none', 'false', 'off' => null,
        default => $value,
    };
}

/**
 * Resolve the per-field runtime preview image for a field doc page, if one
 * was captured (assets/previews/field-<slug>.png).
 */
function fieldPreviewImage(string $sourceRelative, string $distRoot): ?string
{
    if (preg_match('#^docs/forms/fields/([a-z0-9-]+)\.md$#', $sourceRelative, $matches) !== 1) {
        return null;
    }

    $relative = 'assets/previews/field-'.$matches[1].'.png';

    return is_file($distRoot.'/'.$relative) ? $relative : null;
}

/**
 * @param  array<string, string>  $page
 * @param  array<string, array<string, string>>  $pageMap
 * @return array{title:string, excerpt:string, contentHtml:string, plainText:string, headings:array<int, array{level:int, text:string, id:string}>}
 */
function renderMarkdownPage(string $markdown, MarkdownConverter $converter, array $currentPage, array $pageMap, string $repoRoot): array
{
    $rendered = (string) $converter->convert($markdown);

    $doc = new DOMDocument('1.0', 'UTF-8');
    @$doc->loadHTML('<?xml encoding="utf-8" ?><div id="page-root">'.$rendered.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

    $xpath = new DOMXPath($doc);
    $root = $xpath->query('//*[@id="page-root"]')->item(0);

    if (! $root instanceof DOMElement) {
        throw new RuntimeException('Unable to parse rendered HTML for '.$currentPage['sourceRelative']);
    }

    $headings = [];
    $slugCounts = [];

    /** @var DOMElement $heading */
    foreach ($xpath->query('.//h1 | .//h2 | .//h3', $root) as $heading) {
        $text = trim($heading->textContent);
        $baseSlug = slugify($text);
        $count = $slugCounts[$baseSlug] ?? 0;
        $slugCounts[$baseSlug] = $count + 1;
        $id = $count === 0 ? $baseSlug : $baseSlug.'-'.($count + 1);
        $heading->setAttribute('id', $id);

        $headings[] = [
            'level' => (int) substr($heading->tagName, 1),
            'text' => $text,
            'id' => $id,
        ];
    }

    $title = $currentPage['navTitle'];
    $firstH1 = $xpath->query('./h1[1]', $root)->item(0);

    if ($firstH1 instanceof DOMElement) {
        $title = trim($firstH1->textContent) !== '' ? trim($firstH1->textContent) : $title;
        $firstH1->parentNode?->removeChild($firstH1);
    } elseif ($headings !== []) {
        $title = $headings[0]['text'];
    }

    /** @var DOMElement $link */
    foreach ($xpath->query('.//a[@href]', $root) as $link) {
        $href = $link->getAttribute('href');
        $rewritten = rewriteHref(
            href: $href,
            currentSource: $currentPage['source'],
            currentOutput: $currentPage['output'],
            pageMap: $pageMap,
            repoRoot: $repoRoot,
        );

        if ($rewritten !== null) {
            $link->setAttribute('href', $rewritten);
        }

        if (preg_match('/^https?:\/\//i', $href) === 1) {
            $link->setAttribute('target', '_blank');
            $link->setAttribute('rel', 'noreferrer');
        }
    }

    $excerpt = $currentPage['summary'];
    $firstParagraph = $xpath->query('./p[1]', $root)->item(0);

    if ($excerpt === '' && $firstParagraph instanceof DOMElement) {
        $excerpt = trim(preg_replace('/\s+/u', ' ', $firstParagraph->textContent) ?? '');
    }

    $contentHtml = innerHtml($root);
    $plainText = trim(preg_replace('/\s+/u', ' ', $root->textContent) ?? '');

    return [
        'title' => $title,
        'excerpt' => $excerpt,
        'contentHtml' => $contentHtml,
        'plainText' => $plainText,
        'headings' => array_values(array_filter(
            $headings,
            static fn (array $heading): bool => $heading['level'] >= 2 && $heading['level'] <= 3
        )),
    ];
}

/**
 * @param  array<int, array<string, string>>  $pages
 * @return array<int, array{title:string, items:array<int, array{title:string, href:string, active:bool}>}>
 */
function buildNavSections(array $pages, ?string $activeSource, string $distRoot): array
{
    $sections = [];
    $sectionOrder = [];
    $activeOutput = null;

    foreach ($pages as $page) {
        if ($page['source'] === $activeSource) {
            $activeOutput = $distRoot.'/'.$page['output'];
            break;
        }
    }

    $homeFile = $distRoot.'/index.html';
    $fromFile = $activeOutput ?? $homeFile;

    foreach ($pages as $page) {
        if (! ($page['nav'] ?? true)) {
            continue;
        }

        $sectionOrder[$page['section']] = true;
        $sections[$page['section']][] = [
            'title' => $page['navTitle'],
            'href' => relativePageUrl($fromFile, $distRoot.'/'.$page['output']),
            'active' => $page['source'] === $activeSource,
        ];
    }

    $ordered = [];
    foreach (array_keys($sectionOrder) as $title) {
        $ordered[] = [
            'title' => $title,
            'items' => $sections[$title],
        ];
    }

    return $ordered;
}

/**
 * Resolve the version switcher entries for a page in the version currently being
 * built. Cross-version home links are computed purely from path depth (no
 * filesystem lookups) so they are correct even before the other version's files
 * exist on disk. Each version links to its own home page (dist root for the ''
 * path, dist/<path>/ otherwise); unavailable versions render as disabled.
 *
 * @param  array<int, array<string, mixed>>  $versions
 * @param  string  $pageOutput  Output path of the current page relative to its version root, e.g. 'table/overview/index.html'.
 * @param  string  $activeSubdir  Sub-directory of the version being built ('' = dist root).
 * @param  string  $activeLabel  Label of the version being built.
 * @return array{current:string, items:array<int, array{label:string, badge:string, current:bool, href:?string, disabled:bool}>}
 */
function buildVersionMenu(array $versions, string $pageOutput, string $activeSubdir, string $activeLabel): array
{
    // Steps up from the current page to the dist root.
    $depthToVersionRoot = substr_count(trim($pageOutput, '/'), '/');
    $depthToDistRoot = $depthToVersionRoot + ($activeSubdir === '' ? 0 : 1);
    $up = str_repeat('../', $depthToDistRoot);

    $items = [];

    foreach ($versions as $version) {
        $label = (string) ($version['label'] ?? '');
        $path = trim((string) ($version['path'] ?? ''), '/');
        $available = (bool) ($version['available'] ?? false);
        $isCurrent = $label === $activeLabel;

        $href = null;
        if ($available || $isCurrent) {
            $target = $up.($path === '' ? '' : $path.'/');
            $href = $target === '' ? './' : $target;
        }

        $items[] = [
            'label' => $label,
            'badge' => (string) ($version['badge'] ?? ''),
            'current' => $isCurrent,
            'href' => $href,
            'disabled' => $href === null,
        ];
    }

    return ['current' => $activeLabel, 'items' => $items];
}

function outputPathForSource(string $sourceRelative): string
{
    if ($sourceRelative === 'README.md') {
        return 'project/readme/index.html';
    }

    if ($sourceRelative === 'docs/index.md') {
        return 'documentation/index.html';
    }

    if (preg_match('#^packages/([^/]+)/README\.md$#', $sourceRelative, $matches) === 1) {
        return 'packages/'.$matches[1].'/index.html';
    }

    if (preg_match('#^packages/table/docs/(.+)\.md$#', $sourceRelative, $matches) === 1) {
        return 'legacy-table/'.$matches[1].'/index.html';
    }

    if ($sourceRelative === 'architecture/README.md') {
        return 'architecture/index.html';
    }

    if (preg_match('#^docs/(.+)$#', $sourceRelative, $matches) === 1) {
        return outputPathFromRelativeMarkdown($matches[1]);
    }

    if (preg_match('#^architecture/(.+)$#', $sourceRelative, $matches) === 1) {
        return 'architecture/'.outputPathFromRelativeMarkdown($matches[1]);
    }

    throw new RuntimeException('No output mapping for source: '.$sourceRelative);
}

function outputPathFromRelativeMarkdown(string $relativePath): string
{
    $relativePath = str_replace('\\', '/', $relativePath);

    if (str_ends_with($relativePath, 'README.md')) {
        return dirname($relativePath).'/index.html';
    }

    if (str_ends_with($relativePath, 'index.md')) {
        $directory = dirname($relativePath);

        return $directory === '.' ? 'index.html' : trim($directory, '/').'/index.html';
    }

    return substr($relativePath, 0, -3).'/index.html';
}

/**
 * @param  array<string, array<string, string>>  $pageMap
 */
function rewriteHref(string $href, string $currentSource, string $currentOutput, array $pageMap, string $repoRoot): ?string
{
    if ($href === '' || str_starts_with($href, '#') || preg_match('/^(https?:|mailto:|tel:)/i', $href) === 1) {
        return null;
    }

    [$pathPart, $anchor] = explodeAnchor($href);
    $resolved = resolveSourcePath($repoRoot, dirname($currentSource), $pathPart);

    if ($resolved === null || ! isset($pageMap[$resolved])) {
        return null;
    }

    $targetOutput = dirname(dirname($currentSource)).'/'; // placeholder
    $targetOutputFile = dirname(__DIR__).'/docs-site/dist/'.$pageMap[$resolved]['output'];

    return relativePageUrl(dirname(__DIR__).'/docs-site/dist/'.$currentOutput, $targetOutputFile).$anchor;
}

function resolveSourcePath(string $repoRoot, string $currentDirectory, string $path): ?string
{
    $normalized = null;

    if (str_starts_with($path, '/')) {
        $normalized = realpath($repoRoot.'/'.ltrim($path, '/'));
    } else {
        $normalized = realpath($currentDirectory.'/'.$path);
    }

    if ($normalized && is_dir($normalized)) {
        $readme = realpath($normalized.'/README.md');

        return $readme ?: null;
    }

    if ($normalized && is_file($normalized)) {
        return $normalized;
    }

    $try = str_starts_with($path, '/')
        ? $repoRoot.'/'.ltrim($path, '/')
        : $currentDirectory.'/'.$path;

    if (is_dir($try) && is_file($try.'/README.md')) {
        return realpath($try.'/README.md') ?: null;
    }

    if (! str_ends_with($try, '.md') && is_file($try.'.md')) {
        return realpath($try.'.md') ?: null;
    }

    return null;
}

function relativePageUrl(string $fromFile, string $toFile): string
{
    $fromDir = dirname($fromFile);
    $toDir = dirname($toFile);
    $relative = relativePath($fromDir, $toDir);

    return ($relative === '' || $relative === '.')
        ? './'
        : rtrim($relative, '/').'/';
}

function relativeAssetPath(string $fromFile, string $toFile): string
{
    return relativePath(dirname($fromFile), $toFile);
}

function relativePath(string $from, string $to): string
{
    $from = str_replace('\\', '/', realpath($from) ?: $from);
    $to = str_replace('\\', '/', realpath($to) ?: $to);

    $fromParts = explode('/', trim($from, '/'));
    $toParts = explode('/', trim($to, '/'));

    while ($fromParts !== [] && $toParts !== [] && $fromParts[0] === $toParts[0]) {
        array_shift($fromParts);
        array_shift($toParts);
    }

    return implode('/', array_merge(array_fill(0, count($fromParts), '..'), $toParts)) ?: '.';
}

function slugify(string $value): string
{
    $value = strtolower($value);
    $value = preg_replace('/[^a-z0-9]+/u', '-', $value) ?? '';
    $value = trim($value, '-');

    return $value !== '' ? $value : 'section';
}

/**
 * @return array{0:string,1:string}
 */
function explodeAnchor(string $href): array
{
    $parts = explode('#', $href, 2);

    return [$parts[0], isset($parts[1]) ? '#'.$parts[1] : ''];
}

function innerHtml(DOMElement $element): string
{
    $html = '';

    foreach ($element->childNodes as $child) {
        $html .= $element->ownerDocument->saveHTML($child);
    }

    return $html;
}

function renderTemplate(string $template, array $variables): string
{
    extract($variables, EXTR_SKIP);
    ob_start();
    include $template;

    return (string) ob_get_clean();
}

/**
 * Recreate a directory but keep the named top-level entries (used to preserve
 * sibling version sub-directories when rebuilding the root version).
 *
 * @param  array<int, string>  $preserve
 */
function recreateDirectoryPreserving(string $directory, array $preserve): void
{
    ensureDirectory($directory);

    $entries = scandir($directory);

    if ($entries === false) {
        return;
    }

    foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..' || in_array($entry, $preserve, true)) {
            continue;
        }

        $path = $directory.'/'.$entry;

        if (is_dir($path) && ! is_link($path)) {
            deleteDirectory($path);
        } elseif (file_exists($path) || is_link($path)) {
            unlink($path);
        }
    }
}

function recreateDirectory(string $directory): void
{
    if (is_dir($directory)) {
        deleteDirectory($directory);
    }

    ensureDirectory($directory);
}

function ensureDirectory(string $directory): void
{
    if (! is_dir($directory) && ! mkdir($directory, 0777, true) && ! is_dir($directory)) {
        throw new RuntimeException('Unable to create directory: '.$directory);
    }
}

function deleteDirectory(string $directory): void
{
    if (! is_dir($directory)) {
        return;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    /** @var SplFileInfo $item */
    foreach ($iterator as $item) {
        $path = $item->getPathname();

        if ($item->isDir() && ! $item->isLink()) {
            if (is_dir($path)) {
                rmdir($path);
            }

            continue;
        }

        if (file_exists($path) || is_link($path)) {
            unlink($path);
        }
    }

    rmdir($directory);
}

function copyDirectory(string $source, string $destination): void
{
    if (! is_dir($source)) {
        return;
    }

    ensureDirectory($destination);
    $items = scandir($source);

    if ($items === false) {
        return;
    }

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }

        $from = $source.'/'.$item;
        $to = $destination.'/'.$item;

        if (is_dir($from)) {
            copyDirectory($from, $to);
        } else {
            ensureDirectory(dirname($to));
            copy($from, $to);
        }
    }
}

function guessTitleFromFilename(string $sourceRelative): string
{
    $basename = basename($sourceRelative, '.md');

    if (strtoupper($basename) === $basename) {
        return $basename;
    }

    return ucwords(str_replace(['-', '_'], ' ', $basename));
}
