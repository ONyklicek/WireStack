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
     Copy-to-clipboard for code blocks
     ---------------------------------------------------------- */
  const copyIcon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>';

  document.querySelectorAll('.docs-article pre').forEach((pre) => {
    const wrapper = document.createElement('div');
    wrapper.className = 'code-block';
    pre.parentNode.insertBefore(wrapper, pre);
    wrapper.appendChild(pre);

    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'copy-button';
    button.innerHTML = `${copyIcon}<span>Copy</span>`;
    button.setAttribute('aria-label', 'Copy code');
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
      button.querySelector('span').textContent = 'Copied';
      setTimeout(() => {
        button.classList.remove('is-copied');
        button.querySelector('span').textContent = 'Copy';
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

  // ⌘K / Ctrl+K focuses search
  document.addEventListener('keydown', (event) => {
    if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') {
      event.preventDefault();
      searchInput?.focus();
      searchInput?.select();
    }
    if (event.key === 'Escape' && document.activeElement === searchInput) {
      searchInput.blur();
      if (searchResults) searchResults.hidden = true;
    }
  });

  if (!searchInput || !searchResults || !searchIndexUrl) {
    return;
  }

  let index = [];
  let matches = [];
  let activeIndex = -1;

  fetch(searchIndexUrl)
    .then((r) => r.json())
    .then((payload) => { index = Array.isArray(payload) ? payload : []; })
    .catch(() => { index = []; });

  const resolveUrl = (value) => {
    if (!baseUrl) return value;
    try { return new URL(value, baseUrl).href; } catch { return value; }
  };

  const escapeHtml = (value) => String(value)
    .replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;').replaceAll("'", '&#39;');

  const render = () => {
    if (!matches.length) {
      searchResults.innerHTML = '<div class="search-result"><strong>No matches</strong><small>Try a package name, field type, or API term.</small></div>';
      searchResults.hidden = false;
      return;
    }
    searchResults.innerHTML = matches.map((item, i) => `
      <a class="search-result${i === activeIndex ? ' is-active' : ''}" href="${resolveUrl(item.url)}">
        <strong>${escapeHtml(item.title)}</strong>
        <small>${escapeHtml(item.section)}${item.excerpt ? ' · ' + escapeHtml(item.excerpt) : ''}</small>
      </a>`).join('');
    searchResults.hidden = false;
  };

  const runSearch = () => {
    const query = searchInput.value.trim().toLowerCase();
    activeIndex = -1;
    if (query.length < 2) {
      searchResults.hidden = true;
      searchResults.innerHTML = '';
      return;
    }
    const terms = query.split(/\s+/);
    matches = index
      .filter((item) => {
        const haystack = `${item.title} ${item.section} ${item.excerpt} ${item.text}`.toLowerCase();
        return terms.every((t) => haystack.includes(t));
      })
      .slice(0, 8);
    render();
  };

  searchInput.addEventListener('input', runSearch);
  searchInput.addEventListener('focus', () => {
    if (searchInput.value.trim().length >= 2) runSearch();
  });

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
})();
