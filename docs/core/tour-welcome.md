---
order: 84
summary: The block a tour opens with — what it is about, and the choice between starting it now and being asked again later.
---

# TourWelcome

The block a [Tour](tours.md) opens with: a card in the middle of the screen
saying what the walkthrough covers, and two buttons — start it, or be asked
again later. Reach for it when a tour is long enough that beginning it
uninvited would be an interruption. Without one, a tour starts by dimming the
page and pointing at something, which is what it has always done.

```php
use NyonCode\WireCore\Tours\TourWelcome;
```

## How It Works

**When it is shown.** The browser plans the tour first — which steps have an
element on this page — and only then greets. A tour whose steps are all missing
does not start, so it does not greet either. The card is shown **only when the
tour is starting from the top**: somebody carried onto this page by a step's
[`on()`](tour-step.md#on-another-page), or coming back to a walkthrough they
left halfway, is returned to where they were without being asked whether to
start something already running.

**The two answers.**

- **Start** closes the card and begins the walkthrough at the first step.
  Nothing is recorded — finishing or skipping it later records it, as always.
- **Later** closes the tour and records a *postponement*: the tour is not
  offered again for the rest of that session, and the person is asked again on
  their next visit.

**Later is not Skip.** The walkthrough's own Skip is final — it is stored
exactly as finishing is, because somebody who skipped has decided. Later is the
answer that had no way of being said before: not now, ask again.

**Postponements are counted, and run out.** A greeting that came back for ever
would be worse than one that never asked. Each Later increments a count, and the
one that reaches the tour's [`postpone()`](tours.md#tour-api) limit acknowledges
the tour instead — the same record Skip writes, so nothing downstream has a
fourth state to handle. The default limit is `3`, configurable as
`wire-core.tours.postpone`. `->postpone(0)` removes the Later button entirely,
leaving a card that can only be started.

**Why a session and not a clock.** The postponement is stamped with the id of
the session it was made in, and holds only while that session does. That is what
makes "later" mean the same thing on every driver: on `session` the entry would
have expired anyway, while on `database` it outlives the sitting, and without the
comparison a postponement there would be indistinguishable from a skip. No
duration to configure, and no clock to get wrong across time zones.

**Where it runs.** The card is markup the page already carried, shown and hidden
by Alpine. Only Later makes a request, to record the postponement. Escape
dismisses the card and means the same as Later — and for a tour that allows no
postponement, it closes the card without recording anything, so the tour greets
again on the next visit.

**Traps.**

- **A count survives a version bump, but not a finish.** Finishing, skipping and
  replaying all clear the postponement and its count. Changing
  [`since()`](tours.md#whats-new-after-an-upgrade) starts the count again too,
  because a count kept against an older version would mean the author's third
  greeting is somebody else's first.
- **The labels are resolved when the page renders, not when the tour is
  registered.** A tour is registered in a service provider's `boot()`, and a
  translation resolved there is in whatever locale the console had. Leave
  `start()` and `later()` unset to get the framework's translated wording.
- **The card needs the layout that draws it**, like the rest of the tour. See
  [Tour → Your own layout](tours.md#your-own-layout).

## Basic Usage

```php
Tour::make('getting-started')
    ->welcome(
        TourWelcome::make()
            ->heading('Welcome aboard')
            ->text('A minute, and you will know where everything is.'),
    )
    ->steps([
        TourStep::make('admin-sidebar')->text('Every area of the application is here.'),
    ]);
```

## Content

`heading()` is the bold line, `text()` the sentence or two under it. Both are
plain text, and either can be left out. Translate them as you would any other
string:

```php
TourWelcome::make()
    ->heading(__('tours.welcome.heading'))
    ->text(__('tours.welcome.text'));
```

## The Buttons

Both labels are optional, and both fall back to the framework's translated
wording — which is usually what you want, because restating it in every tour is
how one of them ends up in the wrong language:

```php
TourWelcome::make()
    ->start('Show me around')      // default: "Start the tour"
    ->later('Not just now');       // default: "Maybe later"
```

The Later button is drawn only when the tour allows a postponement. These two
are the same statement — a card that can only be started:

```php
Tour::make('one-step')->postpone(0)->welcome(TourWelcome::make()->heading('New in 2.2'));
```

## How Often It Asks Again

`postpone()` is on the [Tour](tours.md#tour-api), not here: how many times a
walkthrough may be put off is a property of the walkthrough rather than of the
card that offers it.

```php
Tour::make('getting-started')->postpone(5);   // five "later"s, then it stops asking
```

Left unset, the configured default applies:

```php
// config/wire-core.php
'tours' => [
    'postpone' => env('WIRE_TOURS_POSTPONE', 3),
],
```

## Your Own Markup

When what you want is different markup rather than different styling — an
illustration, a logo, a short video — `view()` replaces the framework's card
outright:

```php
TourWelcome::make()
    ->heading('Welcome aboard')
    ->view('tours.welcome');
```

The view is included **inside the tour's own Alpine scope**, and that scope is
the whole contract:

| Name | What it is |
| --- | --- |
| `greeting` | Whether the card should be showing. The view owns its own visibility — nothing shows it for you |
| `begin()` | Start the walkthrough |
| `later()` | Close it, and record a postponement if the tour allows one |
| `welcome` | The resolved payload: `heading`, `text`, `start`, `later` (null when postponing is off) |

It also receives `$welcome` (this object) and `$tour`, for anything you would
rather render on the server:

```blade
{{-- resources/views/tours/welcome.blade.php --}}
<div x-show="greeting" x-cloak class="fixed inset-0 z-[63] grid place-items-center p-4">
    <div class="w-full max-w-md rounded-2xl bg-white p-8 text-center shadow-2xl dark:bg-gray-800">
        <img src="/img/welcome.svg" alt="" class="mx-auto mb-4 h-24">

        <h2 class="text-lg font-semibold">{{ $welcome->getHeading() }}</h2>
        <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">{{ $welcome->getText() }}</p>

        <div class="mt-6 flex justify-center gap-2">
            <button type="button" x-show="welcome.later" x-text="welcome.later" x-on:click="later()"></button> {{-- [tl! focus:start] --}}
            <button type="button" x-text="welcome.start" x-on:click="begin()"></button> {{-- [tl! focus:end] --}}
        </div>
    </div>
</div>
```

A view is the escape hatch for markup, not for behaviour: when the card appears,
and what Later costs, stay with the tour.

## Styling

The framework's card carries element hooks, so most changes need no view at all:

| Hook | Element |
| --- | --- |
| `tour-welcome` | The centred overlay |
| `tour-welcome-heading`, `tour-welcome-text` | The two lines |
| `tour-welcome-start`, `tour-welcome-later` | The two buttons |

```css
[data-wire="tour-welcome"] > div { @apply max-w-lg rounded-3xl; }
[data-wire="tour-welcome-start"] { @apply bg-emerald-600 hover:bg-emerald-700; }
```

## Extended Example

A first-run tour that asks before it starts, allows two postponements, and says
what it is about in the application's own words.

```php
<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use NyonCode\WireCore\Tours\Tour;
use NyonCode\WireCore\Tours\Tours;
use NyonCode\WireCore\Tours\TourStep;
use NyonCode\WireCore\Tours\TourWelcome;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->app->make(Tours::class)->register(
            Tour::make('getting-started')
                ->postpone(2)                                    // [tl! focus:start]
                ->welcome(
                    TourWelcome::make()
                        ->heading('Two minutes, and you will know your way around')
                        ->text('We will point at four things and then leave you to it.')
                        ->start('Show me')
                        ->later('Not just now'),
                )                                                // [tl! focus:end]
                ->steps([
                    TourStep::make('admin-sidebar')
                        ->heading('Everything lives here')
                        ->text('Each area of the application has an entry in this menu.')
                        ->placement('right-start'),

                    TourStep::make('global-search-trigger')
                        ->text('Or jump straight to any record.'),
                ]),
        );
    }
}
```

Somebody who chooses "Not just now" is asked again on their next visit, and once
more after that. The third time would be a fourth greeting, so instead the tour
records itself as seen and stops — they can still bring it back from
[Replay the tour](tours.md#replaying) in the user menu.

## TourWelcome API

```php
TourWelcome::make()                   // no arguments — everything about it is optional
->heading(?string $heading)           // the bold line — default none
->text(?string $text)                 // the body, plain text — default none
->start(?string $start)               // label of the start button — default __('wire-core::messages.tour_start')
->later(?string $later)               // label of the postpone button — default __('wire-core::messages.tour_later')
->view(?string $view)                 // render this view instead of the framework's card — default none
->getHeading(): ?string
->getText(): ?string
->getStart(): ?string                 // the author's label, or null for the framework's
->getLater(): ?string
->getView(): ?string
```

## Related

- [Tour](tours.md) — who sees a tour, where it runs, and how `postpone()` is counted
- [TourStep](tour-step.md) — what each stop points at
- [Configuration](../start/configuration.md#tours) — the default postponement allowance
- [Theming → Styling hooks](../start/theming.md#styling-hooks) — what an element hook promises
