<!DOCTYPE html>
<html lang="<?= htmlspecialchars($htmlLang ?? 'en', ENT_QUOTES) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($t('Page not found'), ENT_QUOTES) ?> | Wire Docs</title>
    <meta name="robots" content="noindex">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
    <?php // Served for any unknown path at any depth, so every URL here is absolute.?>
    <link rel="icon" href="<?= htmlspecialchars($assetPrefix.'assets/brand/favicon.svg', ENT_QUOTES) ?>" type="image/svg+xml">
    <link rel="icon" href="<?= htmlspecialchars($assetPrefix.'assets/brand/favicon-32.png', ENT_QUOTES) ?>" sizes="32x32">
    <link rel="apple-touch-icon" href="<?= htmlspecialchars($assetPrefix.'assets/brand/apple-touch-icon.png', ENT_QUOTES) ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars($assetPrefix.'assets/site.css', ENT_QUOTES) ?>">
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
</head>
<body class="docs-body not-found-body">
    <main class="not-found">
        <a class="brand-link" href="<?= htmlspecialchars($assetPrefix, ENT_QUOTES) ?>">
            <?php include __DIR__.'/partials/brand-mark.php'; ?>
            <span class="brand-name">WireStack</span>
        </a>
        <p class="not-found-code">404</p>
        <h1><?= htmlspecialchars($t('Page not found'), ENT_QUOTES) ?></h1>
        <p class="lead"><?= htmlspecialchars($t('That page has moved, been renamed, or never existed. The documentation index lists everything the site holds.'), ENT_QUOTES) ?></p>
        <div class="hero-actions">
            <a class="btn btn-primary" href="<?= htmlspecialchars($assetPrefix.'documentation/', ENT_QUOTES) ?>"><?= htmlspecialchars($t('Documentation Index'), ENT_QUOTES) ?> →</a>
            <a class="btn btn-ghost" href="<?= htmlspecialchars($assetPrefix.'getting-started/', ENT_QUOTES) ?>"><?= htmlspecialchars($t('Getting Started'), ENT_QUOTES) ?></a>
        </div>
    </main>
</body>
</html>
