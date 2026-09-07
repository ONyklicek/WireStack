## wire-admin

The **optional** admin shell: a layout and a sidebar over what is already registered. It requires
`wire-panels` and **nothing requires it** — a composer boundary is the opt-in, so an application that wants
resource pages and its own chrome simply does not install this (ADR 0028).

- **Installing is not adopting.** No provider sets `livewire.component_layout`. A page renders inside the
  shell only once the application's own layout view says so, and the sidebar works alone inside any frame.
- **`php artisan wire-admin:install` is how an application asks.** It publishes `App\Providers\WireAdminServiceProvider`
  (which holds that one config line), writes `resources/views/components/layouts/admin.blade.php`, registers the
  provider in `bootstrap/providers.php` and publishes translations. Idempotent and never overwriting; an app with
  no `bootstrap/providers.php` is told the line to add (`AdminInstallException`, caught and warned) and the rest
  still completes. Failures are exceptions, never a status nobody checks.
@verbatim
- **Two tags, both class-based**: `<x-wire-admin::layout>` (slots: `head`, `brand`, `topbar`, `user`, default)
  and `<x-wire-admin::sidebar :linked-only="false" :zone="…" :active-key="…" />`.
@endverbatim
@verbatim
- **The application writes the layout view, the package writes the frame.** A layout that names
  `<x-wire-admin::layout>` and fills slots is what a consuming app owns; `config('livewire.component_layout')`
  points at *that* view, never at `wire-admin::layout` directly (the component needs its props).
@endverbatim
@verbatim
- **The user menu is filled by packages, not by your layout.** `PageChrome::USER_MENU` is the third chrome
  region: `wire-module-users` contributes the profile link, `wire-module-auth` the sign-out, each with a
  `sort` because provider order is composer's discovery order. Never hand-write either into the `user-menu`
  slot — that slot is for what the *application* owns. One row is `<x-wire::menu-item>` (wire-core, so a
  package that does not depend on the shell can still draw one); `<x-wire-admin::menu-item>` delegates to it.
- **The shell owns a signed-out frame and no authentication.** `<x-wire-admin::auth-layout>` is a head and a
  card with no navigation; the screens that render in it are `nyoncode/wire-module-auth`'s, over Fortify.
@endverbatim
- **Slots, never configuration.** There is no `Panel` object, no branding/colour/auth config and no URL scheme
  — that is the panel-builder drift ADR 0020 named. `vendor:publish --tag=wire-admin::views` is how markup
  changes.
- **It reads seams, holds no state**: `Workspace::navigation($zone, $linkedOnly)` for the menu,
  `ResolvesPageUrls` for every link (null until a package owns routing), `Zone::current()` /
  `Zone::currentKey()` for the zone and the active entry.
- **Zone and active key are read at page render, in the component constructor** — never re-derived per render.
  Inside a Livewire update `Route::currentRouteName()` is `livewire.update`, so a re-derived answer is right
  once and null forever after, while rendering perfectly (ADR 0027).
- **An unrouted entry keeps its row and loses its link** (`aria-disabled`), which is the honest picture of a
  half-routed catalogue; `:linked-only="true"` drops those rows instead.
- **The mobile handle listens to the media query, not to `resize`** — the same query the `lg:` classes are
  matched on, so the two cannot disagree at the boundary. A driver caught the resize version failing.
