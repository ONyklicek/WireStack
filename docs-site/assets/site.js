(() => {
  const body = document.body;
  const root = document.documentElement;

  /* ----------------------------------------------------------
     Theme toggle (initial theme is set inline in <head>)
     ---------------------------------------------------------- */
  const themeToggle = document.querySelector('[data-theme-toggle]');

  themeToggle?.addEventListener('click', () => {
    const next = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
    root.setAttribute('data-theme', next);
    try {
      localStorage.setItem('wire-docs-theme', next);
    } catch {}
  });

  /* ----------------------------------------------------------
     Mobile navigation
     ---------------------------------------------------------- */
  document.querySelector('[data-nav-toggle]')?.addEventListener('click', () => {
    body.classList.add('nav-open');
  });

  document.querySelectorAll('[data-nav-close]').forEach((el) => {
    el.addEventListener('click', () => body.classList.remove('nav-open'));
  });

  /* ----------------------------------------------------------
     Sidebar dropdowns (version + language switchers)
     ---------------------------------------------------------- */
  const initSwitcher = (rootSelector, triggerSelector, menuSelector) => {
    const root = document.querySelector(rootSelector);
    if (!root) return;

    const trigger = root.querySelector(triggerSelector);
    const menu = root.querySelector(menuSelector);

    const close = () => {
      root.classList.remove('is-open');
      trigger?.setAttribute('aria-expanded', 'false');
      if (menu) menu.hidden = true;
    };

    trigger?.addEventListener('click', (event) => {
      event.stopPropagation();
      const isOpen = root.classList.toggle('is-open');
      trigger.setAttribute('aria-expanded', String(isOpen));
      if (menu) menu.hidden = !isOpen;
    });

    document.addEventListener('click', (event) => {
      if (!root.contains(event.target)) close();
    });

    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape') close();
    });
  };

  initSwitcher('[data-version-switcher]', '[data-version-trigger]', '[data-version-menu]');
  initSwitcher('[data-locale-switcher]', '[data-locale-trigger]', '[data-locale-menu]');

  /* ----------------------------------------------------------
     Collapsible sidebar sections (active section stays open)
     ---------------------------------------------------------- */
  (() => {
    let collapsed = new Set();
    try {
      collapsed = new Set(JSON.parse(localStorage.getItem('wire-docs-nav-collapsed') || '[]'));
    } catch {}

    document.querySelectorAll('.sidebar-nav .nav-section').forEach((section) => {
      const heading = section.querySelector('h2');
      const list = section.querySelector('ul');
      if (!heading || !list) return;

      const key = heading.textContent.trim();
      const hasActive = !!section.querySelector('a.is-active');

      const chevron = document.createElement('span');
      chevron.className = 'nav-section-chevron';
      chevron.setAttribute('aria-hidden', 'true');
      heading.appendChild(chevron);
      heading.setAttribute('role', 'button');
      heading.setAttribute('tabindex', '0');

      if (collapsed.has(key) && !hasActive) section.classList.add('is-collapsed');

      const toggle = () => {
        const isCollapsed = section.classList.toggle('is-collapsed');
        if (isCollapsed) collapsed.add(key); else collapsed.delete(key);
        try {
          localStorage.setItem('wire-docs-nav-collapsed', JSON.stringify([...collapsed]));
        } catch {}
      };

      heading.addEventListener('click', toggle);
      heading.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' || event.key === ' ') {
          event.preventDefault();
          toggle();
        }
      });
    });

    // Bring the active nav item into view inside the sidebar on load.
    const active = document.querySelector('.sidebar-nav a.is-active');
    active?.scrollIntoView({ block: 'center' });
  })();

  /* ----------------------------------------------------------
     Reading progress bar
     ---------------------------------------------------------- */
  const progressBar = document.querySelector('[data-reading-progress]');
  if (progressBar) {
    let ticking = false;
    const updateProgress = () => {
      const max = document.documentElement.scrollHeight - window.innerHeight;
      const pct = max > 0 ? Math.min(100, (window.scrollY / max) * 100) : 0;
      progressBar.style.width = pct + '%';
      ticking = false;
    };
    window.addEventListener('scroll', () => {
      if (!ticking) {
        window.requestAnimationFrame(updateProgress);
        ticking = true;
      }
    }, { passive: true });
    updateProgress();
  }

  /* ----------------------------------------------------------
     Back to top
     ---------------------------------------------------------- */
  const backToTop = document.querySelector('[data-back-to-top]');
  if (backToTop) {
    const toggleBackToTop = () => {
      backToTop.classList.toggle('is-visible', window.scrollY > 600);
    };
    window.addEventListener('scroll', toggleBackToTop, { passive: true });
    backToTop.addEventListener('click', () => {
      window.scrollTo({ top: 0, behavior: 'smooth' });
    });
    toggleBackToTop();
  }

  /* ----------------------------------------------------------
     Wrap wide tables so they scroll horizontally on small screens
     ---------------------------------------------------------- */
  document.querySelectorAll('.docs-article table').forEach((table) => {
    if (table.closest('.table-scroll')) return;
    const wrapper = document.createElement('div');
    wrapper.className = 'table-scroll';
    table.parentNode.insertBefore(wrapper, table);
    wrapper.appendChild(table);
  });

  /* ----------------------------------------------------------
     Copy-to-clipboard for code blocks
     ---------------------------------------------------------- */
  const copyIcon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>';

  // Chrome the scripts render themselves still has to speak the page's language.
  const copyLabel = body.dataset.copyLabel || 'Copy';
  const copiedLabel = body.dataset.copiedLabel || 'Copied';
  const copyAriaLabel = body.dataset.copyAriaLabel || 'Copy code';

  document.querySelectorAll('.docs-article pre').forEach((pre) => {
    const wrapper = document.createElement('div');
    wrapper.className = 'code-block';
    pre.parentNode.insertBefore(wrapper, pre);
    wrapper.appendChild(pre);

    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'copy-button';
    button.innerHTML = `${copyIcon}<span></span>`;
    button.querySelector('span').textContent = copyLabel;
    button.setAttribute('aria-label', copyAriaLabel);
    wrapper.appendChild(button);

    button.addEventListener('click', async () => {
      const code = pre.querySelector('code')?.innerText ?? pre.innerText;
      try {
        await navigator.clipboard.writeText(code);
      } catch {
        const range = document.createRange();
        range.selectNodeContents(pre);
        const sel = window.getSelection();
        sel.removeAllRanges();
        sel.addRange(range);
        document.execCommand('copy');
        sel.removeAllRanges();
      }
      button.classList.add('is-copied');
      button.querySelector('span').textContent = copiedLabel;
      setTimeout(() => {
        button.classList.remove('is-copied');
        button.querySelector('span').textContent = copyLabel;
      }, 1600);
    });
  });

  /* ----------------------------------------------------------
     Heading anchor links
     ---------------------------------------------------------- */
  document.querySelectorAll('.docs-article h2[id], .docs-article h3[id], .docs-article h4[id]').forEach((heading) => {
    const anchor = document.createElement('a');
    anchor.className = 'heading-anchor';
    anchor.href = `#${heading.id}`;
    anchor.setAttribute('aria-label', 'Link to this section');
    anchor.textContent = '#';
    heading.prepend(anchor);
  });

  /* ----------------------------------------------------------
     Scrollspy for the table of contents
     ---------------------------------------------------------- */
  const tocLinks = Array.from(document.querySelectorAll('.page-toc a'));

  if (tocLinks.length) {
    const map = new Map();
    tocLinks.forEach((link) => {
      const id = decodeURIComponent(link.getAttribute('href').slice(1));
      const target = document.getElementById(id);
      if (target) map.set(target, link);
    });

    let activeLink = null;
    const setActive = (link) => {
      if (link === activeLink) return;
      activeLink?.classList.remove('is-active');
      link?.classList.add('is-active');
      activeLink = link;
    };

    const observer = new IntersectionObserver((entries) => {
      const visible = entries
        .filter((e) => e.isIntersecting)
        .sort((a, b) => a.boundingClientRect.top - b.boundingClientRect.top);
      if (visible.length) {
        setActive(map.get(visible[0].target));
      }
    }, { rootMargin: '-80px 0px -70% 0px', threshold: 0 });

    map.forEach((_, target) => observer.observe(target));
  }

  /* ----------------------------------------------------------
     Search
     ---------------------------------------------------------- */
  const searchInput = document.querySelector('[data-search-input]');
  const searchResults = document.querySelector('[data-search-results]');
  const searchIndexUrl = root.getAttribute('data-search-index');
  const baseUrl = searchIndexUrl ? new URL(searchIndexUrl, window.location.href) : null;

  const openSearch = () => {
    body.classList.add('search-open');
    // Defer focus so the mobile sheet is visible before focusing.
    requestAnimationFrame(() => {
      searchInput?.focus();
      searchInput?.select();
    });
  };

  const closeSearch = () => {
    body.classList.remove('search-open');
    if (searchResults) searchResults.hidden = true;
    searchInput?.blur();
  };

  document.querySelector('[data-search-open]')?.addEventListener('click', openSearch);
  document.querySelectorAll('[data-search-close]').forEach((el) => {
    el.addEventListener('click', closeSearch);
  });

  // ⌘K / Ctrl+K opens & focuses search
  document.addEventListener('keydown', (event) => {
    if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') {
      event.preventDefault();
      openSearch();
    }
    if (event.key === 'Escape' && (document.activeElement === searchInput || body.classList.contains('search-open'))) {
      closeSearch();
    }
  });

  if (!searchInput || !searchResults || !searchIndexUrl) {
    return;
  }

  let index = [];
  let matches = [];
  let activeIndex = -1;

  /*
   * The index is a few hundred KB and most visits never search, so it is
   * fetched on the first sign of intent (opening, focusing or typing into the
   * field) rather than on every page load. Whoever asks first triggers it; the
   * promise is shared, and a pending fetch re-runs the search when it lands.
   */
  let indexRequest = null;
  const loadIndex = () => {
    indexRequest ??= fetch(searchIndexUrl)
      .then((r) => r.json())
      .then((payload) => { index = Array.isArray(payload) ? payload : []; })
      .catch(() => { index = []; });
    return indexRequest;
  };

  const resolveUrl = (value) => {
    if (!baseUrl) return value;
    try { return new URL(value, baseUrl).href; } catch { return value; }
  };

  const escapeHtml = (value) => String(value)
    .replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;').replaceAll("'", '&#39;');

  /*
   * Fold case and diacritics so the Czech docs are searchable from a plain
   * keyboard: "prehled" has to find "Přehled", and "rozsireni" "rozšíření".
   */
  const fold = (value) => String(value)
    .toLowerCase()
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '');

  const foldedOf = (item) => {
    if (!item._f) {
      item._f = {
        title: fold(item.title),
        section: fold(item.section),
        excerpt: fold(item.excerpt),
        text: fold(item.text ?? ''),
        headings: (item.headings ?? []).map(([id, text]) => [id, text, fold(text)]),
      };
    }
    return item._f;
  };

  /*
   * Where a term matched matters far more than that it matched: a query for
   * "column" belongs on the Columns page, not on every page that mentions one
   * in passing. Field weights, plus a bonus for matching a whole word or the
   * start of one, are what turn the substring filter into a ranking.
   */
  const scoreField = (haystack, term, weight) => {
    const at = haystack.indexOf(term);
    if (at < 0) return 0;
    const before = at === 0 ? '' : haystack[at - 1];
    const after = haystack[at + term.length] ?? '';
    const startsWord = at === 0 || /[^a-z0-9]/.test(before);
    // A trailing plural still counts as the whole word, so searching "column"
    // ranks the Columns page as highly as a page titled "Column Reordering".
    const endsWord = after === '' || /[^a-z0-9]/.test(after)
      || /^e?s([^a-z0-9]|$)/.test(haystack.slice(at + term.length));
    let score = weight;
    if (startsWord) score += weight * 0.5;
    if (startsWord && endsWord) score += weight * 0.5;
    return score;
  };

  const scoreItem = (item, terms) => {
    const f = foldedOf(item);
    let total = 0;
    let anchor = null;
    let anchorScore = 0;

    for (const term of terms) {
      let best = 0;

      if (f.title === term) best = 220;
      best = Math.max(best, scoreField(f.title, term, 60));
      best = Math.max(best, scoreField(f.section, term, 14));
      best = Math.max(best, scoreField(f.excerpt, term, 12));

      for (const [id, text, folded] of f.headings) {
        const headingScore = scoreField(folded, term, 26);
        if (headingScore > 0) {
          best = Math.max(best, headingScore);
          if (headingScore > anchorScore) {
            anchorScore = headingScore;
            anchor = { id, text };
          }
        }
      }

      const bodyScore = scoreField(f.text, term, 6);
      best = Math.max(best, bodyScore);

      // Every term has to appear somewhere — the AND semantics people expect
      // from a multi-word query.
      if (best === 0) return null;
      total += best;
    }

    return { item, score: total, anchor };
  };

  // A snippet from around the first body hit says more than the page excerpt
  // repeated eight times, so results show where the term actually lives.
  const snippetFor = (item, terms) => {
    const f = foldedOf(item);
    const text = item.text ?? '';
    let at = -1;
    let hit = '';
    for (const term of terms) {
      const found = f.text.indexOf(term);
      if (found >= 0 && (at < 0 || found < at)) { at = found; hit = term; }
    }
    if (at < 0) return item.excerpt ?? '';
    const start = Math.max(0, at - 40);
    const end = Math.min(text.length, at + hit.length + 90);
    return (start > 0 ? '…' : '') + text.slice(start, end).trim() + (end < text.length ? '…' : '');
  };

  const highlight = (value, terms) => {
    const folded = fold(value);
    // Folding is character-for-character for everything these docs contain, so
    // an offset found in the folded string addresses the same character in the
    // original. If some exotic character ever breaks that, show it unmarked
    // rather than slicing the string in the wrong place.
    if (folded.length !== value.length) return escapeHtml(value);
    const spans = [];
    for (const term of terms) {
      let from = 0;
      for (;;) {
        const at = folded.indexOf(term, from);
        if (at < 0) break;
        spans.push([at, at + term.length]);
        from = at + term.length;
      }
    }
    if (!spans.length) return escapeHtml(value);

    // Fold() is 1:1 on characters (NFD marks are dropped, never letters), so an
    // offset in the folded string still addresses the same character here.
    spans.sort((a, b) => a[0] - b[0]);
    let out = '';
    let cursor = 0;
    for (const [start, end] of spans) {
      if (start < cursor) continue;
      out += escapeHtml(value.slice(cursor, start));
      out += `<mark>${escapeHtml(value.slice(start, end))}</mark>`;
      cursor = end;
    }
    return out + escapeHtml(value.slice(cursor));
  };

  const emptyTitle = searchResults.dataset.emptyTitle || 'No matches';
  const emptyHint = searchResults.dataset.emptyHint || 'Try a package name, field type, or API term.';

  const render = (terms = []) => {
    if (!matches.length) {
      searchResults.innerHTML = `<div class="search-result"><strong>${escapeHtml(emptyTitle)}</strong><small>${escapeHtml(emptyHint)}</small></div>`;
      searchResults.hidden = false;
      return;
    }
    searchResults.innerHTML = matches.map((match, i) => {
      const item = match.item;
      const href = resolveUrl(item.url) + (match.anchor ? `#${match.anchor.id}` : '');
      const context = match.anchor ? `${item.section} · ${match.anchor.text}` : item.section;
      return `
      <a class="search-result${i === activeIndex ? ' is-active' : ''}" href="${href}">
        <strong>${highlight(item.title, terms)}</strong>
        <small>${escapeHtml(context)}${item.text ? ' · ' + highlight(snippetFor(item, terms), terms) : ''}</small>
      </a>`;
    }).join('');
    searchResults.hidden = false;
  };

  const runSearch = () => {
    const query = fold(searchInput.value.trim());
    activeIndex = -1;
    if (query.length < 2) {
      searchResults.hidden = true;
      searchResults.innerHTML = '';
      return;
    }

    // Nothing to search yet: pull the index and come back with the answer.
    if (!index.length) {
      loadIndex().then(() => {
        if (index.length && fold(searchInput.value.trim()) === query) runSearch();
      });
    }

    const terms = query.split(/\s+/);
    matches = index
      .map((item) => scoreItem(item, terms))
      .filter(Boolean)
      // Equal scores: the shorter title is the more direct answer ("Columns"
      // before "Editing & Column-Level Filters").
      .sort((a, b) => b.score - a.score
        || a.item.title.length - b.item.title.length
        || a.item.title.localeCompare(b.item.title))
      .slice(0, 8);
    render(terms);
  };

  searchInput.addEventListener('input', runSearch);
  searchInput.addEventListener('focus', () => {
    loadIndex();
    if (searchInput.value.trim().length >= 2) runSearch();
  });
  document.querySelector('[data-search-open]')?.addEventListener('click', loadIndex);

  searchInput.addEventListener('keydown', (event) => {
    if (searchResults.hidden || !matches.length) return;
    if (event.key === 'ArrowDown') {
      event.preventDefault();
      activeIndex = (activeIndex + 1) % matches.length;
      render();
    } else if (event.key === 'ArrowUp') {
      event.preventDefault();
      activeIndex = (activeIndex - 1 + matches.length) % matches.length;
      render();
    } else if (event.key === 'Enter' && activeIndex >= 0) {
      event.preventDefault();
      window.location.href = resolveUrl(matches[activeIndex].url);
    }
  });

  document.addEventListener('click', (event) => {
    if (!searchResults.contains(event.target) && event.target !== searchInput) {
      searchResults.hidden = true;
    }
  });

  /* ----------------------------------------------------------
     Motion — GSAP entrance + scroll reveal (opt-in via .has-motion,
     set in <head> only when prefers-reduced-motion is not requested)
     ---------------------------------------------------------- */
  (() => {
    const gsap = window.gsap;

    // If motion is off, or GSAP did not load, reveal everything the CSS hid.
    if (!root.classList.contains('has-motion') || !gsap) {
      root.classList.remove('has-motion');
      return;
    }

    const ScrollTrigger = window.ScrollTrigger;
    if (ScrollTrigger) gsap.registerPlugin(ScrollTrigger);

    // Reveal a batch of elements as they scroll into view (or immediately when
    // ScrollTrigger is unavailable), with a soft stagger.
    const batch = (selector, y = 26) => {
      const els = gsap.utils.toArray(selector);
      if (!els.length) return;
      gsap.set(els, { opacity: 0, y });

      if (!ScrollTrigger) {
        gsap.to(els, { opacity: 1, y: 0, duration: 0.7, ease: 'power3.out', stagger: 0.08 });
        return;
      }

      ScrollTrigger.batch(els, {
        start: 'top 90%',
        onEnter: (group) => gsap.to(group, {
          opacity: 1, y: 0, duration: 0.7, ease: 'power3.out', stagger: 0.09, overwrite: true,
        }),
      });
    };

    // Home hero: a single entrance timeline on load.
    if (document.querySelector('.home-hero-inner')) {
      const bits = ['.hero-badge', '.home-hero h1', '.home-hero .lead', '.hero-actions > *'];
      gsap.set(bits, { opacity: 0, y: 22 });
      gsap.timeline({ defaults: { ease: 'power3.out', duration: 0.85 } })
        .to('.hero-badge', { opacity: 1, y: 0 })
        .to('.home-hero h1', { opacity: 1, y: 0 }, '-=0.6')
        .to('.home-hero .lead', { opacity: 1, y: 0 }, '-=0.6')
        .to('.hero-actions > *', { opacity: 1, y: 0, stagger: 0.09 }, '-=0.55');
    }

    // Home sections + card grids.
    batch('.section-intro', 20);
    batch('.feature-card');
    batch('.preview-card');
    batch('.stat-card', 18);
    batch('.overview-panel');

    // Doc pages: gentle load fade-up, per-heading scroll reveal, TOC slide-in.
    const article = document.querySelector('.docs-article');
    if (article) {
      const hero = article.querySelector('.page-hero');
      if (hero) {
        gsap.set(hero, { opacity: 0, y: 20 });
        gsap.to(hero, { opacity: 1, y: 0, duration: 0.75, ease: 'power3.out' });
      }
      batch('.docs-article > h2', 16);

      const toc = document.querySelector('.page-toc');
      if (toc) {
        gsap.set(toc, { opacity: 0, x: 16 });
        gsap.to(toc, { opacity: 1, x: 0, duration: 0.75, ease: 'power3.out', delay: 0.15 });
      }
    }

    if (ScrollTrigger) ScrollTrigger.refresh();
  })();
})();
