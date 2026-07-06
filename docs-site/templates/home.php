<!DOCTYPE html>
<html lang="<?= htmlspecialchars($htmlLang ?? 'en', ENT_QUOTES) ?>" data-search-index="<?= htmlspecialchars($searchIndexUrl, ENT_QUOTES) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($siteTitle, ENT_QUOTES) ?> — Forms, Tables & Sorting for Livewire</title>
    <meta name="description" content="Complete static documentation for the Wire ecosystem: forms, tables, sortable, and core runtime, with real runtime preview screenshots.">
    <script>
        // Landing page only: if the visitor previously chose a non-default
        // language (and the choice is still fresh), send them to it. Explicit
        // navigation elsewhere always wins; deep pages never auto-redirect.
        (function () {
            try {
                var s = <?= $localeState ?? '{}' ?>;
                if (!s.storageKey) return;
                var raw = localStorage.getItem(s.storageKey);
                var pref = raw ? JSON.parse(raw) : null;
                if (s.current === s.default && pref && pref.code && pref.code !== s.default) {
                    var fresh = pref.ts && (Date.now() - pref.ts) < s.maxAgeDays * 864e5;
                    var path = s.paths ? s.paths[pref.code] : '';
                    if (fresh && path) {
                        location.replace('./' + path + '/');
                        return;
                    }
                }
                localStorage.setItem(s.storageKey, JSON.stringify({ code: s.current, ts: Date.now() }));
            } catch (e) {}
        })();
    </script>
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
                <a class="brand-link" href="./">
                    <span class="brand-mark">W</span>
                    <span class="brand-name">WireStack</span>
                </a>
                <div class="sidebar-header-actions">
                    <?php include __DIR__.'/partials/locale-switcher.php'; ?>
                    <?php include __DIR__.'/partials/version-switcher.php'; ?>
                    <button class="sidebar-close" type="button" data-nav-close aria-label="Close navigation">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
                    </button>
                </div>
            </div>

            <nav class="sidebar-nav">
                <?php foreach ($navSections as $section) { ?>
                    <section class="nav-section">
                        <h2><?= htmlspecialchars($section['title'], ENT_QUOTES) ?></h2>
                        <ul>
                            <?php foreach ($section['items'] as $item) { ?>
                                <li>
                                    <a href="<?= htmlspecialchars($item['href'], ENT_QUOTES) ?>">
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

        <main class="site-main home-main">
            <div class="reading-progress" aria-hidden="true"><span data-reading-progress></span></div>
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
                    <button class="search-cancel" type="button" data-search-close>Cancel</button>
                    <div class="search-results" data-search-results hidden></div>
                </div>

                <div class="topbar-actions">
                    <button class="icon-button search-trigger" type="button" data-search-open aria-label="Search documentation">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                    </button>
                    <button class="icon-button theme-toggle" type="button" data-theme-toggle aria-label="Toggle theme">
                        <svg class="icon-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/></svg>
                        <svg class="icon-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/></svg>
                    </button>
                </div>
            </header>
            <div class="search-backdrop" data-search-close></div>

            <section class="home-hero">
                <div class="home-hero-inner">
                    <span class="hero-badge"><span class="dot"></span> The WireStack ecosystem for Livewire</span>
                    <h1>Build <span class="grad">forms, tables &amp; sortable</span> UIs at lightning speed.</h1>
                    <p class="lead">
                        Production-ready forms, searchable tables, drag-and-drop sorting, and a shared core runtime —
                        documented with real screenshots captured straight from the package previews.
                    </p>
                    <div class="hero-actions">
                        <?php foreach ($quickLinks as $i => $link) { ?>
                            <a class="btn <?= $i === 0 ? 'btn-primary' : 'btn-ghost' ?>" href="<?= htmlspecialchars($link['href'], ENT_QUOTES) ?>">
                                <?= htmlspecialchars($link['label'], ENT_QUOTES) ?>
                                <?php if ($i === 0) { ?> →<?php } ?>
                            </a>
                            <?php if ($i >= 1) {
                                break;
                            } ?>
                        <?php } ?>
                    </div>
                </div>
            </section>

            <section class="home-section">
                <div class="section-intro">
                    <p class="eyebrow">Packages</p>
                    <h2>Four packages, one cohesive runtime.</h2>
                    <p>Compose them together or use them on their own — each builds on the shared core.</p>
                </div>

                <div class="feature-grid">
                    <?php foreach ($cards as $card) { ?>
                        <article class="feature-card">
                            <a href="<?= htmlspecialchars($card['href'], ENT_QUOTES) ?>">
                                <div class="feature-image">
                                    <img src="<?= htmlspecialchars($card['image'], ENT_QUOTES) ?>" alt="<?= htmlspecialchars($card['title'].' preview', ENT_QUOTES) ?>" loading="lazy">
                                </div>
                                <div class="feature-copy">
                                    <h3><?= htmlspecialchars($card['title'], ENT_QUOTES) ?> <span class="arrow">→</span></h3>
                                    <p><?= htmlspecialchars($card['copy'], ENT_QUOTES) ?></p>
                                </div>
                            </a>
                        </article>
                    <?php } ?>
                </div>
            </section>

            <section class="home-section">
                <div class="stats-row">
                    <?php foreach ($stats as $stat) { ?>
                        <article class="stat-card">
                            <strong><?= htmlspecialchars($stat['value'], ENT_QUOTES) ?></strong>
                            <span><?= htmlspecialchars($stat['label'], ENT_QUOTES) ?></span>
                        </article>
                    <?php } ?>
                </div>
            </section>

            <section class="home-section">
                <div class="section-intro">
                    <p class="eyebrow">Real previews</p>
                    <h2>Captured from the runtime, not mocked up.</h2>
                    <p>Every screenshot is rendered by the actual packages, so the docs match what you ship.</p>
                </div>

                <div class="preview-grid">
                    <?php foreach ($galleryCards as $card) { ?>
                        <article class="preview-card">
                            <a href="<?= htmlspecialchars($card['href'], ENT_QUOTES) ?>">
                                <figure class="preview-card-image">
                                    <img src="<?= htmlspecialchars($card['image'], ENT_QUOTES) ?>" alt="<?= htmlspecialchars($card['title'].' preview', ENT_QUOTES) ?>" loading="lazy">
                                </figure>
                                <div class="preview-card-copy">
                                    <strong><?= htmlspecialchars($card['title'], ENT_QUOTES) ?></strong>
                                    <p><?= htmlspecialchars($card['copy'], ENT_QUOTES) ?></p>
                                </div>
                            </a>
                        </article>
                    <?php } ?>
                </div>
            </section>

            <section class="home-section">
                <div class="overview-panels">
                    <article class="overview-panel">
                        <h3>What ships in the bundle</h3>
                        <ul class="panel-list">
                            <li>All markdown pages from <code>docs/</code></li>
                            <li>Public API guides for forms, table, sortable, and core</li>
                            <li>Real preview screenshots for key runtime states</li>
                            <li>Client-side search index built from rendered docs content</li>
                        </ul>
                    </article>

                    <article class="overview-panel">
                        <h3>Publishing target</h3>
                        <p>
                            Upload the contents of <code>docs-site/dist/</code> to any static host —
                            no PHP runtime is required for the published site. Just build, deploy, done.
                        </p>
                    </article>
                </div>
            </section>

            <footer class="site-footer">
                <div class="site-footer-inner">
                    <span>© <?= date('Y') ?> Wire ecosystem · MIT licensed.</span>
                </div>
            </footer>
        </main>
    </div>
</body>
</html>
