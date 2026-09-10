#!/usr/bin/env node
/*
 * Hook-name gate — `npm run hooks:verify`. Holds the promise
 * `docs/start/theming.md` makes about `@wireEl` names:
 *
 *   > A name is public API. It may be added; it is not renamed or removed in a
 *   > minor release.
 *
 * That sentence is worth nothing on its own. A consuming application writes
 * `[data-wire="table-toolbar"] { … }` in its own stylesheet, and the day the
 * name goes away the rule stops matching — no error, no warning, just a panel
 * that quietly looks wrong again. The whole point of hooks over publishing a
 * view is that they survive an upgrade, so the survival has to be checked.
 *
 *   H1  Every name the docs advertise exists in the markup. Two were wrong the
 *       day the page was written — `admin-nav-badge` had been removed and
 *       `table-toolbar` had never existed — and nothing but reading it caught
 *       them. A reader who copies a name that resolves to nothing has no way to
 *       tell whether their CSS or the framework is at fault.
 *   H2  Every name is kebab-case. `ElementHook::render()` drops anything else
 *       rather than escaping it into the markup, so a bad name is not a broken
 *       page — it is an element with no hook at all, which is worse to find.
 *   H4  Every render-hook position the docs advertise is one the framework
 *       actually ships, and every position it ships is documented. A position
 *       named and never placed renders nothing for ever; one placed and never
 *       named is a promise nobody can find.
 *   H3  A name that shipped keeps shipping. The ledger in
 *       scripts/hook-names.json may grow and may not shrink; removing a name is
 *       a major-release decision, and this is where that decision gets made on
 *       purpose rather than by a careless rename.
 *   H5  A hook sits inside an opening tag. `@wireEl` writes an *attribute*, so
 *       one written a line too low writes `data-wire="empty-state"` into the
 *       page as visible text — which is what the canonical empty state did, on
 *       every empty table in the framework, past 2869 green tests and a hook
 *       gate that only ever asked whether the name was rendered.
 *
 *   node scripts/verify-hook-names.mjs [repo-root]
 *   node scripts/verify-hook-names.mjs . --update-ledger
 *   node scripts/verify-hook-names.mjs . --verbose
 *
 * H3 is a ledger rather than a test of the current tree because that is the only
 * shape that can see a *removal*: the markup after the removal is perfectly
 * consistent with itself, and only something written down beforehand disagrees.
 */
import { existsSync, readFileSync, readdirSync, statSync, writeFileSync } from 'node:fs';
import { join, relative, resolve } from 'node:path';

const REPO = resolve(process.argv[2] && !process.argv[2].startsWith('--') ? process.argv[2] : '.');
const UPDATE = process.argv.includes('--update-ledger');
const VERBOSE = process.argv.includes('--verbose');
const LEDGER = join(REPO, 'scripts/hook-names.json');

/** The shape `ElementHook::render()` accepts; anything else renders no hook at all. */
const KEBAB = /^[a-z][a-z0-9]*(?:-[a-z0-9]+)*$/;

/** The heading the documented vocabulary lives under, per locale. */
const DOC_PAGES = [
  { path: 'docs/start/theming.md', heading: '## Styling Hooks' },
  { path: 'docs/cs/start/theming.md', heading: '## Stylovací hooky' },
];

/** The same, for the render-hook positions. */
const RENDER_DOC_PAGES = [
  { path: 'docs/start/theming.md', heading: '## Render Hooks' },
  { path: 'docs/cs/start/theming.md', heading: '## Render hooky' },
];

const RENDER_POSITIONS_SOURCE = 'packages/core/src/Core/Plugin/RenderHook.php';

/** A position is dotted, outside-in: `panels.page.header.end`. */
const POSITION = /^[a-z][a-z0-9]*(?:\.[a-z0-9]+)+$/;

/** The positions the framework ships, read off the class that declares them. */
function shippedPositions() {
  const body = readFileSync(join(REPO, RENDER_POSITIONS_SOURCE), 'utf8');
  const block = body.slice(body.indexOf('const POSITIONS'), body.indexOf('];', body.indexOf('const POSITIONS')));

  return new Set([...block.matchAll(/'([a-z][a-z0-9.]*)'\s*=>/g)].map((m) => m[1]));
}

/** The positions the documentation advertises. */
function documentedPositions() {
  const found = new Map();

  for (const { path, heading } of RENDER_DOC_PAGES) {
    const file = join(REPO, path);

    if (!existsSync(file)) {
      continue;
    }

    const body = readFileSync(file, 'utf8');
    const start = body.indexOf(heading);

    if (start < 0) {
      console.error(`  ${path} has no "${heading}" section — the gate is reading the wrong page.`);
      process.exit(1);
    }

    const next = body.indexOf('\n## ', start + heading.length);
    const section = body.slice(start, next < 0 ? undefined : next);

    for (const row of section.split('\n')) {
      if (!row.startsWith('|') || !row.includes('`')) {
        continue;
      }

      for (const match of row.matchAll(/`([a-z][a-z0-9.]*)`/g)) {
        if (POSITION.test(match[1])) {
          found.set(match[1], [...(found.get(match[1]) ?? []), path]);
        }
      }
    }
  }

  return found;
}

function* bladeFiles(dir) {
  for (const entry of readdirSync(dir)) {
    const path = join(dir, entry);

    if (statSync(path).isDirectory()) {
      if (entry !== 'node_modules' && entry !== 'vendor' && entry !== 'dist') {
        yield* bladeFiles(path);
      }
    } else if (path.endsWith('.blade.php')) {
      yield path;
    }
  }
}

/** Every name the markup actually renders, with where it is. */
function namesInMarkup() {
  const found = new Map();

  for (const file of bladeFiles(join(REPO, 'packages'))) {
    const lines = readFileSync(file, 'utf8').split('\n');

    lines.forEach((line, index) => {
      for (const match of line.matchAll(/@wireEl\(\s*'([^']*)'\s*\)/g)) {
        const where = `${relative(REPO, file)}:${index + 1}`;
        found.set(match[1], [...(found.get(match[1]) ?? []), where]);
      }
    });
  }

  return found;
}

/**
 * From just after a directive's name, the end of its balanced `(...)`.
 *
 * Not cosmetic: `@if($field->getMinValue() !== null)` carries a `>`, and a
 * scanner that walks through it decides the tag ended there. Every attribute
 * below that line then reads as page content.
 */
function skipArguments(text, from) {
  let i = from;
  while (i < text.length && (text[i] === ' ' || text[i] === '\t')) i += 1;
  if (text[i] !== '(') return from;

  let depth = 0;
  let quote = null;

  while (i < text.length) {
    const c = text[i];

    if (quote) {
      if (c === '\\') { i += 2; continue; }
      if (c === quote) quote = null;
    } else if (c === '"' || c === "'") {
      quote = c;
    } else if (c === '(') {
      depth += 1;
    } else if (c === ')') {
      depth -= 1;
      if (depth === 0) return i + 1;
    }

    i += 1;
  }

  return from;
}

/**
 * Every `@wireEl` that is not inside an opening tag — H5.
 *
 * A hand-rolled walk rather than a regex, because the question is positional and
 * a line has no idea which side of a `>` it is on. What it has to step over:
 * Blade comments and `@php` blocks (full of `<` and `>` that mean nothing here),
 * echoes (`{{ $a > $b }}` is not a tag ending), quoted attribute values (an
 * Alpine expression is mostly arrows), a directive's own arguments, and the
 * bodies of `<script>` and `<style>` (where `<` is a comparison).
 */
function misplacedHooks() {
  const found = [];

  for (const file of bladeFiles(join(REPO, 'packages'))) {
    const text = readFileSync(file, 'utf8');
    let i = 0;
    let inTag = false;
    let quote = null;

    while (i < text.length) {
      if (!quote && text.startsWith('{{--', i)) {
        const end = text.indexOf('--}}', i);
        i = end < 0 ? text.length : end + 4;
        continue;
      }

      if (!inTag && !quote) {
        if (text.startsWith('@php', i)) {
          const end = text.indexOf('@endphp', i);
          i = end < 0 ? text.length : end + 7;
          continue;
        }

        if (text.startsWith('<!--', i)) {
          const end = text.indexOf('-->', i);
          i = end < 0 ? text.length : end + 3;
          continue;
        }

        const raw = /^<(script|style)\b/i.exec(text.slice(i));
        if (raw) {
          // Into the tag, so its own attributes still count; then past the body.
          const open = text.indexOf('>', i);
          if (open < 0) break;
          const close = new RegExp(`</${raw[1]}\\s*>`, 'i').exec(text.slice(open));
          i = close ? open + close.index + close[0].length : text.length;
          continue;
        }
      }

      if (text.startsWith('{!!', i)) {
        const end = text.indexOf('!!}', i);
        i = end < 0 ? text.length : end + 3;
        continue;
      }

      if (text.startsWith('{{', i)) {
        const end = text.indexOf('}}', i);
        i = end < 0 ? text.length : end + 2;
        continue;
      }

      const c = text[i];

      if (inTag && quote) {
        if (c === quote) quote = null;
        i += 1;
        continue;
      }

      if (inTag && (c === '"' || c === "'")) {
        quote = c;
        i += 1;
        continue;
      }

      if (inTag && c === '>') {
        inTag = false;
        i += 1;
        continue;
      }

      if (!inTag && c === '<' && /^<([A-Za-z/!]|\{\{)/.test(text.slice(i, i + 4))) {
        inTag = true;
        i += 1;
        continue;
      }

      if (c === '@') {
        const directive = /^@([A-Za-z][A-Za-z0-9_]*)/.exec(text.slice(i));

        if (directive) {
          if (directive[1] === 'wireEl' && !inTag) {
            found.push(`${relative(REPO, file)}:${text.slice(0, i).split('\n').length}`);
          }

          i = skipArguments(text, i + directive[0].length);
          continue;
        }
      }

      i += 1;
    }
  }

  return found;
}

/**
 * Every name a documentation page advertises.
 *
 * Two shapes, because the page uses both: `data-wire="…"` inside its examples,
 * and backticked names in the anchors table. Only the hook section is read —
 * a backticked kebab word elsewhere on the page is a colour role or a config
 * key, not a promise about an element.
 */
function namesInDocs() {
  const found = new Map();

  for (const { path, heading } of DOC_PAGES) {
    const file = join(REPO, path);

    if (!existsSync(file)) {
      continue;
    }

    const body = readFileSync(file, 'utf8');
    const start = body.indexOf(heading);

    if (start < 0) {
      console.error(`  ${path} has no "${heading}" section — the gate is reading the wrong page.`);
      process.exit(1);
    }

    const next = body.indexOf('\n## ', start + heading.length);
    const section = body.slice(start, next < 0 ? undefined : next);

    for (const match of section.matchAll(/data-wire="([^"]*)"/g)) {
      // A shell example greps for `data-wire="[a-z-]*"`; a pattern is not a
      // promise, and anything that is not a name cannot be one.
      if (KEBAB.test(match[1])) {
        found.set(match[1], [...(found.get(match[1]) ?? []), path]);
      }
    }

    // The anchors table: a row of backticked names against a description.
    for (const row of section.split('\n')) {
      if (!row.startsWith('|') || !row.includes('`')) {
        continue;
      }

      for (const match of row.matchAll(/`([a-z][a-z0-9-]*)`/g)) {
        found.set(match[1], [...(found.get(match[1]) ?? []), path]);
      }
    }
  }

  return found;
}

/* -------------------------------------------------------------------- run */

const markup = namesInMarkup();
const docs = namesInDocs();
const names = [...markup.keys()].sort();

if (UPDATE) {
  writeFileSync(LEDGER, `${JSON.stringify({ names }, null, 4)}\n`);
  console.log(`Wrote ${relative(REPO, LEDGER)} — ${names.length} names.`);
  process.exit(0);
}

if (!existsSync(LEDGER)) {
  console.error(`Missing ${relative(REPO, LEDGER)}. Run with --update-ledger to write it.`);
  process.exit(1);
}

const recorded = JSON.parse(readFileSync(LEDGER, 'utf8')).names ?? [];
const failures = [];
const added = names.filter((name) => !recorded.includes(name));

// H1 — the docs may not advertise a name the markup does not render.
for (const [name, pages] of docs) {
  if (!markup.has(name)) {
    failures.push(
      `H1  "${name}" is documented in ${[...new Set(pages)].join(', ')} and rendered nowhere.\n` +
      '      Either hook an element with it, or take it out of the page — a reader who\n' +
      '      copies it gets a rule that silently matches nothing.',
    );
  }
}

// H2 — a name outside the accepted shape renders no attribute at all.
for (const name of names) {
  if (!KEBAB.test(name)) {
    failures.push(
      `H2  "${name}" is not kebab-case, so ElementHook::render() drops it.\n` +
      `      ${markup.get(name)[0]}`,
    );
  }
}

// H3 — a name that shipped keeps shipping.
for (const name of recorded) {
  if (!markup.has(name)) {
    failures.push(
      `H3  "${name}" is in the ledger and gone from the markup.\n` +
      '      Removing a hook breaks every stylesheet that targets it. If that is\n' +
      '      deliberate and this is a major release, drop it with --update-ledger.',
    );
  }
}

// H5 — a hook that is not in a tag is text on the page rather than an attribute.
for (const where of misplacedHooks()) {
  failures.push(
    `H5  @wireEl at ${where} is not inside an opening tag.\n` +
    '      It writes an attribute, so from there it lands in the page as visible\n' +
    '      text — and the name still counts as rendered, which is why every other\n' +
    '      check here stays green. Move it up into the tag it belongs to.',
  );
}

// H4 — the render-hook vocabulary, documented and shipped, in both directions.
const shipped = shippedPositions();
const documented = documentedPositions();

for (const [position, pages] of documented) {
  if (!shipped.has(position)) {
    failures.push(
      `H4  render hook "${position}" is documented in ${[...new Set(pages)].join(', ')}\n` +
      `      and is not in ${RENDER_POSITIONS_SOURCE}. A position named and never placed\n` +
      '      renders nothing for ever.',
    );
  }
}

for (const position of shipped) {
  if (!documented.has(position)) {
    failures.push(
      `H4  render hook "${position}" ships and is documented nowhere.\n` +
      '      A position nobody can find is a promise that cannot be used.',
    );
  }
}

if (VERBOSE) {
  console.log(`${names.length} names in the markup, ${docs.size} advertised in the docs.\n`);

  const byPrefix = new Map();
  for (const name of names) {
    const prefix = name.split('-')[0];
    byPrefix.set(prefix, (byPrefix.get(prefix) ?? 0) + 1);
  }

  for (const [prefix, count] of [...byPrefix].sort((a, b) => b[1] - a[1])) {
    console.log(`  ${String(count).padStart(3)}  ${prefix}-*`);
  }

  console.log();
}

if (added.length > 0) {
  console.log(`New since the ledger (${added.length}): ${added.join(', ')}`);
  console.log('Run with --update-ledger to record them.\n');
}

if (failures.length > 0) {
  console.error('Hook-name gate failed:\n');
  failures.forEach((line) => console.error(`  ${line}\n`));
  console.error('See docs/start/theming.md § Styling Hooks for what a name promises.');
  process.exit(1);
}

console.log(
  `Hook names: ${names.length} rendered, ${docs.size} documented, none missing or dropped. `
  + `Render hooks: ${shipped.size} positions, all documented.`,
);
