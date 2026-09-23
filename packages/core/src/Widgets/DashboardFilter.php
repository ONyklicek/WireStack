<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Widgets;

use Closure;
use NyonCode\WireCore\Foundation\Concerns\HasLabel;
use NyonCode\WireCore\Foundation\Concerns\HasName;
use NyonCode\WireCore\Foundation\Concerns\HasPlaceholder;
use NyonCode\WireCore\Foundation\Support\EvaluatesClosures;

/**
 * One filter over a whole dashboard — "this month", "this customer".
 *
 * A widget's own `filter()` narrows that widget, which is right for a chart
 * with its own range and wrong for a dashboard read as one picture: with each
 * widget on a different slice, two figures side by side answer different
 * questions and nothing on the page says so. A dashboard filter is one
 * selection every widget reads, held by the host and carried in the page's
 * address — so a dashboard can be sent as a link exactly as its sender saw it.
 *
 * The declaration only. The host resolves the value (from the address, checked
 * against the options) and the dashboard reads it while building its widgets;
 * see {@see Support\DashboardFilterState}.
 */
final class DashboardFilter
{
    use EvaluatesClosures;
    use HasLabel;
    use HasName;
    use HasPlaceholder;

    /** @var array<int|string, string>|Closure */
    private array|Closure $options = [];

    private ?string $default = null;

    private bool $buttons = false;

    private function __construct(string $name)
    {
        $this->name = $name;
    }

    public static function make(string $name): self
    {
        return new self($name);
    }

    /**
     * The values to choose from, as `value => label`.
     *
     * A closure is resolved when the filter is drawn or a value is checked, so
     * a list read from the database costs nothing on a dashboard that never
     * renders its filter bar.
     *
     * @param  array<int|string, string>|Closure(): array<int|string, string>  $options
     */
    public function options(array|Closure $options): static
    {
        $this->options = $options;

        return $this;
    }

    /** @return array<string, string> Keyed by the value as the address carries it — a string */
    public function getOptions(): array
    {
        $options = $this->evaluate($this->options);
        $normalised = [];

        foreach (is_array($options) ? $options : [] as $value => $label) {
            $normalised[(string) $value] = (string) $label;
        }

        return $normalised;
    }

    /**
     * The value when nothing is chosen; null means the filter narrows nothing.
     *
     * A default that is one of the options ("this month") is what the dashboard
     * shows before anybody touches it. Null is "all of them", drawn as the
     * placeholder — which is the usual shape for a filter over records, where
     * no selection is a real and common answer.
     */
    public function default(int|string|null $default): static
    {
        $this->default = $default === null ? null : (string) $default;

        return $this;
    }

    public function getDefault(): ?string
    {
        return $this->default;
    }

    /** Draw the options as a row of buttons rather than a select. */
    public function buttons(bool $buttons = true): static
    {
        $this->buttons = $buttons;

        return $this;
    }

    public function isButtons(): bool
    {
        return $this->buttons;
    }

    /**
     * The value to use for what arrived, which is never trusted.
     *
     * The address can carry anything, so a value is kept only when it is one of
     * the options; everything else — a stale link, a typo, a value from before
     * the options changed — is the default rather than an error or, worse, a
     * value handed to the query that reads it.
     */
    public function resolve(mixed $value): ?string
    {
        if (is_int($value) || (is_string($value) && $value !== '')) {
            $value = (string) $value;

            if (array_key_exists($value, $this->getOptions())) {
                return $value;
            }
        }

        return $this->default;
    }
}
