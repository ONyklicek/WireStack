---
order: 34
summary: "Extending the base filter class: your own control, your own query, reusable across tables."
---

# Custom Filter Class

For reusable, complex filters, extend the base `Filter` class.

## How It Works

A table narrows its query in one of two ways, and which one a filter takes
decides whether your `apply()` runs at all:

1. **The query planner** — the default for a single scalar value. The table
   reads the filter's column and value and writes `column = value` itself.
   `apply()` is **not called**.
2. **`apply()`** — taken when the filter has a `->query()` callback, when its
   value is an array and it is not `->multiple()`, or when
   `bypassesPlanner()` returns `true`.

So a filter that overrides `apply()` and submits one value must also return
`true` from `bypassesPlanner()`, or the planner quietly answers for it. Before
either path, a value of `null`, `''` or `[]` means the filter is off and it is
skipped.

The control is drawn by `render($value)`. The base implementation renders
`filterView()` and looks for it under `wire-table::` first, so a view named
like a shipped one (`tables.filters.select`) draws the shipped control. Render
your own view by name, as the skeleton below does, and the name cannot collide.

## Generating One

```bash
php artisan make:wire-filter Region
```

writes `app/Tables/Filters/RegionFilter.php` and
`resources/views/tables/filters/region.blade.php`: the class overrides
`apply()`, `bypassesPlanner()` and `render()`, and the view binds a text input
to `tableState.filters.region.value`. An existing view is never overwritten;
`--force` replaces the class only. `php artisan vendor:publish --tag=wire-table::stubs`
copies `filter.stub` and `filter-view.stub` to `stubs/wire-table/`, and the
command reads them from there first.

## Skeleton

```php
namespace App\Wire\Filters;

use NyonCode\WireTable\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;

class MyFilter extends Filter
{
    // Custom properties
    protected string $myOption = 'default';

    // Fluent setter
    public function myOption(string $value): static
    {
        $this->myOption = $value;
        return $this;
    }

    // Getter (for Blade view)
    public function getMyOption(): string
    {
        return $this->myOption;
    }

    // Override apply logic
    public function apply(Builder $query, mixed $value): Builder // [tl! focus:start]
    {
        if (empty($value)) {
            return $query;
        }

        // Your custom query logic
        return $query->where(...);
    }

    // Send a single value through apply() rather than the query planner
    public function bypassesPlanner(): bool
    {
        return true;
    } // [tl! focus:end]

    // Custom Blade view (optional) — override render() and point at your view.
    // The view receives 'filter' (this instance) and 'value' (current state).
    public function render(mixed $value = null): string
    {
        if (! $this->canView()) {
            return '';
        }

        return view('filters.my-filter', ['filter' => $this, 'value' => $value])->render();
    }
}
```

## Example: JSON Contains Filter

```php
namespace App\Wire\Filters;

use NyonCode\WireTable\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;

// Extends SelectFilter so it inherits ->options()/->searchable()/->native().
class JsonContainsFilter extends SelectFilter
{
    protected string $jsonPath = '';

    public function jsonPath(string $path): static
    {
        $this->jsonPath = $path;
        return $this;
    }

    public function apply(Builder $query, mixed $value): Builder
    {
        if (empty($value)) {
            return $query;
        }

        $column = $this->getColumn();

        if ($this->jsonPath) {
            return $query->whereJsonContains("{$column}->{$this->jsonPath}", $value);
        }

        return $query->whereJsonContains($column, $value);
    }

    // One selected option is a scalar, which the planner would answer
    public function bypassesPlanner(): bool // [tl! focus:start]
    {
        return true;
    } // [tl! focus:end]
}
```

Usage:
```php
JsonContainsFilter::make('permissions')
    ->column('settings')
    ->jsonPath('permissions')
    ->options(['admin' => 'Admin', 'edit' => 'Edit', 'view' => 'View'])
```

## Example: Geo Radius Filter

```php
namespace App\Wire\Filters;

use NyonCode\WireTable\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;

class GeoRadiusFilter extends Filter
{
    protected float $defaultRadius = 10.0;
    protected string $latColumn = 'latitude';
    protected string $lngColumn = 'longitude';

    public function radius(float $km): static
    {
        $this->defaultRadius = $km;
        return $this;
    }

    public function coordinates(string $lat, string $lng): static
    {
        $this->latColumn = $lat;
        $this->lngColumn = $lng;
        return $this;
    }

    public function apply(Builder $query, mixed $value): Builder
    {
        if (empty($value['lat']) || empty($value['lng'])) {
            return $query;
        }

        $lat = (float) $value['lat'];
        $lng = (float) $value['lng'];
        $radius = (float) ($value['radius'] ?? $this->defaultRadius);

        // Haversine formula (returns km)
        $haversine = "(6371 * acos(
            cos(radians(?)) * cos(radians({$this->latColumn})) *
            cos(radians({$this->lngColumn}) - radians(?)) +
            sin(radians(?)) * sin(radians({$this->latColumn}))
        ))";

        return $query
            ->whereRaw("{$haversine} <= ?", [$lat, $lng, $lat, $radius]);
    }

    public function getDefaultRadius(): float
    {
        return $this->defaultRadius;
    }

    public function render(mixed $value = null): string
    {
        if (! $this->canView()) {
            return '';
        }

        return view('filters.geo-radius', ['filter' => $this, 'value' => $value])->render();
    }
}
```
