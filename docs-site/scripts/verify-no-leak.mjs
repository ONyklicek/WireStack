#!/usr/bin/env node
/*
 * Post-highlight integrity guard for the assembled docs site. Runs in
 * deploy-docs.yml *after* Torchlight + strip-copy-source, over the final _site
 * tree that is about to be published — the one artifact docs-check.yml cannot
 * see (it deliberately never runs the flaky Torchlight API).
 *
 * It fails the deploy if any page still shows the `->prefix('$')` copy-source
 * leak — content after the closing </html>, or a surviving
 * `data-torchlight-original` textarea — so a corrupted highlight run can never
 * be published silently. strip-copy-source.mjs heals the known leak shape; this
 * is the fail-loud backstop for anything it did not.
 *
 * Exit 0 = clean; exit 1 = one or more pages are corrupt (details printed).
 */
import { readdirSync, readFileSync, statSync } from 'node:fs';
import { join, resolve } from 'node:path';

const DIST = resolve(process.argv[2] ?? 'docs-site/dist');
const failures = [];

function walk(dir) {
  for (const entry of readdirSync(dir)) {
    const p = join(dir, entry);
    if (statSync(p).isDirectory()) {
      walk(p);
      continue;
    }
    if (!p.endsWith('.html')) continue;

    const html = readFileSync(p, 'utf8');
    const rel = p.slice(DIST.length + 1);

    const afterHtml = html.split('</html>').slice(1).join('</html>').trim();
    if (afterHtml.length > 0) {
      failures.push(`${rel} — content after </html> (${afterHtml.length} bytes): ${afterHtml.slice(0, 60).replace(/\s+/g, ' ')}…`);
    }
    if (html.includes('data-torchlight-original')) {
      failures.push(`${rel} — copy-source textarea survived strip`);
    }
  }
}

walk(DIST);

if (failures.length) {
  console.error(`::error::docs leak guard FAILED — ${failures.length} corrupted page(s):`);
  for (const f of failures) console.error(`  ✗ ${f}`);
  process.exit(1);
}
console.log(`docs leak guard OK — no post-highlight corruption in ${DIST}.`);
