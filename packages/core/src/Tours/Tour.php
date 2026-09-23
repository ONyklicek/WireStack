<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Tours;

use NyonCode\WireCore\Exceptions\TourDefinitionException;
use NyonCode\WireCore\Foundation\Concerns\HasVisibility;
use NyonCode\WireCore\Foundation\Routing\Zone;
use NyonCode\WireCore\Foundation\Support\EvaluatesClosures;

/**
 * A walkthrough: who it is for, where it runs, and what it points at.
 *
 * ## Scoping borrows, it does not invent
 *
 * **Who** is {@see HasVisibility}, which composes
 * `Foundation\Concerns\HasAuthorization` — the same trait actions, columns,
 * filters, fields and widgets authorize with. It resolves through Laravel's
 * `Gate`, so `->permission('sales.*')` inherits wildcard matching and the
 * super-admin gate from `nyoncode/laravel-permission-extended` without this
 * module requiring that package or knowing it exists.
 *
 * A `->forRole()` of this module's own would have been a second, weaker answer
 * to a question that already has an owner: no wildcards, no super-admin gate,
 * and a guarantee of drifting the first time either changed.
 *
 * **Where** is {@see Zone}, whose three readers split a page's route name into
 * the mount point, the registered resource and the kind of page. Matching
 * happens against values passed *in* — see {@see matchesLocation()} — never by
 * calling `Zone` from here.
 *
 * ## Every constraint is optional, and absence means "anywhere"
 *
 * A tour with no `permission()`, no `zones()`, no `resource()` and no `page()`
 * runs for everyone everywhere, which is the single-audience application and
 * the shape most tours have. Each constraint added narrows it further; they
 * compose with AND.
 *
 * ## The version is the author's word, not the package's
 *
 * `since()` is a string the author writes, compared for inequality against what
 * the user last acknowledged. First run (nothing stored) and a re-run after an
 * edit (a stale value stored) are then the same comparison, with no ordering
 * rules and no dependency on installed package metadata — which would have been
 * a poor proxy anyway, since a tour is not new because a patch release happened.
 *
 * Bumping `since()` is how an author says "show this again".
 */
final class Tour
{
    use EvaluatesClosures;
    use HasVisibility;

    public const DEFAULT_VERSION = '1';

    private string $version = self::DEFAULT_VERSION;

    private int $sort = 0;

    private ?TourWelcome $welcome = null;

    /** Null means "whatever the configuration says"; see {@see postpone()}. */
    private ?int $postpone = null;

    /** @var array<int, TourStep> */
    private array $steps = [];

    /** @var array<int, string|null>|null Null means "any zone", a list means one of these. */
    private ?array $zones = null;

    /** @var array<int, string>|null */
    private ?array $resources = null;

    /** @var array<int, string>|null */
    private ?array $pages = null;

    private function __construct(private readonly string $id) {}

    /**
     * Start a tour. The id is what a user's acknowledgement is stored against,
     * so it must be stable across releases — renaming one shows the tour again.
     */
    public static function make(string $id): self
    {
        if (trim($id) === '') {
            throw TourDefinitionException::emptyTourId();
        }

        return new self($id);
    }

    /**
     * Set the content version — bump it to show this tour again to people who
     * already finished it.
     */
    public function since(string $version): self
    {
        $this->version = $version;

        return $this;
    }

    /**
     * Order this tour among others that match the same screen; the lowest wins
     * and the rest wait for another visit.
     */
    public function sort(int $sort): self
    {
        $this->sort = $sort;

        return $this;
    }

    /**
     * Set the steps, in the order they are shown.
     *
     * @param  array<int, TourStep>  $steps
     */
    public function steps(array $steps): self
    {
        $this->steps = array_values($steps);

        return $this;
    }

    /**
     * Open with a block that says what this is and asks whether to start now.
     *
     * Opt-in: without one the tour begins by pointing at something, which is
     * what it has always done. {@see TourWelcome} says why a tour might ask
     * first, and what "Later" means.
     */
    public function welcome(TourWelcome $welcome): self
    {
        $this->welcome = $welcome;

        return $this;
    }

    /**
     * How many times this tour may be put off before it stops asking.
     *
     * Reaching the count acknowledges the tour, exactly as skipping it would:
     * somebody who has said "later" three times has answered, and a greeting
     * that returns for ever is the failure mode this counter exists to prevent.
     * `postpone(0)` removes the "Later" button altogether, leaving a welcome
     * block that can only be started.
     *
     * Left unset, the configured default applies
     * (`wire-core.tours.postpone`). The default is resolved where the count is
     * used rather than here, so a tour registered at boot does not freeze the
     * configuration as it was at boot.
     */
    public function postpone(int $times): self
    {
        $this->postpone = max(0, $times);

        return $this;
    }

    public function getWelcome(): ?TourWelcome
    {
        return $this->welcome;
    }

    /** The author's postponement count, or null to use the configured one. */
    public function getPostponeLimit(): ?int
    {
        return $this->postpone;
    }

    /**
     * Restrict the tour to these zones; `zones(null)` means the unzoned
     * application, which is the common one and would otherwise be inexpressible.
     */
    public function zones(?string ...$zones): self
    {
        $this->zones = array_map(
            static fn (?string $zone): ?string => $zone === null ? null : Zone::prefix($zone),
            $zones,
        );

        return $this;
    }

    /** Restrict the tour to the pages of these registered resources. */
    public function resource(string ...$resources): self
    {
        $this->resources = array_values($resources);

        return $this;
    }

    /** Restrict the tour to these kinds of page — `index`, `create`, `view`, `edit`, or a resource's own. */
    public function page(string ...$pages): self
    {
        $this->pages = array_values($pages);

        return $this;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getVersion(): string
    {
        return $this->version;
    }

    public function getSort(): int
    {
        return $this->sort;
    }

    /** @return array<int, TourStep> */
    public function getSteps(): array
    {
        return $this->steps;
    }

    /**
     * Whether this tour claims a screen, given what {@see Zone} read from it.
     *
     * The three values are passed in rather than read here, and that is the one
     * rule this class cannot bend. `Zone`'s own docblock records the measurement:
     * `Route::currentRouteName()` answers `livewire.update` during a Livewire
     * round trip, so calling `Zone::current()` from inside a component's update
     * returns null and every constraint below would silently stop matching. The
     * identifier travels; the caller reads it once, while the page renders.
     *
     * @param  string|null  $zone  A zone prefix as `Zone::current()` gives it, or null when unzoned
     */
    public function matchesLocation(?string $zone, ?string $resource, ?string $page): bool
    {
        return $this->constraintAllows($this->zones, $zone)
            && $this->constraintAllows($this->resources, $resource)
            && $this->constraintAllows($this->pages, $page);
    }

    /**
     * Whether this tour is the one to show, once the screen is known.
     *
     * Visibility covers authorization: {@see HasVisibility::isVisible()} denies
     * an unauthorized component, so `permission()` is answered here too.
     */
    public function appliesTo(?string $zone, ?string $resource, ?string $page): bool
    {
        return $this->matchesLocation($zone, $resource, $page) && $this->isVisible();
    }

    /**
     * Fail now for a tour that could only fail silently later.
     *
     * Called by {@see Tours::register()} rather than by the setters, because a
     * tour is assembled a method at a time and is legitimately stepless in the
     * middle of being built. Registration is the first moment the definition is
     * finished and therefore the first moment it can be judged.
     */
    /**
     * Whether a step belongs on the page being rendered.
     *
     * A step with `on()` belongs on the page it names. One without belongs on
     * the tour's own pages — wherever the tour itself matches — which is every
     * step there was before a tour could span pages, so a tour declared the old
     * way answers exactly as it did.
     */
    public function stepIsHere(TourStep $step, ?string $zone, ?string $resource, ?string $page): bool
    {
        if (! $step->isElsewhere()) {
            return $this->matchesLocation($zone, $resource, $page);
        }

        return $this->constraintAllows($this->zones, $zone)
            && $step->getResource() === $resource
            && $step->getPage() === $page;
    }

    /**
     * Whether this tour may carry on, on the page being rendered, at a step.
     *
     * What a page the tour navigated to asks: the zone still has to be one the
     * tour runs in — a tour does not leave its zone — and the step asked for has
     * to be one that lives here. Anything else is a link somebody edited, and
     * answers no.
     */
    public function continuesAt(int $index, ?string $zone, ?string $resource, ?string $page): bool
    {
        $step = $this->steps[$index] ?? null;

        return $step instanceof TourStep
            && $this->constraintAllows($this->zones, $zone)
            && $this->stepIsHere($step, $zone, $resource, $page)
            && $this->isVisible();
    }

    /**
     * The page the tour starts on, as `[key, page]`, or null when it does not
     * name one.
     *
     * What a step on another page needs to lead back: going *back* from the
     * first step there returns to the last step here. A tour constrained by
     * nothing narrower than a zone has no single page to return to, and simply
     * does not offer the way back.
     *
     * @return array{0: string, 1: string}|null
     */
    public function home(): ?array
    {
        $resource = $this->resources[0] ?? null;

        return $resource === null ? null : [$resource, $this->pages[0] ?? 'index'];
    }

    /**
     * The zone a tour sent to itself should be opened in.
     *
     * The first one it was declared for, or null — which is both "the unzoned
     * mount point" and the honest answer for a tour that named no zone, since
     * an unconstrained tour runs in every zone and no one of them is its home.
     * Only {@see TourDestination::start()} asks, and only to build one URL.
     */
    public function startZone(): ?string
    {
        return $this->zones[0] ?? null;
    }

    public function assertUsable(): void
    {
        if ($this->steps === []) {
            throw TourDefinitionException::stepless($this->id);
        }
    }

    /**
     * One constraint against one value: no constraint allows anything, and a
     * constraint allows exactly what it lists.
     *
     * @param  array<int, string|null>|null  $allowed
     */
    private function constraintAllows(?array $allowed, ?string $value): bool
    {
        return $allowed === null || in_array($value, $allowed, true);
    }
}
