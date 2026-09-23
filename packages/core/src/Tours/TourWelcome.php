<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Tours;

/**
 * The block a tour opens with: what it is about, and whether to start it now.
 *
 * ## Why a tour asks before it points
 *
 * Without one, a walkthrough begins by dimming the page and pointing at
 * something — which is an interruption somebody never agreed to, arriving at
 * the moment they came to do something else. The welcome block turns the first
 * beat into a question with two honest answers. It is opt-in per tour: a tour
 * with no welcome starts the way it always did, and nothing about this class is
 * reachable by an application that does not ask for it.
 *
 * ## Later is not Skip
 *
 * The walkthrough's own "Skip" is a decision about the tour for ever — the
 * ledger records it exactly as finishing does, for the reason
 * {@see TourLedger::acknowledge()} gives. "Later" is the answer that had no way
 * of being said before: not now, ask again. It is scoped to the session rather
 * than to a clock, and counted, so a tour that is put off repeatedly stops
 * asking instead of greeting somebody for ever. {@see Tour::postpone()} owns
 * that count, because it is a property of the tour rather than of this block.
 *
 * Setting no {@see later()} label removes the button: a welcome that only
 * offers "Start" is a legitimate thing to want, and it is the shape a one-step
 * what's-new tour usually takes.
 *
 * ## The labels are the author's, the defaults are the framework's
 *
 * `start()` and `later()` are optional because most applications want the
 * framework's translated wording and should not have to restate it in every
 * tour — and because restating it is how a Czech application ends up with an
 * English button in the one tour somebody forgot. Left null, each falls back to
 * `wire-core::messages.tour_start` / `tour_later`, resolved in
 * {@see TourHost::payload()} rather than here: this object is a definition
 * registered at boot, and a translation resolved at boot is a translation in
 * whatever locale the console had.
 *
 * ## Styling, and the escape hatch below it
 *
 * The rendered block carries `tour-welcome`, `tour-welcome-heading`,
 * `tour-welcome-text`, `tour-welcome-start` and `tour-welcome-later`, so an
 * application restyles it from its own stylesheet without publishing anything.
 * When the wanted thing is different markup rather than different styling —
 * an illustration, a logo, a video — {@see view()} replaces the block outright.
 */
final class TourWelcome
{
    private ?string $heading = null;

    private ?string $text = null;

    private ?string $start = null;

    private ?string $later = null;

    private ?string $view = null;

    private function __construct() {}

    public static function make(): self
    {
        return new self;
    }

    /** Set the bold line at the top of the block. */
    public function heading(?string $heading): self
    {
        $this->heading = $heading;

        return $this;
    }

    /** Set the body — a sentence or two about what the tour covers. */
    public function text(?string $text): self
    {
        $this->text = $text;

        return $this;
    }

    /** Set the label of the button that starts the tour; null keeps the framework's. */
    public function start(?string $start): self
    {
        $this->start = $start;

        return $this;
    }

    /**
     * Set the label of the button that puts the tour off; null keeps the
     * framework's, and a tour whose {@see Tour::postpone()} count is zero has
     * no such button whatever this says.
     */
    public function later(?string $later): self
    {
        $this->later = $later;

        return $this;
    }

    /**
     * Render this block from a view of your own instead of the framework's.
     *
     * The view is included inside the tour's own Alpine scope, so it has the
     * four names the framework's own markup uses — `greeting`, `begin()`,
     * `later()` and the `welcome` payload object — and it receives `$welcome`
     * (this object) and `$tour`. It owns its visibility: nothing shows it, so
     * it has to carry `x-show="greeting"` itself.
     *
     * A view is the escape hatch for markup, not for behaviour. Everything
     * about when the block appears, and what "Later" means, stays here.
     */
    public function view(?string $view): self
    {
        $this->view = $view;

        return $this;
    }

    public function getHeading(): ?string
    {
        return $this->heading;
    }

    public function getText(): ?string
    {
        return $this->text;
    }

    public function getStart(): ?string
    {
        return $this->start;
    }

    public function getLater(): ?string
    {
        return $this->later;
    }

    public function getView(): ?string
    {
        return $this->view;
    }
}
