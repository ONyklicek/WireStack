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

$githubUrl = 'https://github.com/ONyklicek/WireStack';
?>
<!DOCTYPE html>
<html lang="en" data-search-index="<?= htmlspecialchars($searchIndexUrl, ENT_QUOTES) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($page['title'].' | '.$siteTitle, ENT_QUOTES) ?></title>
    <meta name="description" content="<?= htmlspecialchars($page['excerpt'], ENT_QUOTES) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
    <link rel="stylesheet" href="<?= htmlspecialchars($cssUrl, ENT_QUOTES) ?>">
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
    <script defer src="<?= htmlspecialchars($jsUrl, ENT_QUOTES) ?>"></script>
</head>
<body class="docs-body">
    <div class="site-shell">
        <aside class="site-sidebar" data-sidebar>
            <div class="sidebar-header">
                <a class="brand-link" href="<?= htmlspecialchars($homeUrl, ENT_QUOTES) ?>">
                    <span class="brand-mark">W</span>
                    <span class="brand-name">WireStack<span class="brand-badge">Docs</span></span>
                </a>
                <button class="icon-button sidebar-close" type="button" data-nav-close aria-label="Close navigation" style="display:none">×</button>
            </div>

            <nav class="sidebar-nav">
                <?php foreach ($navSections as $section) { ?>
                    <section class="nav-section">
                        <h2><?= htmlspecialchars($section['title'], ENT_QUOTES) ?></h2>
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
            <header class="topbar">
                <button class="nav-toggle" type="button" data-nav-toggle aria-label="Open navigation">
                    <span></span><span></span><span></span>
                </button>

                <div class="search-shell" data-search-root>
                    <input
                        type="search"
                        class="search-input"
                        placeholder="Search documentation…"
                        autocomplete="off"
                        spellcheck="false"
                        data-search-input
                    >
                    <span class="search-kbd"><kbd>⌘</kbd><kbd>K</kbd></span>
                    <div class="search-results" data-search-results hidden></div>
                </div>

                <div class="topbar-actions">
                    <button class="icon-button theme-toggle" type="button" data-theme-toggle aria-label="Toggle theme">
                        <svg class="icon-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/></svg>
                        <svg class="icon-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/></svg>
                    </button>
                    <a class="icon-button" href="<?= htmlspecialchars($githubUrl, ENT_QUOTES) ?>" target="_blank" rel="noreferrer" aria-label="GitHub repository">
                        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 .5C5.73.5.5 5.73.5 12c0 5.08 3.29 9.39 7.86 10.91.58.11.79-.25.79-.56v-2c-3.2.7-3.88-1.54-3.88-1.54-.53-1.34-1.29-1.7-1.29-1.7-1.05-.72.08-.7.08-.7 1.16.08 1.77 1.19 1.77 1.19 1.03 1.77 2.71 1.26 3.37.96.1-.75.4-1.26.73-1.55-2.56-.29-5.26-1.28-5.26-5.69 0-1.26.45-2.28 1.19-3.09-.12-.29-.52-1.46.11-3.04 0 0 .97-.31 3.18 1.18a11.02 11.02 0 0 1 5.8 0c2.2-1.49 3.17-1.18 3.17-1.18.63 1.58.23 2.75.12 3.04.74.81 1.18 1.83 1.18 3.09 0 4.42-2.7 5.39-5.28 5.68.42.36.79 1.06.79 2.14v3.17c0 .31.21.68.8.56A11.52 11.52 0 0 0 23.5 12C23.5 5.73 18.27.5 12 .5Z"/></svg>
                    </a>
                </div>
            </header>

            <div class="page-body">
                <div class="content-shell">
                    <article class="docs-article">
                        <div class="page-hero<?= $page['previewUrl'] ? ' has-preview' : '' ?>">
                            <nav class="breadcrumb" aria-label="Breadcrumb">
                                <a href="<?= htmlspecialchars($homeUrl, ENT_QUOTES) ?>">Docs</a>
                                <span class="sep">/</span>
                                <span class="current"><?= htmlspecialchars($page['section'], ENT_QUOTES) ?></span>
                            </nav>
                            <p class="eyebrow"><?= htmlspecialchars($page['section'], ENT_QUOTES) ?></p>
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

                        <?= $page['contentHtml'] ?>

                        <?php if ($prevPage || $nextPage) { ?>
                            <nav class="page-nav" aria-label="Pagination">
                                <?php if ($prevPage) { ?>
                                    <a class="page-nav-link is-prev" href="<?= htmlspecialchars($prevPage['href'], ENT_QUOTES) ?>">
                                        <span>← Previous</span>
                                        <strong><?= htmlspecialchars($prevPage['title'], ENT_QUOTES) ?></strong>
                                    </a>
                                <?php } else { ?><span></span><?php } ?>

                                <?php if ($nextPage) { ?>
                                    <a class="page-nav-link is-next" href="<?= htmlspecialchars($nextPage['href'], ENT_QUOTES) ?>">
                                        <span>Next →</span>
                                        <strong><?= htmlspecialchars($nextPage['title'], ENT_QUOTES) ?></strong>
                                    </a>
                                <?php } else { ?><span></span><?php } ?>
                            </nav>
                        <?php } ?>
                    </article>

                    <?php if ($page['headings'] !== []) { ?>
                        <aside class="page-toc">
                            <p class="toc-title">On this page</p>
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
        </main>
    </div>
</body>
</html>
