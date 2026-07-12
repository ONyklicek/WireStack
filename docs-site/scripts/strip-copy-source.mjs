#!/usr/bin/env node
/*
 * Sanitize Torchlight's post-highlight HTML in the built docs site. Two jobs:
 *
 *   1. Strip the hidden `<textarea data-torchlight-original>` copy-source
 *      elements. The docs site's own copy button (assets/site.js) copies the
 *      *visible* code via `code.innerText`, so this textarea is never read. But
 *      it stores the raw, un-highlighted source — which (a) still contains
 *      `[tl! focus]` focus annotations, so "copy" would paste them, and (b) is
 *      the one place raw code with `$`-special sequences (e.g. `->prefix('$')`)
 *      can, on a partial/failed highlight run, leak as duplicated text. Removing
 *      the dead element eliminates both, and shrinks the HTML.
 *
 *   2. Repair the `->prefix('$')` copy-source leak. On a partial/failed run the
 *      Torchlight CLI resolves raw code as a JS `String.replace` *replacement*,
 *      where the `$'` inside `->prefix('$')` expands to "the rest of the string
 *      after the match" — appending a raw, duplicated copy of the document
 *      *after* its closing `</html>`. That leaked tail always lands after the
 *      first `</html>`, so truncating each file there deterministically heals it
 *      no matter how the highlight run behaved. `verify-no-leak.mjs` is the
 *      fail-loud backstop for anything this repair does not catch.
 *
 * Runs after `npm run docs:highlight` over docs-site/dist (and _site in CI).
 */
import { readdirSync, readFileSync, writeFileSync, statSync } from 'node:fs';
import { join } from 'node:path';

const DIST = process.argv[2] ?? 'docs-site/dist';
// Non-greedy, DOTALL: match a single hidden copy-source textarea.
const RE = /<textarea[^>]*\bdata-torchlight-original\b[^>]*>[\s\S]*?<\/textarea>/g;
// The first closing </html> ends the real document; anything after it is leak.
const HTML_CLOSE = /<\/html\s*>/i;

let files = 0;
let stripped = 0;
let repaired = 0;

// Drop any content after the first </html> (the `->prefix('$')` copy-source leak).
function truncateLeak(html) {
  const m = HTML_CLOSE.exec(html);
  if (!m) return html;
  const end = m.index + m[0].length;
  if (html.slice(end).trim() === '') return html; // nothing (or only whitespace) after
  return html.slice(0, end) + '\n';
}

function walk(dir) {
  for (const entry of readdirSync(dir)) {
    const p = join(dir, entry);
    if (statSync(p).isDirectory()) {
      walk(p);
      continue;
    }
    if (!p.endsWith('.html')) continue;

    const html = readFileSync(p, 'utf8');
    let out = html;

    if (out.includes('data-torchlight-original')) {
      stripped += (out.match(RE) ?? []).length;
      out = out.replace(RE, '');
    }

    const healed = truncateLeak(out);
    if (healed !== out) {
      out = healed;
      repaired++;
      console.log(`::warning::Repaired copy-source leak (content after </html>) in ${p}`);
    }

    if (out !== html) {
      writeFileSync(p, out);
      files++;
    }
  }
}

walk(DIST);
console.log(`Sanitized ${files} file(s) in ${DIST}: stripped ${stripped} copy-source textarea(s), repaired ${repaired} leak(s).`);
