<?php
/**
 * The sidebar tree, shared by the page and home templates so the two can never
 * render a different navigation.
 *
 * An item is either a link or a group of links (see buildNavSections()). A group
 * is a collapsed entry at rest and opens on click; the one holding the current
 * page is rendered open, so the reader always sees where they are without the
 * other 25 siblings of a directory pushing the rest of the section off-screen.
 *
 * @var array<int, array{title:string, items:array<int, array<string, mixed>>}> $navSections
 * @var callable(string):string $t
 */
?>
<nav class="sidebar-nav">
    <?php foreach ($navSections as $section) { ?>
        <section class="nav-section">
            <h2><?= htmlspecialchars($t($section['title']), ENT_QUOTES) ?></h2>
            <ul>
                <?php foreach ($section['items'] as $item) { ?>
                    <?php if (($item['type'] ?? 'link') === 'group') { ?>
                        <li class="nav-group<?= ! empty($item['active']) ? ' is-open' : '' ?>" data-nav-group>
                            <button class="nav-group-toggle" type="button" aria-expanded="<?= ! empty($item['active']) ? 'true' : 'false' ?>">
                                <span class="nav-group-label"><?= htmlspecialchars($t($item['title']), ENT_QUOTES) ?></span>
                                <span class="nav-group-count"><?= count($item['items']) ?></span>
                                <span class="nav-group-chevron" aria-hidden="true"></span>
                            </button>
                            <ul>
                                <?php foreach ($item['items'] as $child) { ?>
                                    <li>
                                        <a class="<?= ! empty($child['active']) ? 'is-active' : '' ?>" href="<?= htmlspecialchars($child['href'], ENT_QUOTES) ?>">
                                            <?= htmlspecialchars($child['title'], ENT_QUOTES) ?>
                                        </a>
                                    </li>
                                <?php } ?>
                            </ul>
                        </li>
                    <?php } else { ?>
                        <li>
                            <a class="<?= ! empty($item['active']) ? 'is-active' : '' ?>" href="<?= htmlspecialchars($item['href'], ENT_QUOTES) ?>">
                                <?= htmlspecialchars($item['title'], ENT_QUOTES) ?>
                            </a>
                        </li>
                    <?php } ?>
                <?php } ?>
            </ul>
        </section>
    <?php } ?>
</nav>
