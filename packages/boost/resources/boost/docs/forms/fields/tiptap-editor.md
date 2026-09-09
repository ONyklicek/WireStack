---
summary: "The full editor on TipTap and ProseMirror: tables, images, mentions and alignment, stored as HTML or JSON."
---

# TiptapEditor

Full-featured rich text editor built on [TipTap](https://tiptap.dev/) / ProseMirror. Configurable toolbar, optional extensions (tables, images, text align, highlight), and HTML or JSON output.

```php
use NyonCode\WireForms\Components\TiptapEditor;
```

## Setup

None. The editor's JavaScript ships **pre-bundled inside the package** and the
field's Blade view injects it automatically. There is no npm install, no build
step, and no `app.js` import to add — just use the field and it works out of the box.

The editor is **code-split**: the core bundle (TipTap core + the always-on
extensions) is served at `/wire-forms/tiptap/tiptap-editor.js`, and the opt-in
extensions (`withTables()` / `withImages()` / `withHighlight()` / `withTextAlign()`)
ship in a separate addon bundle that is only loaded when a field on the page
enables one of them. Both share one core chunk, so a page without those extensions
downloads less, and enabling tables never ships a second copy of the editor core.
The `<script type="module">` tags are injected once per page via Livewire's
`@assets` directive; they register the Alpine component `tiptapEditor` that the
view relies on (Alpine ships with Livewire).

> **Publishing the asset (optional).** To have your web server serve the files
> instead of the package route, publish them with:
> ```bash
> php artisan vendor:publish --tag=laravel-assets --force
> ```
> This copies the bundles to `public/vendor/wire-forms/` — the whole stack's, not
> just this package's — and the editor emits those paths from then on, cache-buster
> included. The publish mirrors `dist/` verbatim, so the entries keep resolving their
> shared chunk relative to `vendor/wire-forms/tiptap/`. See
> [Getting Started → JavaScript Assets](../../start/getting-started.md#javascript-assets).

> **Contributors.** The bundles are generated from
> `packages/forms/resources/js/tiptap-editor.js` and `tiptap-editor-addons.js`, and
> committed (with the shared chunk) to `packages/forms/dist/tiptap/`. Rebuild them
> after editing the source with:
> ```bash
> npm run build:forms-assets
> ```

---

## Basic Usage

```php
TiptapEditor::make('content')
```

## Default Content

The editor opens on the field's `->default()` — the canonical default every
component has, no editor-specific method. It is **markup, not plain text**, so a
template arrives pre-formatted:

```php
TiptapEditor::make('minutes')
    ->default('<h2>Meeting notes</h2><p>Some <strong>text</strong>.</p><ul><li>First point</li></ul>')
```

How it resolves, in order:

1. **The form runtime seeds it.** `fill()` (and a modal action's initial state)
   writes `->default()` into the state bag for any key the caller did not
   provide, so the editor simply opens on a value that is already there.
2. **The editor seeds it when the host did not** — a `null` column, a property
   bound by hand — applying the default whenever the bound value is empty and
   pushing the parsed document back into Livewire, so saving a form the user
   never touched stores the template rather than nothing.
3. **A cleared editor is not empty.** Emptying the content stores `<p></p>`, so
   re-opening a document the user deliberately cleared does *not* bring the
   default back. On an edit form where the column is genuinely `null`, add
   `->defaultOnNull()` to let the default fill it server-side too.

Under `->outputJson()` the default may be a TipTap JSON document string, or the
same HTML — HTML is parsed into a document and stored as JSON either way.

## Custom Toolbar

```php
TiptapEditor::make('content')
    ->toolbarButtons([
        'bold', 'italic', 'underline',
        '|',
        'h2', 'h3',
        '|',
        'bulletList', 'orderedList',
        '|',
        'link', 'undo', 'redo',
    ])
```

Use `'|'` as a visual separator between groups.

## Disable Specific Buttons

```php
TiptapEditor::make('content')
    ->disableToolbarButtons(['codeBlock', 'code'])
```

## No Toolbar

```php
TiptapEditor::make('content')
    ->disableAllToolbarButtons()
```

## Extensions

Enable optional extensions individually:

```php
TiptapEditor::make('content')
    ->withTables()       // table insertion + editing
    ->withImages()       // image insertion (via URL prompt)
    ->withTextAlign()    // left / center / right alignment buttons
    ->withHighlight()    // text highlight button
```

When an extension is enabled, its toolbar button is appended automatically.

## Mentions

A mention is stored as an **identity**, never as a name:

```html
<span data-type="mention" data-mention-trigger="#"
      data-mention-type="article" data-id="12">#Price list 2026</span>
```

There is no `href` in there, and the text is a *fallback*. Every render looks the
record up again, so renaming an article renames it in every document that ever
mentioned it, and a link can never outlive the permission that granted it. The
price is that stored content is no longer displayed by echoing it — see
[Displaying content with mentions](#displaying-content-with-mentions) below.

### One trigger, several models

A trigger is not a model. `@` naming people and `#` naming anything the site
publishes are the same feature, and the second one only works when one trigger
can hold several sources:

```php
use Illuminate\Database\Eloquent\Builder;
use NyonCode\WireForms\Components\Mention;
use NyonCode\WireForms\Components\Mention\Source;

TiptapEditor::make('body')
    ->mentions(
        Mention::make('@')->source(
            Source::make(User::class)->titleAttribute('name')->label('People'),
        ),
        Mention::make('#')->sources([                                    // [tl! focus:start]
            Source::make(Article::class)
                ->titleAttribute('title')
                ->label('Articles')
                ->modifyOptionsQueryUsing(fn (Builder $query) => $query->published()),

            Source::make(Page::class)->titleAttribute('title')->label('Pages'),
        ]),                                                              // [tl! focus:end]
    )
```

That is why the document stores a morph type beside the id: under one `#`, `12`
alone would not say whether it means an article or a page.

Each source is queried separately and the rows are grouped by source label — a
`UNION` would cost the per-source scoping, which is the reason several sources
share a trigger in the first place. Rows are **not** ranked against each other
across sources: the list says which group a row came from rather than pretending
to know that an article beats a page.

### Scoping and authorisation

`modifyOptionsQueryUsing()` decides what the **author** may insert:

```php
Source::make(Article::class)
    ->titleAttribute('title')
    ->modifyOptionsQueryUsing(fn (Builder $query) => $query->whereBelongsTo($team))
```

What a stored mention resolves to later is scoped again at render time, where the
viewer may be somebody else entirely — see
[`MentionRegistry`](#models-you-do-not-own).

The suggestion endpoint never answers an empty search term: an unfiltered mention
list is a user-enumeration endpoint, not a search.

### Making a model mentionable

The record itself is the one thing that always knows its own fresh name, so that
is where the render-time facts live:

```php
use NyonCode\WireCore\Foundation\Mentions\Contracts\Mentionable;

class Article extends Model implements Mentionable
{
    public function getMentionLabel(): string           // [tl! focus:start]
    {
        return $this->title;
    }

    public function getMentionUrl(): ?string
    {
        return $this->published ? route('articles.show', $this) : null;
    }                                                   // [tl! focus:end]
}
```

Returning `null` from `getMentionUrl()` is meant: the mention renders named but
not clickable.

### Models you do not own

A package's `User`, a vendor's `Page` — register the same facts at boot instead:

```php
use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Foundation\Mentions\MentionRegistry;

public function boot(MentionRegistry $mentions): void
{
    $mentions->register(Page::class)
        ->titleAttribute('title')                                        // [tl! focus:start]
        ->url(fn (Model $page) => route('pages.show', $page))
        // Viewer-scoped visibility belongs here: a record the query excludes is
        // simply not found, and an unresolvable mention renders as plain text.
        ->modifyQueryUsing(fn ($query) => $query->where('visibility', 'public')); // [tl! focus:end]
}
```

Deleted and not-allowed-to-see take the same path deliberately. It is the one
that leaks nothing — a fresh title pulled straight from the database is the leak.

Without either the contract or a registration the mention still renders: it keeps
the label the document was written with, which was correct at the time and not
after.

### Displaying content with mentions

`{!! $post->body !!}` would print the identities and no links at all. Read the
content back through the renderer instead:

```blade
{{-- Blade, and anywhere else --}}
<x-wire::rich-content :html="$post->body" class="prose" />
```

```php
// An infolist
HtmlEntry::make('body')->label('Body')

// A table cell — implies ->html()
TextColumn::make('body')->richContent()
```

All three go through the same owner
(`NyonCode\WireCore\Foundation\Mentions\MentionRenderer`), which an application
can also call directly:

```php
use NyonCode\WireCore\Foundation\Mentions\MentionRenderer;

$html = app(MentionRenderer::class)->render($post->body);

// Just the identities — for a notification sweep, say.
$mentioned = app(MentionRenderer::class)->extract($post->body);
```

A document naming twelve articles and three users costs **two** queries, not
fifteen: references are grouped by stored type and fetched with one `whereKey()`
each. Content holding no mentions is returned byte-for-byte and never reaches the
DOM parser.

> **A table cell is rendered on its own**, so those lookups batch within one cell
> and not across the page: twenty-five rows with `richContent()` are twenty-five
> lookups. Worth it on a narrow table of documents; not on a listing that only
> shows the first eighty characters.

### Typing across spaces

Off by default. With `allowSpaces()` the suggestion has no way to know where the
mention ended, so it keeps swallowing the sentence after it until something
dismisses the list:

```php
Mention::make('#')->allowSpaces()
```

Titles are usually findable from their first word anyway — `#price` finds
`Price list 2026`, because the matching happens on the server against the whole
column.

### Delivery

The mention node and TipTap's suggestion engine ship as a **third** ESM entry
(`tiptap-editor-mentions.js`), injected only for a field that declares mentions.
An editor with tables and no mentions never downloads it, and the shared
`@tiptap/core` chunk is not duplicated.

## Output Format

```php
// Default: HTML string stored in the model
TiptapEditor::make('body')->outputHtml()

// Store as TipTap JSON document (serialised as a JSON string)
TiptapEditor::make('body')->outputJson()
```

## Character Limit

```php
TiptapEditor::make('summary')
    ->maxLength(2000)    // shows a live counter, enforced by CharacterCount extension
```

## Height

```php
TiptapEditor::make('content')
    ->minHeight(400)     // minimum height in pixels (default 240)
```

## Read-Only / Disabled

```php
TiptapEditor::make('content')
    ->readOnly()
    ->disabled(fn () => ! $this->canEdit)
```

## Localization

The editor carries no English of its own. Toolbar tooltips, the heading titles
and the browser prompts opened by the link and image buttons all resolve from
`wire-forms::fields.editor.*`, so the field follows `app()->getLocale()`. English
(`en`) and Czech (`cs`) ship with the package — a Czech app shows *Tučné*,
*Odrážkový seznam*, *Nadpis 2*, and prompts *URL odkazu*.

The prompt titles are resolved in PHP and handed to the editor's Alpine config,
which is why a locale change reaches strings that live inside the JS bundle.

[RichEditor](rich-editor.md#localization) and
[MarkdownEditor](markdown-editor.md#localization) title their toolbars from the
very same keys, so the three editors read alike in every locale.

Reword a string, or add a locale, by publishing the translations and editing
`lang/vendor/wire-forms/{locale}/fields.php`:

```bash
php artisan vendor:publish --tag=wire-forms::translations
```

The button glyphs stay `H1` / `H2` / `H3` in every locale — those are symbols,
not words; the tooltip is what gets translated.

## Available Toolbar Buttons

| Key | Description |
|-----|-------------|
| `bold` | Bold |
| `italic` | Italic |
| `underline` | Underline |
| `strike` | Strikethrough |
| `code` | Inline code |
| `highlight` | Highlight (requires `withHighlight()`) |
| `h1` | Heading 1 |
| `h2` | Heading 2 |
| `h3` | Heading 3 |
| `bulletList` | Unordered list |
| `orderedList` | Ordered list |
| `blockquote` | Blockquote |
| `codeBlock` | Code block |
| `link` | Hyperlink (opens URL prompt) |
| `image` | Image (requires `withImages()`) |
| `table` | Insert table (requires `withTables()`) |
| `alignLeft` | Left align (requires `withTextAlign()`) |
| `alignCenter` | Centre align (requires `withTextAlign()`) |
| `alignRight` | Right align (requires `withTextAlign()`) |
| `undo` | Undo |
| `redo` | Redo |
| `\|` | Visual separator |

## Comparison with RichEditor

| Feature | RichEditor | TiptapEditor |
|---------|-----------|--------------|
| Engine | `document.execCommand` (deprecated) | ProseMirror (stable) |
| Cross-browser | Inconsistent | Consistent |
| Extensions | None | Tables, images, align, highlight, … |
| Output | HTML | HTML or JSON |
| npm dependency | No | Yes |
| Setup effort | Zero | `npm install` + one import |

## Methods

| Method | Type | Description |
|--------|------|-------------|
| `toolbarButtons(array)` | array | Override the toolbar button list |
| `disableToolbarButtons(array)` | array | Remove specific buttons |
| `disableAllToolbarButtons()` | — | Hide the toolbar entirely |
| `default(string\|Closure)` | string | Pre-formatted document the editor opens on when empty |
| `defaultOnNull()` | — | Let `default()` also fill an existing `null` on fill |
| `outputHtml()` | — | Store content as HTML (default) |
| `outputJson()` | — | Store content as TipTap JSON string |
| `withImages(bool)` | bool | Enable image extension + button |
| `withTables(bool)` | bool | Enable table extension + button |
| `withTextAlign(bool)` | bool | Enable text-align extension + buttons |
| `withHighlight(bool)` | bool | Enable highlight extension + button |
| `mentions(Mention\|array ...)` | Mention | Mention triggers offered by the editor |
| `minHeight(int)` | int | Minimum editor height in pixels (default `240`) |
| `maxLength(int\|null)` | int | Character limit with live counter |
| `disabled(bool\|Closure)` | bool | Disable the editor |
| `readOnly(bool\|Closure)` | bool | Read-only mode |
| `required()` | — | Mark as required |
| `placeholder(string\|Closure)` | string | Placeholder shown when empty |
| `live()` | — | Trigger Livewire update on each change |
| `debounce(int)` | ms | Debounce delay for `live()` |

### `Mention`

| Method | Type | Description |
|--------|------|-------------|
| `Mention::make(string)` | string | The trigger character — `@`, `#` |
| `sources(array)` | array\<Source\> | The models this trigger offers |
| `source(Source)` | Source | A trigger with exactly one model behind it |
| `allowSpaces(bool)` | bool | Keep matching after a space (default `false`) |
| `limit(int)` | int | Cap the whole list, however many sources feed it (default `15`) |

### `Mention\Source`

| Method | Type | Description |
|--------|------|-------------|
| `Source::make(string)` | class-string\<Model\> | The model this source offers |
| `titleAttribute(string)` | string | The column shown in the suggestion list |
| `searchAttribute(string)` | string | The column matched, when not the one displayed |
| `label(string)` | string | The group heading rows sit under |
| `limit(int)` | int | Rows this source contributes (default `5`) |
| `modifyOptionsQueryUsing(Closure)` | Closure | Scope the suggestion query |

See [Common Field API](index.md#common-field-api) for label, hint, tooltip, and other shared methods.
