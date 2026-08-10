<?php
// Flatten the navigation to derive previous / next links.
$flatNav = [];
foreach ($navSections as $section) {
    foreach ($section['items'] as $item) {
        $flatNav[] = $item + ['section' => $section['title']];
    }
}
$activeIndex = null;
foreach ($flatNav as $i => $item) {
    if (! empty($item['active'])) {
        $activeIndex = $i;
        break;
    }
}
$prevPage = ($activeIndex !== null && isset($flatNav[$activeIndex - 1])) ? $flatNav[$activeIndex - 1] : null;
$nextPage = ($activeIndex !== null && isset($flatNav[$activeIndex + 1])) ? $flatNav[$activeIndex + 1] : null;

?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($htmlLang ?? 'en', ENT_QUOTES) ?>" data-search-index="<?= htmlspecialchars($searchIndexUrl, ENT_QUOTES) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($page['title'].' | '.$siteTitle, ENT_QUOTES) ?></title>
    <meta name="description" content="<?= htmlspecialchars($page['excerpt'], ENT_QUOTES) ?>">
<?php include __DIR__.'/partials/head-meta.php'; ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
    <link rel="stylesheet" href="<?= htmlspecialchars($cssUrl, ENT_QUOTES) ?>">
    <script>
        // Remember the locale the visitor is actually reading — explicit
        // navigation always wins and refreshes the stored preference.
        (function () {
            try {
                var s = <?= $localeState ?? '{}' ?>;
                if (s.storageKey) {
                    localStorage.setItem(s.storageKey, JSON.stringify({ code: s.current, ts: Date.now() }));
                }
            } catch (e) {}
        })();
    </script>
    <script>
        (function () {
            try {
                var stored = localStorage.getItem('wire-docs-theme');
                var theme = stored || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
                document.documentElement.setAttribute('data-theme', theme);
            } catch (e) {
                document.documentElement.setAttribute('data-theme', 'light');
            }
        })();
    </script>
    <script>
        // Opt into entrance/scroll motion unless the visitor prefers less of it.
        try {
            if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                document.documentElement.classList.add('has-motion');
            }
        } catch (e) {}
    </script>
    <script defer src="<?= htmlspecialchars(str_replace('site.js', 'gsap.min.js', $jsUrl), ENT_QUOTES) ?>"></script>
    <script defer src="<?= htmlspecialchars(str_replace('site.js', 'scrolltrigger.min.js', $jsUrl), ENT_QUOTES) ?>"></script>
    <script defer src="<?= htmlspecialchars($jsUrl, ENT_QUOTES) ?>"></script>
</head>
<?php // Chrome strings the scripts render at runtime (copy buttons, search states).?>
<body
    class="docs-body"
    data-copy-label="<?= htmlspecialchars($t('Copy'), ENT_QUOTES) ?>"
    data-copied-label="<?= htmlspecialchars($t('Copied'), ENT_QUOTES) ?>"
    data-copy-aria-label="<?= htmlspecialchars($t('Copy code'), ENT_QUOTES) ?>"
>
    <div class="site-shell">
        <aside class="site-sidebar" data-sidebar>
            <div class="sidebar-header">
                <a class="brand-link" href="<?= htmlspecialchars($homeUrl, ENT_QUOTES) ?>">
                    <span class="brand-mark">W</span>
                    <span class="brand-name">WireStack</span>
                </a>
                <button class="sidebar-close" type="button" data-nav-close aria-label="<?= htmlspecialchars($t('Close navigation'), ENT_QUOTES) ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
                </button>
            </div>

            <div class="sidebar-tools">
                <?php include __DIR__.'/partials/locale-switcher.php'; ?>
                <?php include __DIR__.'/partials/version-switcher.php'; ?>
            </div>

            <nav class="sidebar-nav">
                <?php foreach ($navSections as $section) { ?>
                    <section class="nav-section">
                        <h2><?= htmlspecialchars($t($section['title']), ENT_QUOTES) ?></h2>
                        <ul>
                            <?php foreach ($section['items'] as $item) { ?>
                                <li>
                                    <a class="<?= $item['active'] ? 'is-active' : '' ?>" href="<?= htmlspecialchars($item['href'], ENT_QUOTES) ?>">
                                        <?= htmlspecialchars($item['title'], ENT_QUOTES) ?>
                                    </a>
                                </li>
                            <?php } ?>
                        </ul>
                    </section>
                <?php } ?>
            </nav>
        </aside>

        <div class="site-overlay" data-nav-close></div>

        <main class="site-main">
            <div class="reading-progress" aria-hidden="true"><span data-reading-progress></span></div>
            <header class="topbar">
                <button class="nav-toggle" type="button" data-nav-toggle aria-label="<?= htmlspecialchars($t('Open navigation'), ENT_QUOTES) ?>">
                    <span></span><span></span><span></span>
                </button>

                <div class="search-shell" data-search-root>
                    <input
                        type="search"
                        class="search-input"
                        placeholder="<?= htmlspecialchars($t('Search documentation…'), ENT_QUOTES) ?>"
                        autocomplete="off"
                        spellcheck="false"
                        data-search-input
                    >
                    <span class="search-kbd"><kbd>⌘</kbd><kbd>K</kbd></span>
                    <button class="search-cancel" type="button" data-search-close><?= htmlspecialchars($t('Cancel'), ENT_QUOTES) ?></button>
                    <div
                        class="search-results"
                        data-search-results
                        data-empty-title="<?= htmlspecialchars($t('No matches'), ENT_QUOTES) ?>"
                        data-empty-hint="<?= htmlspecialchars($t('Try a package name, field type, or API term.'), ENT_QUOTES) ?>"
                        hidden
                    ></div>
                </div>

                <div class="topbar-actions">
                    <button class="icon-button search-trigger" type="button" data-search-open aria-label="<?= htmlspecialchars($t('Search documentation'), ENT_QUOTES) ?>">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                    </button>
                    <button class="icon-button theme-toggle" type="button" data-theme-toggle aria-label="<?= htmlspecialchars($t('Toggle theme'), ENT_QUOTES) ?>">
                        <svg class="icon-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/></svg>
                        <svg class="icon-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/></svg>
                    </button>
                </div>
            </header>
            <div class="search-backdrop" data-search-close></div>

            <div class="page-body">
                <div class="content-shell">
                    <article class="docs-article">
                        <?php if (($htmlLang ?? 'en') !== 'en' && empty($page['translated'])) { ?>
                            <div class="translation-notice" role="note">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 8h.01M11 12h1v4h1"/></svg>
                                <span><?= htmlspecialchars($t("This page hasn't been translated yet — showing the English version."), ENT_QUOTES) ?></span>
                            </div>
                        <?php } ?>
                        <div class="page-hero<?= $page['previewUrl'] ? ' has-preview' : '' ?>">
                            <nav class="breadcrumb" aria-label="<?= htmlspecialchars($t('Breadcrumb'), ENT_QUOTES) ?>">
                                <a href="<?= htmlspecialchars($homeUrl, ENT_QUOTES) ?>"><?= htmlspecialchars($t('Docs'), ENT_QUOTES) ?></a>
                                <span class="sep">/</span>
                                <span class="current"><?= htmlspecialchars($t($page['section']), ENT_QUOTES) ?></span>
                            </nav>
                            <p class="eyebrow"><?= htmlspecialchars($t($page['section']), ENT_QUOTES) ?></p>
                            <h1><?= htmlspecialchars($page['title'], ENT_QUOTES) ?></h1>
                            <?php if ($page['excerpt'] !== '') { ?>
                                <p class="lead"><?= htmlspecialchars($page['excerpt'], ENT_QUOTES) ?></p>
                            <?php } ?>

                            <?php if ($page['previewUrl']) { ?>
                                <figure class="hero-preview">
                                    <img src="<?= htmlspecialchars($page['previewUrl'], ENT_QUOTES) ?>" alt="<?= htmlspecialchars($page['title'].' preview', ENT_QUOTES) ?>" loading="lazy">
                                </figure>
                            <?php } ?>

                            <?php $galleryItems = array_slice($page['previewItems'] ?? [], 1); ?>
                            <?php if ($galleryItems !== []) { ?>
                                <div class="preview-gallery">
                                    <?php foreach ($galleryItems as $preview) { ?>
                                        <article class="preview-card">
                                            <figure class="preview-card-image">
                                                <img src="<?= htmlspecialchars($preview['image'], ENT_QUOTES) ?>" alt="<?= htmlspecialchars($preview['title'], ENT_QUOTES) ?>" loading="lazy">
                                            </figure>
                                            <div class="preview-card-copy">
                                                <strong><?= htmlspecialchars($preview['title'], ENT_QUOTES) ?></strong>
                                                <p><?= htmlspecialchars($preview['caption'], ENT_QUOTES) ?></p>
                                            </div>
                                        </article>
                                    <?php } ?>
                                </div>
                            <?php } ?>
                        </div>

                        <?php if ($page['headings'] !== []) { ?>
                            <details class="mobile-toc">
                                <summary>
                                    <span class="mobile-toc-label"><?= htmlspecialchars($t('On this page'), ENT_QUOTES) ?></span>
                                    <svg class="mobile-toc-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
                                </summary>
                                <ul>
                                    <?php foreach ($page['headings'] as $heading) { ?>
                                        <li class="level-<?= (int) $heading['level'] ?>">
                                            <a href="#<?= htmlspecialchars($heading['id'], ENT_QUOTES) ?>"><?= htmlspecialchars($heading['text'], ENT_QUOTES) ?></a>
                                        </li>
                                    <?php } ?>
                                </ul>
                            </details>
                        <?php } ?>

                        <?= $page['contentHtml'] ?>

                        <?php if ($prevPage || $nextPage) { ?>
                            <nav class="page-nav" aria-label="<?= htmlspecialchars($t('Pagination'), ENT_QUOTES) ?>">
                                <?php if ($prevPage) { ?>
                                    <a class="page-nav-link is-prev" href="<?= htmlspecialchars($prevPage['href'], ENT_QUOTES) ?>">
                                        <span><?= htmlspecialchars($t('← Previous'), ENT_QUOTES) ?></span>
                                        <strong><?= htmlspecialchars($prevPage['title'], ENT_QUOTES) ?></strong>
                                    </a>
                                <?php } else { ?><span></span><?php } ?>

                                <?php if ($nextPage) { ?>
                                    <a class="page-nav-link is-next" href="<?= htmlspecialchars($nextPage['href'], ENT_QUOTES) ?>">
                                        <span><?= htmlspecialchars($t('Next →'), ENT_QUOTES) ?></span>
                                        <strong><?= htmlspecialchars($nextPage['title'], ENT_QUOTES) ?></strong>
                                    </a>
                                <?php } else { ?><span></span><?php } ?>
                            </nav>
                        <?php } ?>
                    </article>

                    <?php if ($page['headings'] !== []) { ?>
                        <aside class="page-toc">
                            <p class="toc-title"><?= htmlspecialchars($t('On this page'), ENT_QUOTES) ?></p>
                            <ul>
                                <?php foreach ($page['headings'] as $heading) { ?>
                                    <li class="level-<?= (int) $heading['level'] ?>">
                                        <a href="#<?= htmlspecialchars($heading['id'], ENT_QUOTES) ?>">
                                            <?= htmlspecialchars($heading['text'], ENT_QUOTES) ?>
                                        </a>
                                    </li>
                                <?php } ?>
                            </ul>
                        </aside>
                    <?php } ?>
                </div>
            </div>

            <button class="back-to-top" type="button" data-back-to-top aria-label="<?= htmlspecialchars($t('Back to top'), ENT_QUOTES) ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="m18 15-6-6-6 6"/></svg>
            </button>
        </main>
    </div>
</body>
</html>
