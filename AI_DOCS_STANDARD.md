# WireStack Documentation Standard

Binding standard for everything under `docs/`, human- or AI-authored. Where this
file and habit disagree, this file wins. `CLAUDE.md` routes here; read it before
writing documentation, not after. Code is governed by
[`AI_CODING_STANDARD.md`](AI_CODING_STANDARD.md) — this file governs the pages
that explain it.

## Philosophy

**A page earns its place by explaining how something works, not by listing that
it exists.**

A reader arrives with a task and a half-formed model of the framework. A list of
method names does not fix the model — it only tells them the names of things
they still do not understand. So every page owes three things, in this order:

1. **The mechanism.** What runs, when, in what order, and what wins when two
   things disagree. Resolution order, fallbacks, defaults, server vs client,
   what a call costs.
2. **The complete fluent surface.** Every configuration method the class
   declares, with the types it accepts and what it does — nothing omitted
   because it seemed obvious.
3. **Examples that survive being copied.** Real, runnable, in context, with the
   lines that matter spotlighted so the reader's eye lands where the prose is
   pointing.

Documentation that only satisfies (2) is a signature dump. Documentation that
only satisfies (3) is a cookbook. The framework needs all three on every page.

## Rules

Numbered so a review can cite them. **S**-rules are checked by
`npm run docs:standard`; the rest are review obligations.

### D1 — Structure follows the page's kind

Three kinds of page, three shapes. Do not invent a fourth.

**Class reference page** (`BadgeColumn`, `SelectFilter`, `TextInput`, …):

```text
# ClassName                     <- H1 is the class short name, nothing else
one-paragraph statement of what it is and when to reach for it
```php use NyonCode\...\ClassName; ```   <- the import, on its own, immediately
## How It Works                 <- the mechanism (D2). Not optional.
## Basic Usage                  <- the smallest example that does something real
## <Capability sections>        <- one per capability, each with an example
## Extended Example             <- one full, in-context example (D4)
## ClassName API                <- the complete fluent surface (D3)
## Related                      <- links to the pages a reader needs next
```

The H1 + import pair is not decoration: `docs-site/scripts/verify-api-docs.php`
uses exactly that signal to decide which class a page is the reference for.

**Guide** (`authorization.md`, `testing.md`, save lifecycle, gestures …): opens
with what the guide gets you, then works through the topic in the order a reader
meets it. It documents a *flow*, so it carries no `## X API` section — the
classes it touches have their own pages, which it links to.

**Overview / index page** (`table/overview.md`, `forms/fields/index.md`): the map
of a section. Quick start first, then the shape of the subsystem, then a table
of the pages beneath it. Every child page must be reachable from it.

### D2 — Mechanism before signatures

Every reference page carries a `## How It Works` section that answers, for that
class, whichever of these apply:

- **Resolution order.** What is consulted first, second, last —
  `->colors()` map, then the enum's `HasColor` contract, then `->color()`, then
  `gray`. Write the chain, not "it can also come from an enum".
- **Defaults.** What happens when you configure nothing.
- **Where it runs.** Server render, Livewire roundtrip, or Alpine in the browser
  — and what that means for closures, state and cost.
- **What it touches.** Query impact (a join? a `whereHas`? N+1 risk?), state it
  persists, events it fires.
- **The traps.** The mistake the maintainers actually made or fixed. If a bug
  was worth fixing, its cause is worth one sentence here.

If a behaviour is decided in code by a `match`, an `??` chain or a guard clause,
that decision belongs in prose on the page. The reader cannot see your `match`.

### D3 — The API section is complete and typed

Reference pages end with the full configuration surface, in the canonical
code-block form (the corpus uses it 107 pages to 4; it greps, highlights and
copy-pastes):

```php
->colors(array $map)                 // ['state' => 'color_name'|Color, ...]
->colorUsing(Closure $fn)            // fn ($state) => 'color_name'|Color|null
->size(string|Size $size)            // 'xs'|'sm'|'md'|'lg'|'xl' — default 'md'
->getColorForState($state): ?string
```

Binding details:

- **One method per line, starting with `->`.** The API gate parses this form.
- **Complete.** Every public fluent setter the class *declares* must appear.
  Inherited and trait-provided configuration is documented centrally (the shared
  field/column API pages), never copied per page. `verify-api-docs.php` enforces
  this in both directions — an undocumented method and a documented method that
  does not exist both fail.
- **Typed.** Real parameter types, including unions (`string|Icon`) and
  nullability. If a method takes a closure, the comment gives the closure's
  signature.
- **The comment carries the vocabulary and the default.** Closed sets are listed
  (`'xs'|'sm'|'md'|'lg'`), defaults are stated (`— default 'md'`).
- **Getters last**, with their return type, and only the ones a user calls.
- A method that is deliberately undocumented is marked `@docs-ignore` in its
  docblock — in the code, where the next reader of the code will see it.

Tables are for matrices, not for the API surface: use one only when a row needs
more than a signature and a note (for example an "On" column naming which class
of a pair owns the method).

### D4 — Examples are extended, real, and in context

- **At least one example per capability section**, and one `## Extended Example`
  per reference page showing the class inside a real host — a Livewire component
  with `use WithTable;`, a form class, a real model — not a floating fragment.
- **Runnable.** Imports present, class and method scaffolding present, real
  model and column names. A reader must be able to paste it and run it.
- **Show the wiring once, then stop.** Sections after the first may drop the
  host component and show the chain alone, once the extended example has
  established where it lives.
- **Comment the non-obvious argument**, not the language. `// null keeps the
  previous page's order` earns its place; `// set the label` does not.
- **State maps read state-first.** `['active' => 'success']` — the state is the
  key, the colour is the value. Both `->colors()` and `->icons()` have shipped
  backwards in these docs; `verify-api-docs.php` now detects it.

### S1 — Long examples spotlight what they are about

Any PHP or Blade block of **12 lines or more** must use Torchlight focus so the
lines under discussion stay bright and the scaffolding dims:

```php
class ListUsers extends Component
{
    use WithTable;

    public function table(Table $table): Table
    {
        return $table
            ->model(User::class)
            ->columns([
                BadgeColumn::make('status')      // [tl! focus:start]
                    ->colors(['active' => 'success', 'banned' => 'danger'])
                    ->icons(['banned' => 'x-circle']), // [tl! focus:end]
            ]);
    }
}
```

Rules for the spotlight:

- **Focus the answer, dim the scaffold.** Imports, class declaration and the
  `table()` boilerplate are context; the chain the section is about is the
  point.
- **Single line** → `// [tl! focus]`. **Range** → `// [tl! focus:start]` …
  `// [tl! focus:end]` on the first and last line of the range, inclusive.
- The token is appended to an existing `//` comment when there is one, otherwise
  it becomes the line's own comment. Torchlight strips the token and keeps the
  effect.
- **Never focus every line** — that is the same as focusing nothing, and the
  gate rejects it.
- **Multiple ranges are fine** when a section compares two spots (see
  `docs/forms/overview.md`, which focuses both auto-detected form schemas).
- Short blocks (a bare fluent chain, a `use` line, a shell command) take no
  focus. The whole block is already the point.

Pages written before this standard are recorded in
`docs-site/docs-standard-baseline.txt`. **Editing a block removes its exemption**
— the ledger is keyed by a hash of the block's code — so the corpus converges as
it is touched. Shrink the ledger deliberately with
`npm run docs:standard -- --update-baseline` after fixing pages.

### S2 — Focus markers are well formed

Every `focus:start` is closed by a `focus:end` in the same block; no orphan
`focus:end`; markers never leak into prose (a `[tl!` outside a code block is
caught by `npm run docs:check`).

### S3 — Czech mirrors English, structurally

Every English page has a Czech mirror at `docs/cs/<same path>`, and:

- **Prose is translated**, including headings.
- **Code is not rewritten.** Same blocks in the same order, same fluent chain in
  each. Translate the `//` comments and user-facing string literals (labels,
  messages); leave method names, models, columns and structure alone.
- **Focus markers are mirrored** line for line.

The gate compares block count, the fluent call sequence of each block, and the
focus markers of each block. It starts at zero violations — keep it there.

### D5 — Front matter earns the navigation

```md
---
order: 23           # position inside the section
section: Table      # overrides the section inferred from the path
summary: One sentence used as the hero intro and the search excerpt.
nav: false          # hide from the sidebar (child pages of an index)
preview: table      # preview bundle, or 'none'
---
```

`summary` is worth writing on every page: it is what the search results and the
page hero show. Without it the builder falls back to the first paragraph, which
usually reads as a fragment out of context.

### D6 — Links and anchors are real

Relative `.md` links between pages, and anchors that exist. `npm run docs:check`
resolves every link and every `#anchor` — including same-page anchors in the
Czech tree, which rot silently when a heading is translated (49 had drifted when
the check was added).

### D7 — What changing an API obliges

A public API change is not finished until:

1. The class's reference page documents the new/changed method (D3).
2. Its `## How It Works` still describes reality (D2).
3. At least one example uses it, with focus if the block is long (S1).
4. The Czech mirror is updated in the same commit (S3) — not "later".
5. `npm run docs:api` passes without touching the baseline.

Never fix a gate by widening a baseline. Baselines record what predates the
rule, not what you just wrote.

## Verification

```bash
npm run docs:check       # markdown integrity, links, anchors, a clean build per locale
npm run docs:standard    # S1–S3: focus spotlights, marker syntax, EN/CS parity
npm run docs:api         # docs vs the real public API, both directions
npm run docs:verify-ui   # the built site in a browser (search, language, head tags)
```

The first three are cheap and run on every docs change. All four run in CI
(`.github/workflows/docs-check.yml`).

## Canonical Examples

Copy the shape from these rather than inventing one:

- **Reference page** — `docs/table/columns/badge.md`: mechanism first
  (resolution order and fallbacks), capability sections, one extended example in
  a real component, complete typed API, related links.
- **Focus in a quick start** — `docs/table/overview.md`, `docs/getting-started.md`:
  the full component is shown, the table definition is what glows.
- **Multi-range focus** — `docs/forms/overview.md`: two schemas spotlighted in
  one block.
- **Guide** — `docs/forms/save-lifecycle.md`: a flow documented in the order it
  runs, with the hook points focused in the closing example.

## Checklist

New or rewritten reference page:

- [ ] H1 is the class short name; the import follows immediately
- [ ] `## How It Works` covers resolution order, defaults, where it runs, traps
- [ ] every capability has its own section and example
- [ ] one extended example in a real host component
- [ ] API section lists every declared fluent setter, typed, with defaults
- [ ] every PHP/Blade block ≥ 12 lines carries a focus spotlight
- [ ] `summary` front matter written
- [ ] Czech mirror updated: prose translated, code and focus identical
- [ ] `npm run docs:check && npm run docs:standard && npm run docs:api` pass
