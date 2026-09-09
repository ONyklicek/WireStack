import { openPage, checker } from './lib/cdp.mjs';
import { writeFile } from 'node:fs/promises';
import { join } from 'node:path';

/*
 * CDP driver for a "sharp" theme, in the three phases it actually takes
 * (/previews/routed/users).
 *
 * `verify-theming.mjs` proves each lever moves something. This one proves the
 * thing the levers were split up *for*: that a token and a hook answer two
 * different questions, and that neither alone rebuilds a theme.
 *
 *   1  default          — the page as it ships
 *   2  + tokens         — `--radius-*: 0`. Every corner Tailwind compiles to
 *                         `var(--radius-*)` goes square: cards, inputs, buttons.
 *                         **Pills do not.** `rounded-full` compiles to
 *                         `calc(infinity * 1px)` and follows no token, which is
 *                         correct — a sharp theme that squared the avatars would
 *                         read as broken rather than sharp.
 *   3  + the shipped shape — `data-shape="sharp"`, which is the real setting.
 *                         `wire-core::partials.shape` squares the pills that are
 *                         content *and* the shell's own chrome, and leaves the
 *                         avatar alone. That distinction is the whole reason both
 *                         layers exist: a token cannot tell "round because it is
 *                         a card" from "round because it is a face".
 *
 * The census buckets every element by computed radius rather than asserting on
 * one, because "did the theme apply" is a question about the page, not about a
 * div — and picking one div is how the first version of this reported that
 * tokens do nothing (it happened to measure something already square).
 *
 * It writes full-page shots and a **zoomed crop** of the toolbar band. The crop
 * is the one worth opening: at 1400px wide an 8px radius is a rendering detail,
 * and the full-page shots of phases 1 and 2 look identical to the eye while
 * differing in every corner.
 *
 * Two gaps this driver found, both of which a count could not see:
 *
 *  - The role pills in the table carried no hook at all, because the hook sweep
 *    followed `data-testid` and `TagsColumn` has none. Phase 3 changed nothing
 *    until `table-tag` existed.
 *  - The shell's own chrome — search box, both toggle groups, the user button —
 *    stayed round while the content went square, and **the driver passed**: the
 *    badges moved, the pill count went down, and "some pills changed" was all
 *    the check asked. It asks by name now.
 *
 * Usage:
 *   vendor/bin/testbench serve --host=127.0.0.1 --port=8085   # in background
 *   node workbench/scripts/verify-sharp-theme.mjs
 */

const url = process.env.PREVIEW_URL
  ?? `${process.env.PREVIEW_ORIGIN ?? 'http://127.0.0.1:8085'}/previews/routed/users`;

/** Phase 2: the whole of a `sharp` preset, as tokens. */
const TOKENS = `:root {
  --radius-xs: 0; --radius-sm: 0; --radius-md: 0; --radius-lg: 0;
  --radius-xl: 0; --radius-2xl: 0; --radius-3xl: 0; --radius-4xl: 0;
}`;

/*
 * Phase 3 has no CSS here on purpose: it flips `data-shape="sharp"` and lets the
 * framework's own `wire-core::partials.shape` do the work. A driver that carried
 * its own copy of those rules would pass while the shipped ones were wrong —
 * which is exactly what happened before shape existed as a setting, when this
 * file's hand-written list squared the badges and left the whole top bar round.
 */

const { page, eval_, shot, shotDir, consoleErrors, badResponses, close } =
  await openPage({ url, shotPrefix: 'sharp-theme', width: 1400, height: 900 });

const { check, finish } = checker();

/**
 * Every element bucketed by the corner it has — and the named ones listed.
 *
 * The count alone is too weak, and was: "some pills changed" passed while every
 * pill in the top bar stayed round, because the badges in the table moved and
 * the total went down. What the shell looks like is a different question from
 * how many pills there are, so the named elements are checked by name.
 */
const census = () => eval_(`(() => {
  const buckets = { square: 0, rounded: 0, pill: 0 };

  for (const el of document.querySelectorAll('header *, aside *, main *')) {
    const r = parseFloat(getComputedStyle(el).borderTopLeftRadius);
    if (! r) buckets.square++;
    else if (r > 1000) buckets.pill++;   // calc(infinity * 1px), clamped
    else buckets.rounded++;
  }

  const named = {};
  for (const el of document.querySelectorAll('[data-wire]')) {
    const r = parseFloat(getComputedStyle(el).borderTopLeftRadius);
    named[el.dataset.wire] = r > 1000 ? 'pill' : (r ? 'rounded' : 'square');
  }

  return { ...buckets, named };
})()`);

const addStyle = (id, css) => eval_(`(() => {
  const style = document.createElement('style');
  style.id = ${JSON.stringify(id)};
  style.textContent = ${JSON.stringify(css)};
  document.head.appendChild(style);
  return true;
})()`);

/** The band where a card corner, an input, a primary button and a pill sit together. */
const clip = await eval_(`(() => {
  const card = document.querySelector('main [class*="bg-white"]');
  if (! card) return null;
  const r = card.getBoundingClientRect();
  return { x: Math.round(r.x), y: Math.round(r.y), width: Math.round(r.width), height: 260, scale: 2 };
})()`);

const crop = async (name) => {
  if (! clip) return;
  const { data } = await page('Page.captureScreenshot', { format: 'png', clip, captureBeyondViewport: true });
  await writeFile(join(shotDir, `${name}.png`), Buffer.from(data, 'base64'));
};

try {
  const normal = await census();
  const before = normal;
  await shot('01-default');
  await crop('zoom-1-default');

  check('the page has corners to square in the first place', before.rounded > 20,
    `${before.rounded} rounded, ${before.pill} pills, ${before.square} square`);
  check('and pills to leave alone', before.pill > 0, `${before.pill} pills`);

  await addStyle('sharp-tokens', TOKENS);
  const tokens = await census();
  await shot('02-sharp-tokens');
  await crop('zoom-2-tokens');

  check('every token-backed corner went square', tokens.rounded === 0,
    `${before.rounded} → ${tokens.rounded}`);
  check('a pill did not follow the token, which is the documented limit', tokens.pill === before.pill,
    `${before.pill} → ${tokens.pill} — rounded-full is calc(infinity * 1px)`);

  check('the shipped rules are on the page', await eval_(`!!document.querySelector('style[data-wire-shape]')`));
  await eval_(`document.documentElement.dataset.shape = 'sharp'`);
  const hooks = await census();
  await shot('03-sharp-hooks');
  await crop('zoom-3-hooks');

  check('a hook squares the pills a token could not reach', hooks.pill < tokens.pill,
    `${tokens.pill} → ${hooks.pill}`);

  // By name, not by count. The count passed once while the whole top bar stayed
  // round: the badges in the table moved and the total went down with them.
  const shouldSquare = [
    'table-badge', 'table-tag', 'badge',
    'global-search-trigger', 'admin-theme', 'admin-density', 'admin-user',
  ].filter((name) => name in normal.named);

  const stillRound = shouldSquare.filter((name) => hooks.named[name] === 'pill');
  check('every named pill is square, the chrome included', stillRound.length === 0,
    stillRound.length ? `still round: ${stillRound.join(', ')}` : shouldSquare.join(', '));

  check('and the avatar is not among them — a face stays a circle',
    hooks.named['admin-avatar'] === 'pill', String(hooks.named['admin-avatar']));
  check('nothing came back round', hooks.rounded === 0, `${hooks.rounded} rounded`);
} finally {
  await close();
}

finish({ consoleErrors, badResponses, shotDir });
