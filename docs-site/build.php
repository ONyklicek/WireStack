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
// The deploy job points every per-branch, per-locale build at one shared output
// tree (DOCS_DIST_DIR) so the whole matrix assembles into a single dist/ without
// any cell clobbering another; local builds default to docs-site/dist.
$distRoot = rtrim((string) (getenv('DOCS_DIST_DIR') ?: $siteRoot.'/dist'), '/');

// Canonical matrix of locales x versions. The deploy job feeds ONE copy of this
// file to every branch build via DOCS_VERSIONS_FILE so the switchers can never
// drift between the 1.x and 2.x branches; the values baked into config.json are
// the local-dev fallback.
//
// Each run builds exactly one (version, locale) cell — selected with
// DOCS_BUILD_VERSION / DOCS_BUILD_LOCALE — into its own output root:
//
//   dist/            v1.x, default locale (EN)
//   dist/cs/         v1.x, cs
//   dist/v2/         v2.x, default locale
//   dist/v2/cs/      v2.x, cs
//
// so the deploy job iterates the matrix, assembling every cell into a single
// dist/ tree without any cell clobbering another (see recreate/preserve below).
[$siteVersions, $locales] = loadSiteConfig($siteRoot);

// - `path`:      output sub-directory ('' = the version/dist root). Also the URL
//                segment the switcher links to.
// - `available`: whether the version has been (or will be) built. Unavailable
//                versions render as a disabled "coming soon" entry.
//
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
$versionDir = $outputSubdir === '' ? $distRoot : $distRoot.'/'.$outputSubdir;

// Resolve which locale this run builds. The default locale owns the version root
// (no URL segment); every other locale lives in its own sub-directory.
$activeLocaleCode = (string) (getenv('DOCS_BUILD_LOCALE') ?: '');
$activeLocale = null;

foreach ($locales as $locale) {
    if ($activeLocaleCode !== '' && (string) ($locale['code'] ?? '') === $activeLocaleCode) {
        $activeLocale = $locale;
        break;
    }
}

if ($activeLocale === null) {
    foreach ($locales as $locale) {
        if (! empty($locale['default'])) {
            $activeLocale = $locale;
            break;
        }
    }
}

$activeLocale ??= $locales[0];
$activeLocaleCode = (string) ($activeLocale['code'] ?? 'en');
$activeLocaleLabel = (string) ($activeLocale['label'] ?? $activeLocaleCode);
$localeSubdir = trim((string) ($activeLocale['path'] ?? ''), '/');
$localeIsDefault = $localeSubdir === '';
$defaultLocaleCode = (string) ($locales[0]['code'] ?? 'en');
foreach ($locales as $locale) {
    if (! empty($locale['default'])) {
        $defaultLocaleCode = (string) ($locale['code'] ?? $defaultLocaleCode);
        break;
    }
}

// The write root for this run: version dir, then locale sub-dir for non-default
// locales. Every output path below is resolved against $versionRoot, so pointing
// it at the locale root is all that is needed to relocate a whole language.
$versionRoot = $localeIsDefault ? $versionDir : $versionDir.'/'.$localeSubdir;

// Non-default locales own a docs/<code>/ overlay tree and a <version>/<path>/
// output sub-directory; collect both so the manifest can skip overlay sources
// and the recreate step can preserve sibling-locale output.
$localeSubdirs = [];
$overlayLocaleCodes = [];
foreach ($locales as $locale) {
    $path = trim((string) ($locale['path'] ?? ''), '/');
    if ($path !== '') {
        $localeSubdirs[] = $path;
        $overlayLocaleCodes[] = (string) ($locale['code'] ?? '');
    }
}

// Runtime locale state shared by every page: the client remembers the visitor's
// choice (see templates) and, on the version-root landing only, redirects to it.
$localePaths = [];
foreach ($locales as $locale) {
    $localePaths[(string) ($locale['code'] ?? '')] = trim((string) ($locale['path'] ?? ''), '/');
}
$localeState = (string) json_encode([
    'storageKey' => 'wire-docs-locale',
    'maxAgeDays' => 90,
    'current' => $activeLocaleCode,
    'default' => $defaultLocaleCode,
    'paths' => $localePaths,
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

// UI-chrome translator: maps an English source string to the active locale, or
// returns it unchanged (default locale, or no translation yet). See lang.php.
$localeStrings = (array) ((require $siteRoot.'/lang.php')[$activeLocaleCode] ?? []);
$t = static fn (string $string): string => $localeStrings[$string] ?? $string;

$pages = pageManifest($root, $overlayLocaleCodes, $localeIsDefault ? '' : $activeLocaleCode);
$pageMap = [];

foreach ($pages as $page) {
    $pageMap[$page['source']] = $page;
}

// Recreate only the (version, locale) cell this run owns, preserving every
// sibling cell so the deploy job can assemble the matrix incrementally:
//   - a non-default locale is a leaf directory -> wiped clean, no siblings inside;
//   - the default locale owns the version root -> keep the locale sub-dirs;
//   - the default version root is also the dist root -> keep sibling versions too.
$preserveSubdirs = [];
if ($localeIsDefault) {
    $preserveSubdirs = $localeSubdirs;
    if ($outputSubdir === '') {
        foreach ($siteVersions as $version) {
            $path = trim((string) ($version['path'] ?? ''), '/');
            if ($path !== '' && $path !== $outputSubdir) {
                $preserveSubdirs[] = $path;
            }
        }
    }
}

ensureDirectory($distRoot);
recreateDirectoryPreserving($versionRoot, $preserveSubdirs);

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
    'palette' => ['title' => 'Full Color Palette', 'caption' => 'Every Tailwind hue rendered through the canonical HasColor resolvers across solid, soft, badge, button, and text surfaces.'],
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
    'docs/core/foundation.md' => ['palette'],
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
                : $t('Rendered live through the Wire Forms runtime.'),
        ];

        // Optional additional variant previews for the same field doc page.
        foreach ($fieldExtraPreviews[$page['sourceRelative']] ?? [] as $extra) {
            $image = 'assets/previews/'.$extra['slug'].'.png';

            if (! is_file($versionRoot.'/'.$image)) {
                continue;
            }

            $previewItems[] = [
                'image' => relativeAssetPath($currentFile, $versionRoot.'/'.$image),
                'title' => $t($extra['title']),
                'caption' => $t($extra['caption']),
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
                'title' => $t($previewMeta[$slug]['title']),
                'caption' => $t($previewMeta[$slug]['caption']),
            ];
        }
    }

    $previewUrl = $previewItems[0]['image'] ?? null;

    $html = renderTemplate($siteRoot.'/templates/page.php', [
        'siteTitle' => 'Wire Docs',
        't' => $t,
        'page' => array_merge($page, $content, ['previewUrl' => $previewUrl, 'previewItems' => $previewItems]),
        'navSections' => buildNavSections($pages, $page['source'], $versionRoot),
        'versionMenu' => buildVersionMenu($siteVersions, $page['output'], $outputSubdir, $localeSubdir, $activeVersionLabel),
        'localeMenu' => buildLocaleMenu($locales, $page['output'], $localeSubdir, $activeLocaleCode),
        'htmlLang' => $activeLocaleCode,
        'localeState' => $localeState,
        'isVersionHome' => false,
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
    't' => $t,
    'navSections' => buildNavSections($pages, null, $versionRoot),
    'versionMenu' => buildVersionMenu($siteVersions, 'index.html', $outputSubdir, $localeSubdir, $activeVersionLabel),
    'localeMenu' => buildLocaleMenu($locales, 'index.html', $localeSubdir, $activeLocaleCode),
    'htmlLang' => $activeLocaleCode,
    'localeState' => $localeState,
    'isVersionHome' => true,
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

echo "Built docs site ({$activeVersionLabel} · {$activeLocaleLabel}) in {$versionRoot}\n";

/**
 * Load the canonical locale x version matrix. DOCS_VERSIONS_FILE (set by the
 * deploy job) wins so every branch build shares one source of truth; otherwise
 * docs-site/config.json is used. Both fall back to sane defaults if unreadable.
 *
 * @return array{0:array<int, array<string, mixed>>, 1:array<int, array<string, mixed>>}
 */
function loadSiteConfig(string $siteRoot): array
{
    $defaults = [
        'versions' => [
            ['label' => 'v1.x', 'badge' => 'Latest', 'path' => '', 'branch' => '1.x', 'available' => true],
            ['label' => 'v2.x', 'badge' => 'Soon', 'path' => 'v2', 'branch' => '2.x', 'available' => false],
        ],
        'locales' => [
            ['code' => 'en', 'label' => 'English', 'path' => '', 'default' => true],
        ],
    ];

    $configPath = (string) (getenv('DOCS_VERSIONS_FILE') ?: $siteRoot.'/config.json');
    $config = [];

    if (is_file($configPath)) {
        $decoded = json_decode((string) file_get_contents($configPath), true);
        if (is_array($decoded)) {
            $config = $decoded;
        }
    }

    $versions = (isset($config['versions']) && is_array($config['versions']) && $config['versions'] !== [])
        ? array_values($config['versions'])
        : $defaults['versions'];
    $locales = (isset($config['locales']) && is_array($config['locales']) && $config['locales'] !== [])
        ? array_values($config['locales'])
        : $defaults['locales'];

    return [$versions, $locales];
}

/**
 * Build the page manifest for one locale. Pages are always enumerated from the
 * canonical (default-locale) docs/ tree, so identity — source key, output path,
 * navigation — is locale-independent; only the markdown body is swapped for a
 * translation when docs/<locale>/<same-path> exists. Missing translations fall
 * back to the canonical body and are flagged `translated => false`.
 *
 * @param  array<int, string>  $overlayLocaleCodes  Codes whose docs/<code>/ trees are translation overlays (excluded from enumeration).
 * @param  string  $localeCode  Locale being built ('' = default/canonical).
 * @return array<int, array<string, mixed>>
 */
function pageManifest(string $root, array $overlayLocaleCodes, string $localeCode): array
{
    $pages = [];
    $isDefault = $localeCode === '';

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

        $sourceRelative = str_replace('\\', '/', substr($source, strlen($root) + 1));

        // Skip translation overlays — they are pulled in as bodies for their own
        // locale below, never enumerated as canonical pages of their own.
        foreach ($overlayLocaleCodes as $code) {
            if ($code !== '' && str_starts_with($sourceRelative, 'docs/'.$code.'/')) {
                continue 2;
            }
        }

        // Read the translated body when present, else fall back to canonical.
        $readFrom = $source;
        $translated = true;
        if (! $isDefault) {
            $overlay = $root.'/docs/'.$localeCode.'/'.substr($sourceRelative, strlen('docs/'));
            if (is_file($overlay)) {
                $readFrom = $overlay;
            } else {
                $translated = false;
            }
        }

        $markdown = file_get_contents($readFrom);
        if ($markdown === false) {
            throw new RuntimeException('Unable to read markdown file: '.$readFrom);
        }

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
            'translated' => $translated,
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
 * @param  string  $pageOutput  Output path of the current page relative to its locale root, e.g. 'table/overview/index.html'.
 * @param  string  $activeVersionSubdir  Sub-directory of the version being built ('' = dist root).
 * @param  string  $activeLocaleSubdir  Sub-directory of the locale being built ('' = version root).
 * @param  string  $activeLabel  Label of the version being built.
 * @return array{current:string, items:array<int, array{label:string, badge:string, current:bool, href:?string, disabled:bool}>}
 */
function buildVersionMenu(array $versions, string $pageOutput, string $activeVersionSubdir, string $activeLocaleSubdir, string $activeLabel): array
{
    // Steps up from the current page to the dist root, then back down into the
    // target version while keeping the current locale segment.
    $depthToVersionRoot = substr_count(trim($pageOutput, '/'), '/') + ($activeLocaleSubdir === '' ? 0 : 1);
    $depthToDistRoot = $depthToVersionRoot + ($activeVersionSubdir === '' ? 0 : 1);
    $up = str_repeat('../', $depthToDistRoot);
    $localePrefix = $activeLocaleSubdir === '' ? '' : $activeLocaleSubdir.'/';

    $items = [];

    foreach ($versions as $version) {
        $label = (string) ($version['label'] ?? '');
        $path = trim((string) ($version['path'] ?? ''), '/');
        $available = (bool) ($version['available'] ?? false);
        $isCurrent = $label === $activeLabel;

        $href = null;
        if ($available || $isCurrent) {
            $target = $up.($path === '' ? '' : $path.'/').$localePrefix;
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

/**
 * Resolve the language switcher entries for the page being built. Because every
 * locale mirrors the same page structure, a switch links to the *same* page in
 * the sibling locale (up to the version root, then down the sibling's segment).
 *
 * @param  array<int, array<string, mixed>>  $locales
 * @param  string  $pageOutput  Output path of the current page relative to its locale root.
 * @param  string  $activeLocaleSubdir  Sub-directory of the locale being built ('' = version root).
 * @param  string  $activeCode  Code of the locale being built.
 * @return array{current:string, items:array<int, array{label:string, code:string, current:bool, href:string, disabled:bool}>}
 */
function buildLocaleMenu(array $locales, string $pageOutput, string $activeLocaleSubdir, string $activeCode): array
{
    if (count($locales) < 2) {
        return ['current' => '', 'items' => []];
    }

    $pageOutput = trim($pageOutput, '/');
    $pageDir = dirname($pageOutput);
    $pageUrlRel = ($pageDir === '.' || $pageDir === '') ? '' : $pageDir.'/';
    $depthToVersionRoot = substr_count($pageOutput, '/') + ($activeLocaleSubdir === '' ? 0 : 1);
    $up = str_repeat('../', $depthToVersionRoot);

    $currentLabel = '';
    $items = [];

    foreach ($locales as $locale) {
        $code = (string) ($locale['code'] ?? '');
        $label = (string) ($locale['label'] ?? $code);
        $path = trim((string) ($locale['path'] ?? ''), '/');
        $isCurrent = $code === $activeCode;

        if ($isCurrent) {
            $currentLabel = $label;
        }

        $target = $up.($path === '' ? '' : $path.'/').$pageUrlRel;

        $items[] = [
            'label' => $label,
            'code' => $code,
            'current' => $isCurrent,
            'href' => $target === '' ? './' : $target,
            'disabled' => false,
        ];
    }

    return ['current' => $currentLabel, 'items' => $items];
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
