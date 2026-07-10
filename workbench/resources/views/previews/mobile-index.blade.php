<!doctype html>
<html lang="cs">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>Živé mobilní náhledy — wireStack</title>
<style>
  :root { color-scheme: light dark; }
  * { box-sizing: border-box; }
  body { margin: 0; font: 15px/1.55 -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #eef2f9; color: #1e293b; padding-bottom: env(safe-area-inset-bottom); }
  header { padding: 22px 20px; background: #0f172a; color: #e2e8f0; }
  header h1 { margin: 0 0 6px; font-size: 20px; }
  header p { margin: 0; color: #94a3b8; font-size: 13px; }
  header code { background: #1e293b; padding: 1px 6px; border-radius: 5px; color: #7dd3fc; }
  main { max-width: 860px; margin: 0 auto; padding: 16px 16px 60px; }
  .tips { background: #fff7ed; border: 1px solid #fed7aa; border-radius: 12px; padding: 12px 14px; margin: 16px 0; font-size: 13.5px; color: #7c2d12; }
  .tips b { color: #9a3412; }
  h2 { font-size: 15px; margin: 28px 0 6px; color: #334155; }
  .grid { display: grid; grid-template-columns: 1fr; gap: 10px; }
  a.card { display: block; background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 13px 15px; text-decoration: none; color: inherit; box-shadow: 0 2px 8px rgba(148,163,184,.12); transition: transform .1s, box-shadow .1s; }
  a.card:active { transform: scale(.99); }
  a.card:hover { box-shadow: 0 6px 18px rgba(99,102,241,.18); border-color: #c7d2fe; }
  .card .t { font-weight: 600; font-size: 14.5px; }
  .card .t .w { font-weight: 400; color: #94a3b8; font-size: 12px; margin-left: 6px; }
  .card .d { color: #64748b; font-size: 13px; margin-top: 3px; }
  .card .how { color: #6366f1; font-size: 12px; margin-top: 5px; font-weight: 500; }
  .pill { display: inline-block; font-size: 10.5px; font-weight: 700; padding: 1px 7px; border-radius: 999px; background: #e0e7ff; color: #4338ca; margin-left: 6px; vertical-align: middle; }
</style>
</head>
<body>
<header>
  <h1>Živé mobilní náhledy</h1>
  <p>Otevři na telefonu: <code>{{ $lanUrl }}</code> · na Macu použij DevTools device mode (⌥⌘M).</p>
</header>
<main>
  <div class="tips">
    <b>Jak testovat:</b> zúž okno / zapni device toolbar. <b>390&nbsp;px</b> = telefon (sheet), <b>1400&nbsp;px</b> = desktop (floating).
    Pro <b>tablet</b> použij <i>-tablet</i> náhledy (vynutí breakpoint <code>md</code>) na <b>~700&nbsp;px</b>.
    Zapni <b>touch emulaci</b> pro grabber (tažení dolů zavře sheet). A11y: <b>Tab</b> cykluje v sheetu, <b>Esc</b> zavře, fokus se vrátí na trigger.
  </div>

  @php
    $card = function (string $slug, string $title, string $width, string $desc, string $how) {
        echo '<a class="card" href="/previews/'.$slug.'"><div class="t">'.$title.'<span class="w">'.$width.'</span></div><div class="d">'.$desc.'</div><div class="how">'.$how.'</div></a>';
    };
  @endphp

  <h2>Sheet vs. floating (tentýž Select)</h2>
  <div class="grid">
    @php $card('field-select', 'Select — non-searchable', '390px → sheet', 'Krátký výběr → bottom sheet na telefonu.', 'Otevři výběr, tahni grabber dolů, zkus Tab/Esc.'); @endphp
    @php $card('field-select-floating', 'Select — searchable', '390px → floating', 'Searchable select zůstává klasický floating (search u pole).', 'Napiš do search inputu.'); @endphp
  </div>

  <h2>Tabulkové dropdowny → sheet na telefonu</h2>
  <div class="grid">
    @php $card('table-actions-group', 'Řádkové akce (⋯)', '390px', 'Action-group menu → bottom sheet + backdrop + grabber.', 'Klikni ⋯ v řádku (odscrolluj doprava). Esc/tap mimo zavře.'); @endphp
    @php $card('table-overview', 'Filtry + column-toggle', '390px', 'Filter panel a přepínač sloupců → bottom sheet.', 'Klikni „Filters" nebo ikonu sloupců vpravo nahoře.'); @endphp
  </div>

  <h2>Formulářová pole</h2>
  <div class="grid">
    @php $card('field-date-time-picker', 'Date-time picker', '390px', 'Kalendář přes celou šířku, cap výšky, scroll uvnitř.', 'Klikni do pole.'); @endphp
    @php $card('field-tags', 'Tags', '390px', 'Našeptávač jako sheet (bez focus-trapu — combobox).', 'Napiš písmeno „a".'); @endphp
    @php $card('field-checkbox-list', 'Checkbox list', '390px', 'Sloupce se přeskládají (2 → 1) na telefonu.', 'Porovnej 390 vs 1400 px.'); @endphp
    @php $card('field-radio-color', 'Radio inline-cards', '390px', 'Inline karty se stackují místo mačkání do řady.', 'Porovnej 390 vs 1400 px.'); @endphp
  </div>

  <h2>Action modaly</h2>
  <div class="grid">
    @php $card('table-modal-slideover-mobile', 'slideOverOnMobile', '390px', 'Form modal → bottom sheet zespoda.', 'Modal se otevře sám.'); @endphp
    @php $card('table-modal-fullscreen-mobile', 'fullScreenOnMobile', '390px', 'Modal přes celou obrazovku.', 'Modal se otevře sám.'); @endphp
    @php $card('table-modal-slideover-compose', 'compose (slideOver + onMobile)', '390 / 1400', 'Mobil = sheet, desktop = slide-over zprava.', 'Přepínej šířku.'); @endphp
  </div>

  <h2>Tablet breakpoint <span class="pill">md · ~700px</span></h2>
  <div class="grid">
    @php $card('table-actions-group-tablet', 'Akce ⋯ — tablet (md)', '~700px → sheet', 'Na tabletu (< 768) sheet místo floating.', 'Nastav ~700 px a otevři ⋯.'); @endphp
    @php $card('table-actions-group', 'Akce ⋯ — default (sm)', '~700px → floating', 'Porovnání: na 700 px default = floating.', 'Nastav ~700 px, srovnej s předchozím.'); @endphp
    @php $card('table-modal-slideover-mobile-tablet', 'Modal — tablet (md)', '~700px → sheet', 'slideOverOnMobile modal jako sheet i na tabletu.', 'Nastav ~700 px.'); @endphp
    @php $card('table-modal-slideover-compose-tablet', 'Compose slide-over — tablet (md)', '~700px → sheet', 'Compose slide-over jako full-width sheet do 768.', 'Nastav ~700 px.'); @endphp
  </div>

  <h2>Obecný dropdown</h2>
  <div class="grid">
    @php $card('core-dropdown', 'x-wire::dropdown', '390px', 'Generický dropdown → sheet (opt-in prop).', 'Klikni „Options".'); @endphp
  </div>
</main>
</body>
</html>
