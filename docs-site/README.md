# Docs Site

Static documentation web for the Wire monorepo.

## Structure

- `build.php` generates the publishable site into `dist/`
- `assets/` contains site CSS, JS, and captured preview images
- `scripts/capture-previews.mjs` captures real screenshots from the internal Workbench preview pages

## Build

```bash
php docs-site/build.php
```

## Markdown Metadata

The builder now reads optional front matter from the top of each Markdown file:

```md
---
order: 30
section: Table
preview: table
summary: Short summary for the page hero and search excerpt.
---
```

Supported keys:

- `order`: controls sidebar ordering inside a section
- `section`: overrides the section inferred from the file path
- `preview`: forces a preview bundle (`forms`, `table`, `sortable`, `core`) or disables previews with `none`
- `summary` / `excerpt`: overrides the hero intro and search excerpt
- `title` / `nav_title`: overrides the sidebar label before the Markdown H1 is parsed

## Full Refresh

Rebuild preview assets, recapture screenshots, and regenerate the publishable docs site:

```bash
npm run docs:refresh
```

Useful variants:

```bash
bash scripts/refresh-docs-site.sh --skip-capture
bash scripts/refresh-docs-site.sh --skip-site-build
```

## Changed-Only Refresh

Run the smallest useful docs update for the current git changes:

```bash
npm run docs:changed
```

Useful variants:

```bash
bash scripts/docs-changed.sh --dry-run
bash scripts/docs-changed.sh --since origin/main
bash scripts/docs-changed.sh --full
```

## Capture Preview Images

Prerequisites:

1. Start the Workbench preview server
2. Start `safaridriver`

Then run:

```bash
node docs-site/scripts/capture-previews.mjs
```

## Publish

Upload the contents of `docs-site/dist/` to hosting.
