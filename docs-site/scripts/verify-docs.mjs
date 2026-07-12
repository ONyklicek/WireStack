#!/usr/bin/env node
/*
 * Docs build sanity gate — runs locally (`npm run docs:check`) and in CI before
 * the deploy. It does NOT need the Torchlight API (which needs a token and is
 * flaky), so it is deterministic and fast. It catches every docs-build failure
 * class we have actually hit:
 *
 *   1. Source Markdown defects — unbalanced code fences, an invalid closing
 *      fence (``` followed by trailing text), a leaked `[tl! …]` Torchlight
 *      marker in prose, and broken relative `.md` links (e.g. the
 *      `columnclaudes` typo).
 *   2. Missing build assets — the copy-source strip script referenced by
 *      package.json + deploy-docs.yml must exist (the MODULE_NOT_FOUND that
 *      broke the deploy).
 *   3. build.php must run cleanly for every locale (into a throwaway dir so the
 *      real dist is untouched) and produce HTML.
 *   4. The strip script must run cleanly over that output.
 *   5. Structural soundness — no built page may have content after </html>
 *      (the signature of the `->prefix('$')` copy-source leak) and no
 *      data-torchlight-original textarea may survive the strip.
 *
 * Exit 0 = clean; exit 1 = one or more checks failed (details printed).
 */
import { readdirSync, readFileSync, existsSync, statSync, rmSync, mkdtempSync } from 'node:fs';
import { join, resolve } from 'node:path';
import { tmpdir } from 'node:os';
import { execFileSync } from 'node:child_process';

const REPO = resolve(process.argv[2] ?? '.');
const DOCS = join(REPO, 'docs');
const failures = [];
const fail = (msg) => failures.push(msg);

// ── helpers ──────────────────────────────────────────────────────────────
function walk(dir, ext, out = []) {
  for (const e of readdirSync(dir)) {
    const p = join(dir, e);
    if (statSync(p).isDirectory()) walk(p, ext, out);
    else if (p.endsWith(ext)) out.push(p);
  }
  return out;
}
const rel = (p) => p.slice(REPO.length + 1);

// ── 1. source markdown integrity ─────────────────────────────────────────
const mdFiles = walk(DOCS, '.md');
for (const md of mdFiles) {
  const text = readFileSync(md, 'utf8');
  const lines = text.split('\n');
  let inCode = false;
  let fenceCount = 0;
  lines.forEach((ln, i) => {
    const s = ln.replace(/^\s+/, '');
    if (s.startsWith('```')) {
      fenceCount++;
      if (inCode && s.replace(/\s+$/, '') !== '```') {
        fail(`${rel(md)}:${i + 1} — invalid closing fence (trailing text after \`\`\`): ${ln.trim().slice(0, 60)}`);
      }
      inCode = !inCode;
    } else if (!inCode && ln.includes('[tl!')) {
      fail(`${rel(md)}:${i + 1} — leaked Torchlight marker in prose: ${ln.trim().slice(0, 60)}`);
    }
  });
  if (fenceCount % 2 !== 0) fail(`${rel(md)} — unbalanced code fences (${fenceCount})`);

  // broken relative .md links
  const linkRe = /\]\((?!https?:|#|mailto:)([^)#]+\.md)(#[^)]*)?\)/g;
  let m;
  while ((m = linkRe.exec(text)) !== null) {
    const target = resolve(join(md, '..', m[1]));
    if (!existsSync(target)) fail(`${rel(md)} — broken relative link: ${m[1]}`);
  }
}

// ── 2. required build assets exist ───────────────────────────────────────
const stripScript = join(REPO, 'docs-site/scripts/strip-copy-source.mjs');
if (!existsSync(stripScript)) fail(`missing build asset: docs-site/scripts/strip-copy-source.mjs (referenced by package.json + deploy-docs.yml)`);

// ── 3. build.php runs for every locale into a throwaway dir ──────────────
let locales = ['en'];
try {
  const cfg = JSON.parse(readFileSync(join(REPO, 'docs-site/config.json'), 'utf8'));
  locales = (cfg.locales ?? []).map((l) => l.code).filter(Boolean);
  if (!locales.length) locales = ['en'];
} catch { /* default */ }

const tmpDist = mkdtempSync(join(tmpdir(), 'docs-verify-'));
let built = false;
try {
  for (const code of locales) {
    execFileSync('php', [join(REPO, 'docs-site/build.php')], {
      env: { ...process.env, DOCS_BUILD_LOCALE: code, DOCS_DIST_DIR: tmpDist },
      stdio: 'pipe',
    });
  }
  built = true;
} catch (e) {
  fail(`build.php failed: ${String(e.stderr ?? e.message).split('\n')[0]}`);
}

if (built) {
  const htmls = walk(tmpDist, '.html');
  if (!htmls.length) fail('build produced no HTML pages');

  // ── 4. strip script runs cleanly over the output ───────────────────────
  if (existsSync(stripScript)) {
    try {
      execFileSync('node', [stripScript, tmpDist], { stdio: 'pipe' });
    } catch (e) {
      fail(`strip-copy-source.mjs failed: ${String(e.stderr ?? e.message).split('\n')[0]}`);
    }
  }

  // ── 5. structural soundness of every built page ────────────────────────
  for (const f of htmls) {
    const h = readFileSync(f, 'utf8');
    const afterHtml = h.split('</html>').slice(1).join('</html>').trim();
    if (afterHtml.length > 0) fail(`${f.slice(tmpDist.length + 1)} — content after </html> (copy-source leak signature)`);
    if (h.includes('data-torchlight-original')) fail(`${f.slice(tmpDist.length + 1)} — copy-source textarea survived strip`);
  }
}

rmSync(tmpDist, { recursive: true, force: true });

// ── report ───────────────────────────────────────────────────────────────
if (failures.length) {
  console.error(`docs:check FAILED — ${failures.length} issue(s):`);
  for (const f of failures) console.error(`  ✗ ${f}`);
  process.exit(1);
}
console.log(`docs:check OK — ${mdFiles.length} markdown files, ${locales.length} locale(s) built clean, structure sound.`);
