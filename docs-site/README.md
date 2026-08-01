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

## Versioned Documentation (v1.x / v2.x)

The sidebar shows a **version switcher**. Each version is a self-contained build
written to its own folder under `dist/`:

| Version           | `path` | Output folder       | URL        |
|-------------------|--------|---------------------|------------|
| latest (current)  | `''`   | `dist/`             | `/`        |
| an older/newer one| `v2`   | `dist/v2/`          | `/v2/`     |

### 1. Configure the versions

Edit `config.json` — the canonical version x locale matrix. The deploy job feeds
one copy of it to every per-branch build (`DOCS_VERSIONS_FILE`), so the switchers
can never drift between branches:

```json
{
    "baseUrl": "https://wirestack.nyoncode.cz",
    "locales": [
        { "code": "en", "label": "English", "path": "", "default": true },
        { "code": "cs", "label": "Čeština", "path": "cs" }
    ],
    "versions": [
        { "label": "v1.x", "badge": "Latest", "path": "", "branch": "1.x", "available": true },
        { "label": "v2.x", "badge": "Soon", "path": "v2", "branch": "2.x", "available": false }
    ]
}
```

- `label`     — text shown in the switcher.
- `badge`     — small tag next to the label (`Latest`, `Soon`, `LTS`, …).
- `path`      — output sub-directory under `dist/` **and** the URL segment.
  Use `''` for the version served from the site root (the latest one).
- `available` — `true` once the version is actually built. `false` renders it as
  a disabled "coming soon" entry (cannot be clicked).

The switcher links are computed from path depth, so they are correct no matter
which version you are viewing and regardless of build order.

### 2. Where each version's content comes from

`build.php` always renders the Markdown under `docs/` of the **current checkout**.
So "a version" = "the docs as they exist on that branch/tag". Pick which version
the build represents with `DOCS_BUILD_VERSION=<label>`; it controls the output
folder (`path`) and which entry is marked as current.

### 3. Build commands

Build the latest version (served from the site root):

```bash
php docs-site/build.php
# or explicitly:
DOCS_BUILD_VERSION=v1.x php docs-site/build.php
```

Build another version into its sub-folder — typically from that version's branch
or tag so `docs/` contains the right content:

```bash
git worktree add ../wire-v2 v2.x        # or: git checkout v2.x
DOCS_BUILD_VERSION=v2.x php docs-site/build.php
```

Rebuilding one version **does not** wipe the others: the root build preserves the
sibling version folders, and a sub-folder build only recreates its own folder.
A full publish just builds every version you want online:

```bash
php docs-site/build.php                         # latest  -> dist/
DOCS_BUILD_VERSION=v2.x php docs-site/build.php  # v2.x    -> dist/v2/
```

### 4. Promoting a new latest (when v2 ships)

Move the previous latest into its own folder and make the new one the root:

```php
$siteVersions = [
    ['label' => 'v2.x', 'badge' => 'Latest', 'path' => '',   'available' => true],
    ['label' => 'v1.x', 'badge' => 'LTS',    'path' => 'v1', 'available' => true],
];
```

Then build v2 from the new code (root) and v1 from the old branch into `dist/v1`.

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

## SEO & Sharing

`baseUrl` in `config.json` (override: `DOCS_BASE_URL`) is the address the site is
published at, and everything that cannot be expressed relatively is derived from
it:

- `<link rel="canonical">` plus `hreflang` alternates for every locale and
  `x-default`, on every page,
- Open Graph / Twitter card tags, using the page's own preview screenshot,
- `sitemap.xml` per (version, locale) cell — a build only ever knows the cell it
  owns — and one `robots.txt` at the site root listing all of them,
- `404.html` per cell (GitHub Pages serves the closest one), linking by absolute
  path because it is served for any unknown path at any depth.

Leave `baseUrl` empty and the absolute-only tags are simply omitted, so a local
build never emits links pointing at a site it is not.

## Checks

```bash
npm run docs:check       # static: markdown integrity, a clean build per locale, structure
npm run docs:verify-ui   # browser: mobile search, language switching, ranking, head tags
```

`docs:verify-ui` builds every locale into a throwaway dir, serves it, and drives
it in headless Chrome at phone and desktop metrics. It exists because the two
defects it was written for — a mobile search sheet buried under its own backdrop,
and a landing page that bounced every language switch back — produced perfectly
valid markup and were invisible to the static check.

## Publish

Upload the contents of `docs-site/dist/` to hosting.
