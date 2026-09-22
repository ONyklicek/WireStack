<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Tours;

use Illuminate\Contracts\Auth\Authenticatable;
use NyonCode\WireCore\Foundation\Routing\Zone;

/**
 * Which tour, if any, this person should see on this screen.
 *
 * The one question the host view asks, and the only class that needs all three
 * of the registry, a tour's own constraints and the ledger to answer it.
 *
 * ## The location travels
 *
 * `match()` takes the zone, resource and page as arguments and never reads them
 * itself. {@see Zone} records why, from a measurement rather than a guess:
 * `Route::currentRouteName()` answers
 * `livewire.update` during a Livewire round trip, so a class that asked for the
 * current zone while handling an update would get null — and every constraint
 * would quietly stop matching, in production, on exactly the requests a test
 * does not make.
 *
 * The caller reads the three values once while the page renders, keeps them in
 * properties Livewire snapshots, and passes them back in afterwards. That also
 * makes every rule below testable without a request.
 *
 * ## One tour at a time
 *
 * If several match — somebody holding both `admin.*` and `sales.*`, on a page
 * both tours claim — the lowest {@see Tour::sort()} wins and the rest wait for
 * another visit. Two walkthroughs back to back is worse for a person than one.
 *
 * Deliberately `sort` rather than "the most specific match wins": specificity
 * across three independent axes has no obvious ordering — is a zone constraint
 * narrower than a permission one? — so it would be a rule nobody could predict
 * from the outside. An integer is a rule an author can read off the page.
 */
final class TourState
{
    public function __construct(
        private readonly Tours $tours,
        private readonly TourLedger $ledger,
    ) {}

    /**
     * The tour to run now, or null when there is none.
     *
     * Ordered by the registry, so the first match is the winner and the rest are
     * never consulted — a tour whose `visible()` closure is expensive does not
     * pay for a tour ahead of it having already claimed the screen.
     *
     * @param  string|null  $zone  A zone prefix as `Zone::current()` gives it, or null when unzoned
     */
    public function match(
        ?string $zone,
        ?string $resource,
        ?string $page,
        ?Authenticatable $user,
    ): ?Tour {
        return $this->first(
            $zone,
            $resource,
            $page,
            fn (Tour $tour): bool => ! $this->ledger->hasSeen($tour, $user),
        );
    }

    /**
     * The tour a page was navigated to in order to carry on, or null.
     *
     * A tour spanning pages hands the next page its id and the step it reached,
     * and this is where that is taken at its word only as far as it is true:
     * the tour has to exist, still be unfinished for this person, and have that
     * step on *this* page in a zone it runs in. A link somebody edited, a tour
     * finished in another tab, a step that moved — each answers null, and the
     * page renders as though nothing had been asked.
     */
    public function resuming(
        string $id,
        int $index,
        ?string $zone,
        ?string $resource,
        ?string $page,
        ?Authenticatable $user,
    ): ?Tour {
        $tour = $this->tours->get($id);

        if ($tour === null || $this->ledger->hasSeen($tour, $user)) {
            return null;
        }

        return $tour->continuesAt($index, $zone, $resource, $page) ? $tour : null;
    }

    /**
     * The tour that claims this screen whether or not it has been acknowledged.
     *
     * What a "replay" affordance needs, and the reason it is a second method
     * rather than a flag on {@see match()}: the two questions differ in what
     * they are *for*. One decides whether to interrupt somebody; the other
     * decides whether to offer them a way back in. A boolean argument would have
     * made every call site restate which of those it meant.
     */
    public function claiming(?string $zone, ?string $resource, ?string $page): ?Tour
    {
        return $this->first($zone, $resource, $page, static fn (): bool => true);
    }

    /**
     * The first registered tour that claims this screen and passes an extra test.
     *
     * The extra test runs before the constraints because it is the cheap one: a
     * ledger lookup is a read of an array already in hand, while `appliesTo()`
     * may evaluate an application's `visible()` closure.
     *
     * @param  callable(Tour): bool  $also
     */
    private function first(?string $zone, ?string $resource, ?string $page, callable $also): ?Tour
    {
        foreach ($this->tours->all() as $tour) {
            if ($also($tour) && $tour->appliesTo($zone, $resource, $page)) {
                return $tour;
            }
        }

        return null;
    }

    /**
     * Record that this person has finished or skipped the tour with this id.
     *
     * Takes an id rather than a {@see Tour} because the caller is a Livewire
     * method and the browser decides what it sends. An id that is not registered
     * is ignored rather than refused: the tour may have been removed between the
     * page rendering and the person clicking, and there is nothing to record and
     * nothing to complain about.
     */
    public function acknowledge(string $tourId, ?Authenticatable $user): void
    {
        $tour = $this->tours->get($tourId);

        if ($tour === null) {
            return;
        }

        $this->ledger->acknowledge($tour, $user);
    }

    /**
     * The step this person had reached in an unfinished tour, or null.
     *
     * Zero is null too: a tour left on its first step starts where it would
     * have started anyway, and the browser has one fewer case to settle.
     */
    public function reached(Tour $tour, ?Authenticatable $user): ?int
    {
        $step = $this->ledger->reached($tour, $user);

        return $step !== null && $step > 0 && $step < count($tour->getSteps()) ? $step : null;
    }

    /**
     * Record the step this person has got to in the tour with this id.
     *
     * The step comes from the browser, so it is bounded to the tour's steps and
     * otherwise ignored. The worst a forged one can do is choose where this
     * person's own tour reopens. A tour already finished is left alone: a late
     * request from the last step must not reopen what "Finish" just closed.
     */
    public function reach(string $tourId, int $step, ?Authenticatable $user): void
    {
        $tour = $this->tours->get($tourId);

        if ($tour === null || $step < 0 || $step >= count($tour->getSteps()) || $this->ledger->hasSeen($tour, $user)) {
            return;
        }

        $this->ledger->reach($tour, $user, $step);
    }

    /**
     * Forget a tour for this person so it runs again — what a "replay" entry calls.
     *
     * Same treatment of an unknown id, for the same reason.
     */
    public function replay(string $tourId, ?Authenticatable $user): void
    {
        $tour = $this->tours->get($tourId);

        if ($tour === null) {
            return;
        }

        $this->ledger->forget($tour, $user);
    }
}
