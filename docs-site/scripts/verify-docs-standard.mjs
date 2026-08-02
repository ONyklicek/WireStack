#!/usr/bin/env node
/*
 * Docs standard gate — `npm run docs:standard`. Enforces the mechanically
 * checkable half of AI_DOCS_STANDARD.md:
 *
 *   S1  A long PHP/Blade example must spotlight what it is about with
 *       Torchlight `[tl! focus]`. A 40-line block where everything is equally
 *       bright teaches nothing; the reader has to find the three lines the
 *       prose was talking about.
 *   S2  Focus markers must be well formed: every `focus:start` closed by a
 *       `focus:end` in the same block, no orphan end, and never every line of a
 *       block (focusing everything focuses nothing).
 *   S3  Every English page has a Czech mirror, and the mirror matches
 *       structurally: same number of code blocks, the same fluent call chain in
 *       each block, and the same focus markers. Translations drift by rewriting
 *       an example rather than translating it; the call chain is the part that
 *       must never differ.
 *
 * S1 carries a baseline (docs-site/docs-standard-baseline.txt) of the pages
 * written before the standard existed — same contract as the API gate: known
 * gaps are recorded, only new ones fail, and a block loses its exemption the
 * moment it is edited (the key is a hash of the block's code).
 *
 *   node docs-site/scripts/verify-docs-standard.mjs [repo-root]
 *   node docs-site/scripts/verify-docs-standard.mjs . --update-baseline
 *
 * S2 and S3 have no baseline: the corpus already satisfies them, and that is
 * exactly when a rule is worth locking down.
 */
import { createHash } from 'node:crypto';
import { existsSync, readFileSync, readdirSync, statSync, writeFileSync } from 'node:fs';
import { join, relative, resolve } from 'node:path';

const REPO = resolve(process.argv[2] && !process.argv[2].startsWith('--') ? process.argv[2] : '.');
const UPDATE = process.argv.includes('--update-baseline');
const DOCS = join(REPO, 'docs');
const BASELINE_FILE = join(REPO, 'docs-site/docs-standard-baseline.txt');

// Examples this long stop being a snippet and become a program: without a
// spotlight the reader cannot tell which lines the surrounding prose means.
const LONG_BLOCK_LINES = 12;
const FOCUSABLE = new Set(['php', 'blade']);

const failures = [];
const fail = (msg) => failures.push(msg);

function walk(dir, out = []) {
  for (const entry of readdirSync(dir)) {
    const path = join(dir, entry);
    if (statSync(path).isDirectory()) walk(path, out);
    else if (path.endsWith('.md')) out.push(path);
  }
  return out;
}

/** Fenced code blocks of one page: language, body lines, and where they start. */
function codeBlocks(text) {
  const lines = text.split('\n');
  const blocks = [];
  let open = null;
  let lang = '';

  lines.forEach((line, i) => {
    if (!line.trimStart().startsWith('```')) return;
    if (open === null) {
      open = i;
      lang = line.trim().replace(/^`+/, '').trim().toLowerCase() || 'none';
    } else {
      blocks.push({ lang, line: open + 1, body: lines.slice(open + 1, i) });
      open = null;
    }
  });

  return blocks;
}

const stripComments = (line) => line.replace(/\/\/.*$/, '').replace(/\{\{--[\s\S]*?--\}\}/g, '');

/** The fluent chain of a block — the part a translation must never change. */
const callChain = (body) => body
  .map(stripComments)
  .join('\n')
  .match(/->\s*[a-zA-Z_][a-zA-Z0-9_]*\s*\(/g)
  ?.map((call) => call.replace(/\s+/g, '')) ?? [];

const focusMarkers = (body) => body.flatMap((line, i) =>
  [...line.matchAll(/\[tl!\s*([a-z:]+)/g)].map((m) => `${i}:${m[1]}`));

// Identity survives the block moving around the page or new blocks appearing
// above it, but not the code itself changing — editing a block re-opens the
// standard for it, which is the point.
const blockKey = (relPath, body) =>
  `${relPath}#${createHash('sha1').update(body.map(stripComments).join('\n').trim()).digest('hex').slice(0, 8)}`;

const enPages = walk(DOCS).filter((p) => !relative(DOCS, p).startsWith('cs/')).sort();
const violations = [];

for (const page of enPages) {
  const relPath = relative(REPO, page);
  const text = readFileSync(page, 'utf8');
  const blocks = codeBlocks(text);

  blocks.forEach((block) => {
    const markers = focusMarkers(block.body);

    // S1 — a long example must say what it is about.
    if (FOCUSABLE.has(block.lang) && block.body.length >= LONG_BLOCK_LINES && markers.length === 0) {
      violations.push(`${blockKey(relPath, block.body)}  ${relPath}:${block.line} (${block.lang}, ${block.body.length} lines)`);
    }

    // S2 — well-formed markers.
    let open = 0;
    for (const marker of markers) {
      const kind = marker.split(':').slice(1).join(':');
      if (kind === 'focus:start') open++;
      if (kind === 'focus:end') {
        open--;
        if (open < 0) fail(`${relPath}:${block.line} — [tl! focus:end] without a matching focus:start`);
      }
    }
    if (open > 0) fail(`${relPath}:${block.line} — [tl! focus:start] is never closed by focus:end`);

    const focusedLines = new Set(markers.map((m) => Number(m.split(':')[0])));
    const codeLines = block.body.filter((l) => l.trim() !== '').length;
    if (markers.length > 0 && !markers.some((m) => m.endsWith('focus:start')) && focusedLines.size >= codeLines) {
      fail(`${relPath}:${block.line} — every line is focused, which focuses nothing`);
    }
  });

  // S3 — the Czech mirror.
  const csPage = join(DOCS, 'cs', relative(DOCS, page));
  if (!existsSync(csPage)) {
    fail(`${relPath} — no Czech mirror (docs/cs/${relative(DOCS, page)})`);
    continue;
  }

  const csBlocks = codeBlocks(readFileSync(csPage, 'utf8'));
  if (csBlocks.length !== blocks.length) {
    fail(`${relPath} — the Czech mirror has ${csBlocks.length} code blocks, this page has ${blocks.length}`);
    continue;
  }

  blocks.forEach((block, i) => {
    const cs = csBlocks[i];
    if (callChain(block.body).join(' ') !== callChain(cs.body).join(' ')) {
      fail(`${relPath}:${block.line} — the Czech mirror calls a different chain (translate the comments, not the code)`);
    }
    if (focusMarkers(block.body).join(' ') !== focusMarkers(cs.body).join(' ')) {
      fail(`${relPath}:${block.line} — the Czech mirror focuses different lines`);
    }
  });
}

if (UPDATE) {
  writeFileSync(BASELINE_FILE, `${violations.map((v) => v.split('  ')[0]).join('\n')}\n`);
  console.log(`docs-standard baseline updated — ${violations.length} known gap(s) recorded.`);
  process.exit(0);
}

const baseline = existsSync(BASELINE_FILE)
  ? new Set(readFileSync(BASELINE_FILE, 'utf8').split('\n').map((l) => l.trim()).filter(Boolean))
  : new Set();

const fresh = violations.filter((v) => !baseline.has(v.split('  ')[0]));
for (const violation of fresh) {
  fail(`${violation} — long example without a [tl! focus] spotlight (AI_DOCS_STANDARD.md S1)`);
}

if (failures.length) {
  console.error(`docs:standard FAILED — ${failures.length} issue(s):`);
  for (const f of failures) console.error(`  ✗ ${f}`);
  console.error('\nSee AI_DOCS_STANDARD.md. A block edited since the baseline was written is expected to meet the standard.');
  process.exit(1);
}

const closed = baseline.size - violations.filter((v) => baseline.has(v.split('  ')[0])).length;
console.log(`docs:standard OK — ${enPages.length} pages, ${violations.length} baselined gap(s) left.`);
if (closed > 0) {
  console.log(`${closed} baselined gap(s) are now fixed — shrink the ledger with: npm run docs:standard -- --update-baseline`);
}
