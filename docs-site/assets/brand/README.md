# WireStack brand

One mark for the whole stack — the packages have no icons of their own.

Everything in this folder is **generated**. Change the source, then rebuild:

```bash
python3 docs-site/scripts/brand/generate.py   # vectors + the inline mark in templates/partials/brand-mark.php
python3 docs-site/scripts/brand/raster.py     # PNGs, through headless Chrome (the wordmark is a webfont)
```

The design reference is the WireStack brand sheet (kept outside the repository);
the shape and shading below were measured from it.

## The mark

A single ribbon folded into a zigzag: five bands, rising – falling – rising –
falling – rising. Where two bands meet they share one rounded tip. The top end
is cut vertically, the bottom end is a semicircle square to the band.

**It is layered like folded paper:** every segment lies *under* the one before
it, so the drawing is painted from the bottom band up. Depth comes from three
things, all measured in the reference:

- a soft **shadow** each rising band casts on the falling band beneath it —
  darkest right at the edge, gone within ~3.6 units;
- the top band being slightly **see-through**, so the first falling band shows
  as a faint wedge inside it (fading in, no hard edge);
- the falling bands sitting a step **darker** than the rising ones on the same
  colour ramp.

The bands are not uniform: they thin out towards the bottom (48, 40, 44, 44,
36 px in the reference) and their slopes run 0.39–0.42. Averaging them made the
notches shallow and moved the folds, so every edge is its own fitted line.

**How it was measured.** Every edge was first fitted from the points where the
reference turns from white to ink, then all of the numbers were refined by
coordinate descent against the reference, scoring the number of pixels where the
two silhouettes differ: 387 → 84 of 27 000, about 0.1 px of mean outline error —
below what the reference itself resolves. The interior was checked the same way,
with a false-colour luminance map and colour profiles across each fold.

| File | Use |
| --- | --- |
| `wirestack-mark.svg` | Default, on light and dark grounds |
| `wirestack-mark-dark.svg` | Same drawing; kept as a stable name for dark contexts |
| `wirestack-mark-mono.svg` | One colour via `currentColor`; the layers are split by thin gaps, since a shadow cannot show in one colour |
| `wirestack-mark-micro.svg` | 16–24 px: layers and colours, no shadows (a blur smears at that size) |
| `favicon.svg`, `favicon-*.png`, `apple-touch-icon.png` | Browser icons |
| `github/*` | Avatar, README banners (light/dark), 1280×640 social card |

**Clear space:** the height of one band on every side. **Minimum size:** 16 px
for the mark (use the micro variant below 24 px), 120 px wide for mark + wordmark.

## Colour

**Warm, to match the docs site:** yellow → orange → red. The site's `--primary`
is amber-500 `#F59E0B` and its `--brand-grad` (`docs-site/assets/site.css`) runs
`#FBBF24` → `#F97316` → `#EF4444` — the same tones as the mark. The reference's
blue-violet colours are not used; only its shape and shading are.

The mark is coloured by **one ramp across the whole drawing**, top to bottom
with a slight tilt (`RAMP_AXIS`), lightest at the top right:

`#FBBF24` (amber-400) → `#F8AD17` → `#F59E0B` (amber-500) → `#F7850F` →
`#F97316` (orange-500) → `#F35A2C` → `#EF4444` (red-500)

Every anchor is a Tailwind step or the midpoint of two, so the vocabulary stays
inside what ADR 0005 guarantees. One ramp rather than a colour per band keeps
the flow smooth; the layers stay legible because the falling bands take the
ramp a little further along and 10 % darker towards orange-900 (`BAND_TONE`),
and the shadows on them are `#D35A0A` and `#C2380F`.

A more diagonal axis (`52,5 → 24,60`) was tried and rejected: it gives the left
end of the top band the middle band's tone.

The GitHub set keeps the warm neutrals it had: ground `#131210` / `#FAF9F6`,
text `#F4F1EA` / `#1A1815`, secondary `#A29A8C` / `#6D675C`.

## Type

Inter throughout. The wordmark is `WireStack` in one word, weight 700, tracking
−3 %. The tagline is `BUILD · CONNECT · SIMPLIFY`, weight 500, uppercase,
tracking +20 %, in the warm secondary grey.
