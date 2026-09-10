<?php
/**
 * The WireStack mark in the sidebar header.
 *
 * Drawn inline rather than as an `<img>` so its two contact pads can take
 * `currentColor` — an image would need a second file for the dark ground, and
 * the amber wire is the one part that never changes. The wire is painted from
 * `--primary`, so a theme that repaints the site repaints the mark with it.
 *
 * Geometry, clear space and the rules around it: `docs-site/assets/brand/`.
 */
?>
<span class="brand-mark" aria-hidden="true">
    <svg viewBox="0 0 64 64" fill="none">
        <path d="M52 12H20A10 10 0 0 0 20 32H44A10 10 0 0 1 44 52H12" stroke="var(--primary)" stroke-width="7" stroke-linecap="round"/>
        <circle cx="52" cy="12" r="4.6" fill="currentColor"/>
        <circle cx="12" cy="52" r="4.6" fill="currentColor"/>
    </svg>
</span>
