<?php
/**
 * Language switcher dropdown.
 *
 * Expects $localeMenu = ['current' => string, 'items' => array{label,code,current,href,disabled}].
 * Renders nothing when only a single locale is configured.
 */
if (empty($localeMenu['items'])) {
    return;
}
?>
<div class="locale-switcher" data-locale-switcher>
    <button class="locale-trigger" type="button" data-locale-trigger aria-haspopup="listbox" aria-expanded="false" aria-label="Switch language">
        <svg class="locale-globe" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a15 15 0 0 1 0 18M12 3a15 15 0 0 0 0 18"/></svg>
        <span class="locale-current"><?= htmlspecialchars($localeMenu['current'], ENT_QUOTES) ?></span>
        <svg class="locale-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
    </button>
    <div class="locale-menu" data-locale-menu role="listbox" hidden>
        <p class="locale-menu-title">Language</p>
        <?php foreach ($localeMenu['items'] as $locale) { ?>
            <a
                class="locale-item<?= $locale['current'] ? ' is-current' : '' ?>"
                href="<?= htmlspecialchars((string) $locale['href'], ENT_QUOTES) ?>"
                role="option"
                aria-selected="<?= $locale['current'] ? 'true' : 'false' ?>"
                data-locale-code="<?= htmlspecialchars($locale['code'], ENT_QUOTES) ?>"
            >
                <span class="locale-item-label"><?= htmlspecialchars($locale['label'], ENT_QUOTES) ?></span>
                <?php if ($locale['current']) { ?>
                    <svg class="locale-check" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                <?php } ?>
            </a>
        <?php } ?>
    </div>
</div>
