#!/usr/bin/env node
/*
 * Docs-site browser gate — `npm run docs:verify-ui`.
 *
 * verify-docs.mjs reads the built markup; nothing until now looked at what a
 * browser does with it, and both defects this exists for were invisible to a
 * static check:
 *
 *   1. The mobile search sheet lives inside the topbar (z-index 20) while its
 *      backdrop sits at 75 in the same stacking context, so every tap on the
 *      field landed on the backdrop and shut the sheet again. The markup was
 *      perfectly fine; only hit-testing showed it.
 *   2. The landing page redirects to the language the visitor last read, which
 *      bounced an explicit switch straight back — the home page could not be
 *      switched from Czech to English at all. Again: correct links, wrong
 *      behaviour.
 *
 * It builds every locale into a throwaway dir, serves it, and drives it in
 * headless Chrome at phone and desktop metrics. Exit 0 = clean.
 */
import { execFileSync, spawn } from 'node:child_process';
import { existsSync, mkdtempSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';
import { openPage, checker, sleep } from '../../workbench/scripts/lib/cdp.mjs';

const REPO = resolve(process.argv[2] ?? '.');
const PORT = Number(process.env.DOCS_UI_PORT ?? 8123);
const BASE = `http://127.0.0.1:${PORT}`;

// Local dev is macOS; CI is a Linux runner with Chrome on the PATH.
if (!process.env.CHROME_BIN) {
  const candidates = [
    '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
    '/usr/bin/google-chrome',
    '/usr/bin/google-chrome-stable',
    '/usr/bin/chromium-browser',
    '/usr/bin/chromium',
  ];
  const found = candidates.find((c) => existsSync(c));
  if (!found) {
    console.error('docs:verify-ui FAILED — no Chrome binary found. Set CHROME_BIN to one.');
    process.exit(1);
  }
  process.env.CHROME_BIN = found;
}

const dist = mkdtempSync(join(tmpdir(), 'docs-ui-'));
const locales = (() => {
  try {
    const cfg = JSON.parse(execFileSync('cat', [join(REPO, 'docs-site/config.json')], { encoding: 'utf8' }));
    return (cfg.locales ?? []).map((l) => l.code).filter(Boolean);
  } catch {
    return ['en'];
  }
})();

for (const code of locales) {
  execFileSync('php', [join(REPO, 'docs-site/build.php')], {
    env: { ...process.env, DOCS_BUILD_LOCALE: code, DOCS_DIST_DIR: dist },
    stdio: 'pipe',
  });
}

const server = spawn('php', ['-S', `127.0.0.1:${PORT}`, '-t', dist], { stdio: 'ignore' });
const { check, finish } = checker();
let session;

try {
  await sleep(600);
  session = await openPage({ url: `${BASE}/table/overview/`, shotPrefix: 'docs-ui', width: 390, height: 780, mobile: true, settle: 1200 });
  const { page, eval_, shot, shotDir, consoleErrors, badResponses, close } = session;

  const goto = async (url, settle = 1000) => {
    await page('Page.navigate', { url });
    await sleep(settle);
  };
  const tap = async (selector) => {
    const box = await eval_(`(() => {
      const el = document.querySelector(${JSON.stringify(selector)});
      if (!el) return null;
      const r = el.getBoundingClientRect();
      return { x: r.left + r.width / 2, y: r.top + r.height / 2 };
    })()`);
    if (!box) throw new Error(`no element for ${selector}`);
    for (const type of ['mousePressed', 'mouseReleased']) {
      await page('Input.dispatchMouseEvent', { type, x: box.x, y: box.y, button: 'left', clickCount: 1 });
    }
    await sleep(250);
  };

  // ── the index is not paid for until someone searches ───────────────────
  check(
    'search index is not fetched on page load',
    (await eval_(`performance.getEntriesByType('resource').filter(r => r.name.includes('search-index')).length`)) === 0,
  );

  // ── mobile search sheet ────────────────────────────────────────────────
  await tap('[data-search-open]');
  check('mobile: search sheet opens', await eval_(`document.body.classList.contains('search-open')`));

  const hit = await eval_(`(() => {
    const input = document.querySelector('[data-search-input]');
    const r = input.getBoundingClientRect();
    const top = document.elementFromPoint(r.left + r.width / 2, r.top + r.height / 2);
    return { same: top === input, top: top ? (top.className || top.tagName) : null };
  })()`);
  check('mobile: the search field is the topmost element (nothing covers it)', hit.same, `hit ${hit.top}`);

  await tap('[data-search-input]');
  check('mobile: tapping the field focuses it instead of closing the sheet', await eval_(`
    document.activeElement === document.querySelector('[data-search-input]') && document.body.classList.contains('search-open')
  `));

  await page('Input.insertText', { text: 'column' });
  await sleep(900);
  const mobileResults = await eval_(`(() => {
    const res = document.querySelector('[data-search-results]');
    const links = [...res.querySelectorAll('a.search-result')];
    const r = res.getBoundingClientRect();
    const first = links[0]?.getBoundingClientRect();
    const covering = first ? document.elementFromPoint(first.left + first.width / 2, first.top + 10) : null;
    return {
      count: links.length,
      hidden: res.hidden,
      inViewport: r.top >= 0 && r.top < innerHeight && r.width === innerWidth,
      firstReachable: !!covering && !!covering.closest('.search-result'),
      titles: links.slice(0, 3).map((a) => a.querySelector('strong').textContent),
    };
  })()`);
  check('mobile: typing returns results', !mobileResults.hidden && mobileResults.count > 0, `${mobileResults.count} hits`);
  check('mobile: the results panel is full-bleed under the sheet', mobileResults.inViewport);
  check('mobile: results are clickable, not covered by the backdrop', mobileResults.firstReachable);
  await shot('mobile-search');

  // ── ranking: the page a term names must win ────────────────────────────
  check(
    'search ranks the page named by the query first',
    /^Columns$/.test(mobileResults.titles[0] ?? ''),
    `got ${JSON.stringify(mobileResults.titles)}`,
  );

  await tap('[data-search-close]');
  check('mobile: the backdrop closes the sheet', !(await eval_(`document.body.classList.contains('search-open')`)));

  // ── language switching on the landing page ─────────────────────────────
  await page('Emulation.setDeviceMetricsOverride', { width: 1280, height: 900, deviceScaleFactor: 1, mobile: false });

  await goto(`${BASE}/cs/`);
  const csHref = await eval_(`document.querySelector('[data-locale-code="en"]').href`);
  await goto(csHref);
  const afterToEn = await eval_(`({ url: location.href, lang: document.documentElement.lang, pref: JSON.parse(localStorage.getItem('wire-docs-locale') || '{}').code })`);
  check('home: switching cs -> en lands on the English home and stays', afterToEn.url === `${BASE}/` && afterToEn.lang === 'en', afterToEn.url);
  check('home: the switch is remembered as the new preference', afterToEn.pref === 'en');

  const enHref = await eval_(`document.querySelector('[data-locale-code="cs"]').href`);
  await goto(enHref);
  const afterToCs = await eval_(`({ url: location.href, lang: document.documentElement.lang, pref: JSON.parse(localStorage.getItem('wire-docs-locale') || '{}').code })`);
  check('home: switching en -> cs lands on the Czech home and stays', afterToCs.url === `${BASE}/cs/` && afterToCs.lang === 'cs', afterToCs.url);

  // The redirect itself must survive the fix: a plain visit to the root still
  // follows the remembered language.
  await goto(`${BASE}/`);
  check('home: a plain visit still follows the remembered language', await eval_(`location.pathname === '/cs/'`));

  // ── diacritics-insensitive search on the Czech site ────────────────────
  await goto(`${BASE}/cs/table/overview/`);
  const csHits = await eval_(`(async () => {
    const input = document.querySelector('[data-search-input]');
    const res = document.querySelector('[data-search-results]');
    input.focus();
    input.value = 'prehled';
    input.dispatchEvent(new Event('input'));
    for (let i = 0; i < 40 && !res.querySelector('a'); i++) await new Promise(r => setTimeout(r, 100));
    return {
      count: res.querySelectorAll('a.search-result').length,
      empty: res.textContent.trim(),
    };
  })()`);
  check('cs: an unaccented query matches accented content ("prehled" → "Přehled")', csHits.count > 0, `${csHits.count} hits`);

  const csEmpty = await eval_(`(async () => {
    const input = document.querySelector('[data-search-input]');
    const res = document.querySelector('[data-search-results]');
    input.value = 'qqqzzzxxx';
    input.dispatchEvent(new Event('input'));
    await new Promise(r => setTimeout(r, 300));
    return res.textContent.trim();
  })()`);
  check('cs: the empty-result copy is translated', !/No matches/.test(csEmpty), csEmpty.slice(0, 40));

  // ── SEO head + 404 ─────────────────────────────────────────────────────
  await goto(`${BASE}/table/overview/`);
  const head = await eval_(`({
    canonical: document.querySelector('link[rel=canonical]')?.href ?? '',
    alternates: [...document.querySelectorAll('link[rel=alternate][hreflang]')].map(l => l.hreflang),
    ogTitle: document.querySelector('meta[property="og:title"]')?.content ?? '',
    ogImage: document.querySelector('meta[property="og:image"]')?.content ?? '',
  })`);
  check('page head carries a canonical URL', head.canonical.endsWith('/table/overview/'), head.canonical);
  check('page head carries hreflang alternates for every locale', locales.every((c) => head.alternates.includes(c)) && head.alternates.includes('x-default'), head.alternates.join(','));
  check('page head carries a social card', head.ogTitle !== '' && head.ogImage.startsWith('http'), head.ogImage);

  await goto(`${BASE}/404.html`);
  check('404 page renders with a way back into the docs', await eval_(`
    !!document.querySelector('.not-found') && !!document.querySelector('.not-found a[href*="documentation/"]')
  `));
  check('404 page loads its stylesheet from an absolute path', await eval_(`
    getComputedStyle(document.querySelector('.not-found-code')).fontWeight === '800'
  `));

  finish({ consoleErrors, badResponses, shotDir });
  await close();
} catch (error) {
  check('driver ran to completion', false, String(error.message ?? error));
  finish({});
  await session?.close?.();
} finally {
  server.kill('SIGTERM');
  rmSync(dist, { recursive: true, force: true });
}
