---
order: 34
nav: false
---

# Custom Filter Class

For reusable, complex filters, extend the base `Filter` class.

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
    public function apply(Builder $query, mixed $value): Builder
    {
        if (empty($value)) {
            return $query;
        }

        // Your custom query logic
        return $query->where(...);
    }

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
