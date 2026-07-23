---
order: 39
nav: false
---

# Patterns & Recipes

## E-commerce Product Filters

```php
$table->filters([
    SelectFilter::make('category')
        ->options(Category::orderBy('name')->pluck('name', 'id')->toArray())
        ->searchable()
        ->query(fn (Builder $q, string $v) => $q->whereHas('category', fn ($q) => $q->where('id', $v))),

    SelectFilter::make('brand')
        ->options(Brand::orderBy('name')->pluck('name', 'id')->toArray())
        ->searchable()
        ->multiple(),

    NumberRangeFilter::make('price')
        ->min(0)
        ->max(100000)
        ->step(100)
        ->minLabel('Min Price')
        ->maxLabel('Max Price'),

    TernaryFilter::make('in_stock')
        ->label('Availability')
        ->trueLabel('In Stock')
        ->falseLabel('Out of Stock')
        ->query(fn (Builder $q, bool $value) => $value
            ? $q->where('stock_quantity', '>', 0)
            : $q->where('stock_quantity', '<=', 0)),

    SelectFilter::make('rating')
        ->options([
            '5' => '★★★★★',
            '4' => '★★★★☆ and up',
            '3' => '★★★☆☆ and up',
        ])
        ->query(fn (Builder $q, string $v) => $q->where('avg_rating', '>=', (int)$v)),

    TernaryFilter::make('has_discount')
        ->label('Discounted')
        ->query(fn (Builder $q, bool $value) => $value
            ? $q->whereNotNull('discount_percent')
            : $q->whereNull('discount_percent')),
]);
```

## Admin User Filters

```php
$table->filters([
    SelectFilter::make('role')
        ->options(Role::pluck('display_name', 'name')->toArray())
        ->multiple(),

    TernaryFilter::make('email_verified_at')
        ->label('Email Verified')
        ->nullable(),

    DateFilter::make('created_at')
        ->range()
        ->fromLabel('Registered after')
        ->toLabel('Registered before'),

    TernaryFilter::make('two_factor_enabled')
        ->label('2FA Enabled'),

    SelectFilter::make('last_activity')
        ->label('Activity')
        ->options([
            'today' => 'Active today',
            'week' => 'Active this week',
            'month' => 'Active this month',
            'inactive' => 'Inactive (30+ days)',
        ])
        ->query(fn (Builder $q, string $v) => match($v) {
            'today' => $q->whereDate('last_active_at', today()),
            'week' => $q->where('last_active_at', '>=', now()->subWeek()),
            'month' => $q->where('last_active_at', '>=', now()->subMonth()),
            'inactive' => $q->where('last_active_at', '<', now()->subMonth()),
        }),
]);
```

## Order Management Filters

```php
$table->filters([
    SelectFilter::make('status')
        ->options([
            'pending' => 'Pending',
            'processing' => 'Processing',
            'shipped' => 'Shipped',
            'delivered' => 'Delivered',
            'cancelled' => 'Cancelled',
            'refunded' => 'Refunded',
        ])
        ->multiple()
        ->default(['pending', 'processing']),   // pre-select active orders

    DateFilter::make('ordered_at')
        ->range(),

    NumberRangeFilter::make('total')
        ->min(0)
        ->max(1000000)
        ->step(100)
        ->minLabel('Min Total')
        ->maxLabel('Max Total'),

    SelectFilter::make('payment_method')
        ->options([
            'card' => 'Credit Card',
            'bank' => 'Bank Transfer',
            'cash' => 'Cash on Delivery',
        ]),

    TernaryFilter::make('has_notes')
        ->label('Has Notes')
        ->query(fn (Builder $q, bool $value) => $value
            ? $q->whereNotNull('notes')->where('notes', '!=', '')
            : $q->where(fn ($q2) => $q2->whereNull('notes')->orWhere('notes', ''))),
]);
```

## Filter with Dependent Options

```php
// Country → City cascade (requires Livewire re-render)
SelectFilter::make('country')
    ->options(Country::pluck('name', 'id')->toArray())
    ->searchable(),

SelectFilter::make('city')
    ->options(function () {
        $countryId = $this->tableFilters['country'] ?? null;
        if (! $countryId) {
            return [];
        }
        return City::where('country_id', $countryId)->pluck('name', 'id')->toArray();
    })
    ->searchable()
    ->visible(fn () => ! empty($this->tableFilters['country'])),
```
