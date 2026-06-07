<?php
/**
 * Version switcher dropdown.
 *
 * Expects $versionMenu = ['current' => string, 'items' => array{label,badge,current,href,disabled}].
 */
if (empty($versionMenu['items'])) {
    return;
}
?>
<div class="version-switcher" data-version-switcher>
    <button class="version-trigger" type="button" data-version-trigger aria-haspopup="listbox" aria-expanded="false" aria-label="Switch documentation version">
        <span class="version-current"><?= htmlspecialchars($versionMenu['current'], ENT_QUOTES) ?></span>
        <svg class="version-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
    </button>
    <div class="version-menu" data-version-menu role="listbox" hidden>
        <p class="version-menu-title">Version</p>
        <?php foreach ($versionMenu['items'] as $version) { ?>
            <?php if ($version['disabled']) { ?>
                <span class="version-item is-disabled" aria-disabled="true">
                    <span class="version-item-label"><?= htmlspecialchars($version['label'], ENT_QUOTES) ?></span>
                    <?php if ($version['badge'] !== '') { ?>
                        <span class="version-tag is-soon"><?= htmlspecialchars($version['badge'], ENT_QUOTES) ?></span>
                    <?php } ?>
                </span>
            <?php } else { ?>
                <a class="version-item<?= $version['current'] ? ' is-current' : '' ?>" href="<?= htmlspecialchars((string) $version['href'], ENT_QUOTES) ?>" role="option" aria-selected="<?= $version['current'] ? 'true' : 'false' ?>">
                    <span class="version-item-label"><?= htmlspecialchars($version['label'], ENT_QUOTES) ?></span>
                    <?php if ($version['badge'] !== '') { ?>
                        <span class="version-tag<?= $version['current'] ? ' is-current' : '' ?>"><?= htmlspecialchars($version['badge'], ENT_QUOTES) ?></span>
                    <?php } ?>
                </a>
            <?php } ?>
        <?php } ?>
    </div>
</div>
