<?php
/**
 * Canonical, hreflang and social-card tags, shared by every page template.
 *
 * Expects $canonicalUrl, $alternateUrls (list of ['hreflang','href']),
 * $ogTitle, $ogDescription, $ogImage and $htmlLang. Every absolute tag is
 * skipped when the build has no base URL configured (see config.json), so a
 * local build never emits links pointing at a site it is not.
 */
$canonicalUrl ??= '';
$alternateUrls ??= [];
$ogTitle ??= '';
$ogDescription ??= '';
$ogImage ??= '';
?>
<?php if ($canonicalUrl !== '') { ?>
    <link rel="canonical" href="<?= htmlspecialchars($canonicalUrl, ENT_QUOTES) ?>">
<?php } ?>
<?php foreach ($alternateUrls as $alternate) { ?>
    <link rel="alternate" hreflang="<?= htmlspecialchars((string) $alternate['hreflang'], ENT_QUOTES) ?>" href="<?= htmlspecialchars((string) $alternate['href'], ENT_QUOTES) ?>">
<?php } ?>
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="WireStack Docs">
    <meta property="og:locale" content="<?= htmlspecialchars($htmlLang ?? 'en', ENT_QUOTES) ?>">
    <meta property="og:title" content="<?= htmlspecialchars($ogTitle, ENT_QUOTES) ?>">
<?php if ($ogDescription !== '') { ?>
    <meta property="og:description" content="<?= htmlspecialchars($ogDescription, ENT_QUOTES) ?>">
<?php } ?>
<?php if ($canonicalUrl !== '') { ?>
    <meta property="og:url" content="<?= htmlspecialchars($canonicalUrl, ENT_QUOTES) ?>">
<?php } ?>
<?php if ($ogImage !== '') { ?>
    <meta property="og:image" content="<?= htmlspecialchars($ogImage, ENT_QUOTES) ?>">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:image" content="<?= htmlspecialchars($ogImage, ENT_QUOTES) ?>">
<?php } else { ?>
    <meta name="twitter:card" content="summary">
<?php } ?>
    <meta name="twitter:title" content="<?= htmlspecialchars($ogTitle, ENT_QUOTES) ?>">
<?php if ($ogDescription !== '') { ?>
    <meta name="twitter:description" content="<?= htmlspecialchars($ogDescription, ENT_QUOTES) ?>">
<?php } ?>
