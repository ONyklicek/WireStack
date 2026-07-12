#!/usr/bin/env node
/*
 * Strip Torchlight's hidden `<textarea data-torchlight-original>` copy-source
 * elements from the built docs HTML.
 *
 * Why: the docs site's own copy button (assets/site.js) copies the *visible*
 * code via `code.innerText`, so this textarea is never read. But it stores the
 * raw, un-highlighted source — which (a) still contains `[tl! focus]` focus
 * annotations, so "copy" would paste them, and (b) is the one place raw code
 * with `$`-special sequences (e.g. `->prefix('$')`) can, on a partial/failed
 * highlight run, leak as duplicated text outside the article. Removing the dead
 * element eliminates both, and shrinks the HTML.
 *
 * Runs after `npm run docs:highlight` over docs-site/dist.
 */
import { readdirSync, readFileSync, writeFileSync, statSync } from 'node:fs';
import { join } from 'node:path';

const DIST = process.argv[2] ?? 'docs-site/dist';
// Non-greedy, DOTALL: match a single hidden copy-source textarea.
const RE = /<textarea[^>]*\bdata-torchlight-original\b[^>]*>[\s\S]*?<\/textarea>/g;

let files = 0;
let stripped = 0;

function walk(dir) {
  for (const entry of readdirSync(dir)) {
    const p = join(dir, entry);
    if (statSync(p).isDirectory()) {
      walk(p);
    } else if (p.endsWith('.html')) {
      const html = readFileSync(p, 'utf8');
      if (!html.includes('data-torchlight-original')) continue;
      const out = html.replace(RE, '');
      if (out !== html) {
        writeFileSync(p, out);
        files++;
        stripped += (html.match(RE) ?? []).length;
      }
    }
  }
}

walk(DIST);
console.log(`Stripped ${stripped} copy-source textarea(s) from ${files} file(s) in ${DIST}`);
