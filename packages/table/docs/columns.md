# Columns

## Base Column

All column types extend the base `Column` class and share these methods.

### Creating a Column

```php
use NyonCode\WireTable\Columns\TextColumn;

TextColumn::make('name')
```

The `name` parameter corresponds to the model attribute or relationship path (e.g., `'user.name'`).

### Label

```php
TextColumn::make('first_name')
    ->label('First Name')  // Custom label; auto-generated from name if omitted
```

### Sorting

```php
TextColumn::make('name')
    ->sortable()

// Custom sort callback
TextColumn::make('full_name')
    ->sortable(query: fn (Builder $query, string $direction) =>
        $query->orderBy('last_name', $direction)
            ->orderBy('first_name', $direction)
    )
```

### Searching

```php
TextColumn::make('name')
    ->searchable()

// Search across multiple columns
TextColumn::make('name')
    ->searchable(['first_name', 'last_name'])

// Custom search callback
TextColumn::make('name')
    ->searchable(query: fn (Builder $query, string $search) =>
        $query->whereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$search}%"])
    )
```

### Column Filters

Column filters render inline filter inputs directly in the column header. They are separate from the [table-level filters](filters.md) — column filters appear inside each column's `<th>` and filter by that specific column.

#### Text Filter (default)

```php
TextColumn::make('name')
    ->filterable()                     // Text input with LIKE search
    ->filterPlaceholder('Search...')
    ->filterDebounce(300)              // Input debounce in ms

// Change the SQL operator
TextColumn::make('code')
    ->filterable()
    ->filterOperator('equals')         // Exact match instead of LIKE
```

**Available operators:** `like` (default), `equals`/`=`, `starts_with`, `ends_with`, `>`, `>=`, `<`, `<=`, `!=`

#### Select Filter

```php
TextColumn::make('status')
    ->filterAsSelect([
        'active' => 'Active',
        'inactive' => 'Inactive',
        'pending' => 'Pending',
    ], placeholder: 'All statuses')
```

#### Date Filter

```php
TextColumn::make('created_at')
    ->filterAsDate(
        minDate: '2024-01-01',
        maxDate: now()->format('Y-m-d')
    )
```

#### Date Range Filter

```php
TextColumn::make('created_at')
    ->filterAsDateRange(
        minDate: '2024-01-01',
        maxDate: '2025-12-31'
    )
```

Renders two inputs (from/to) and applies `whereDate($column, '>=', $from)` and `whereDate($column, '<=', $to)`.

#### Number Range Filter

```php
TextColumn::make('price')
    ->filterAsNumberRange(
        min: 0,
        max: 10000,
        step: 100
    )
```

Renders two inputs (min/max) and applies `where($column, '>=', $min)` and `where($column, '<=', $max)`.

#### Boolean Filter

```php
BooleanColumn::make('is_active')
    ->filterAsBoolean(
        trueLabel: 'Active',
        falseLabel: 'Inactive'
    )
```

Renders a three-state dropdown (All / Active / Inactive). False matches both `false` and `NULL` values.

#### Custom Filter Query

```php
TextColumn::make('full_name')
    ->filterable()
    ->filterUsing(fn (Builder $query, mixed $value) =>
        $query->where('first_name', 'like', "%{$value}%")
            ->orWhere('last_name', 'like', "%{$value}%")
    )
```

#### Generic Filterable

```php
// Short-hand with type and options
TextColumn::make('category')
    ->filterable(type: 'select', options: ['a' => 'A', 'b' => 'B'])
```

#### Relationship Column Filters

Column filters on relationship columns automatically use `whereHas`:

```php
TextColumn::make('user.role')
    ->filterAsSelect(['admin' => 'Admin', 'user' => 'User'])
// Generates: whereHas('user', fn($q) => $q->where('role', $value))
```

#### Resetting Column Filters

In your Livewire component:

```php
$this->resetColumnFilters();    // Reset only column filters
$this->resetTableFilters();     // Reset column filters + table filters + search
```

---

### Visibility

```php
TextColumn::make('secret')
    ->hidden()                    // Always hidden
    ->hidden(fn () => ! auth()->user()->isAdmin())  // Conditionally hidden

TextColumn::make('details')
    ->visibleFrom('lg')           // Show only on lg+ screens
    ->hiddenFrom('md')            // Hide on md+ screens

TextColumn::make('actions')
    ->toggleable()                // User can show/hide via toggle (default: true)
    ->toggleable(false)           // Disable toggle
```

### Formatting

```php
TextColumn::make('bio')
    ->formatStateUsing(fn (mixed $value) => str()->limit($value, 100))

TextColumn::make('description')
    ->html()                      // Render as raw HTML
```

### Placeholder

```php
TextColumn::make('phone')
    ->placeholder('N/A')          // Show when value is null/empty
```

### URL

```php
TextColumn::make('website')
    ->url(fn (Model $record) => $record->website_url)
```

### Relationship Columns

Dot notation is automatically resolved to Eloquent relationships:

```php
TextColumn::make('user.name')           // belongsTo
TextColumn::make('user.role.title')     // Nested relationship
TextColumn::make('category.name')
    ->searchable()                      // Automatically uses whereHas
```

### Mobile Display

```php
TextColumn::make('email')
    ->mobileDisplay(fn (mixed $value) => str()->limit($value, 20))
```

### Summary / Footer

```php
TextColumn::make('amount')
    ->summary(fn (Collection $records) => $records->sum('amount'))
```

---

## Column Types

### TextColumn

General-purpose text column with formatting options.

```php
use NyonCode\WireTable\Columns\TextColumn;

TextColumn::make('price')
    ->money('CZK')                     // Currency formatting (default: CZK)

TextColumn::make('quantity')
    ->numeric(0, ',', ' ')             // Decimal places, separators

TextColumn::make('created_at')
    ->date('d.m.Y')                    // Date formatting (default: d.m.Y)

TextColumn::make('updated_at')
    ->dateTime('d.m.Y H:i')           // DateTime formatting

TextColumn::make('published_at')
    ->since()                          // Relative time ("2 days ago")

TextColumn::make('code')
    ->fontFamily('monospace')          // Custom font
```

---

### BadgeColumn

Displays values as colored badges with optional icons.

```php
use NyonCode\WireTable\Columns\BadgeColumn;

BadgeColumn::make('status')
    ->colors([
        'active' => 'success',
        'inactive' => 'danger',
        'pending' => 'warning',
    ])
    ->icons([
        'active' => 'check',
        'inactive' => 'x',
        'pending' => 'clock',
    ])
    ->size('sm')                       // xs, sm, md, lg

// Dynamic color/icon
BadgeColumn::make('priority')
    ->colorUsing(fn (mixed $state) => match(true) {
        $state > 8 => 'danger',
        $state > 5 => 'warning',
        default => 'success',
    })
    ->iconUsing(fn (mixed $state) => $state > 8 ? 'exclamation' : 'check')
```

**Available colors:** `primary`, `success`, `danger`, `warning`, `info`, `gray`, `purple`, `pink`, `orange`, `teal`, `cyan`, `indigo`

---

### BooleanColumn

Displays boolean values as icons.

```php
use NyonCode\WireTable\Columns\BooleanColumn;

BooleanColumn::make('is_active')
    ->trueIcon('check-circle')         // Default: check-circle
    ->falseIcon('x-circle')            // Default: x-circle
    ->trueColor('success')             // Default: success
    ->falseColor('danger')             // Default: danger
    ->labels('Active', 'Inactive')     // Optional text labels
```

---

### ToggleColumn

Inline toggle switch for boolean editing.

```php
use NyonCode\WireTable\Columns\ToggleColumn;

ToggleColumn::make('is_active')
    ->onColor('primary')               // Default: primary
    ->offColor('gray')                 // Default: gray
    ->onIcon('check')                  // Optional icon when on
    ->offIcon('x')                     // Optional icon when off
    ->disabled()                       // Disable toggle
    ->disabled(fn (Model $record) => $record->is_locked)  // Conditional
```

---

### SelectColumn

Inline select dropdown for editing.

```php
use NyonCode\WireTable\Columns\SelectColumn;

SelectColumn::make('status')
    ->options([
        'draft' => 'Draft',
        'published' => 'Published',
        'archived' => 'Archived',
    ])
    ->disabled(fn (Model $record) => $record->is_locked)

// Dynamic options
SelectColumn::make('category_id')
    ->options(fn () => Category::pluck('name', 'id')->toArray())
    ->native(false)                    // Custom styled dropdown
```

---

### TextInputColumn

Full-featured inline text input with validation and save behavior.

```php
use NyonCode\WireTable\Columns\TextInputColumn;

TextInputColumn::make('name')
    ->rules(['required', 'string', 'max:255'])
    ->placeholder('Enter name')
    ->saveOnBlur()                     // Save when focus leaves (default: true)
    ->saveOnEnter()                    // Save on Enter key (default: true)
```

#### Input Types

```php
TextInputColumn::make('email')->email()
TextInputColumn::make('phone')->tel()
TextInputColumn::make('website')->url()
TextInputColumn::make('secret')->password()
TextInputColumn::make('quantity')->numeric()
TextInputColumn::make('count')->integer()
TextInputColumn::make('price')->decimal(2)
TextInputColumn::make('amount')->money()        // Formatted currency
TextInputColumn::make('price')->czk()            // Czech koruna
TextInputColumn::make('data')->type('search')    // Any HTML input type
```

#### Constraints

```php
TextInputColumn::make('code')
    ->maxLength(10)
    ->minLength(3)
    ->pattern('[A-Z0-9]+')
    ->required()

TextInputColumn::make('quantity')
    ->min('0')
    ->max('1000')
    ->step('1')
```

#### Validation

```php
TextInputColumn::make('email')
    ->rules(['required', 'email', 'unique:users,email'])
    ->rule('max:255')                  // Add single rule
    ->validationMessages([
        'email.required' => 'Email is required.',
    ])
    ->validationAttribute('email address')
    ->liveValidation(debounce: 500)    // Validate as user types
```

#### Permissions and State

```php
TextInputColumn::make('salary')
    ->editPermission('edit-salary')    // Require permission
    ->disabled(fn (Model $record) => $record->is_locked)
    ->readonly(fn (Model $record) => ! $record->is_editable)
```

#### Formatting and Transforms

```php
TextInputColumn::make('code')
    ->uppercase()                      // Convert to uppercase
    ->trim()                           // Trim whitespace
    ->nullable()                       // Allow null

TextInputColumn::make('price')
    ->beforeSave(fn ($value) => (int)($value * 100))    // Transform before save
    ->afterLoad(fn ($value) => $value / 100)             // Transform after load
    ->displayFormat(fn ($value) => number_format($value, 2) . ' Kc')

TextInputColumn::make('name')
    ->inputPrefix('Mr.')
    ->inputSuffix('@example.com')
    ->helperText('Enter your full name')
    ->autocomplete('name')
    ->inputClass('font-mono')
```

#### Custom Save Logic

```php
TextInputColumn::make('name')
    ->saveUsing(fn (Model $record, string $column, mixed $value) =>
        $record->updateQuietly([$column => $value])
    )
    ->afterStateUpdated(fn (Model $record, mixed $value) =>
        Log::info("Updated {$record->id}: {$value}")
    )
```

---

### ImageColumn

Displays images with sizing and shape options.

```php
use NyonCode\WireTable\Columns\ImageColumn;

ImageColumn::make('avatar')
    ->circular()                       // Round image
    ->size('md')                       // xs, sm, md, lg, xl, 2xl
    ->defaultImageUrl('/images/placeholder.png')
    ->disk('s3')                       // Storage disk
    ->visibility('public')            // 'public' or 'protected'

// Multiple stacked images
ImageColumn::make('team_avatars')
    ->stacked()
    ->stackLimit(3)
    ->ring(2, 'white')
```

---

### IconColumn

Displays icons based on values.

```php
use NyonCode\WireTable\Columns\IconColumn;

IconColumn::make('status')
    ->icons([
        'active' => 'check-circle',
        'inactive' => 'x-circle',
        'pending' => 'clock',
    ])
    ->colors([
        'active' => 'success',
        'inactive' => 'danger',
        'pending' => 'warning',
    ])
    ->iconSize('md')                   // xs, sm, md, lg, xl

// Boolean mode shortcut
IconColumn::make('is_verified')
    ->boolean()
    ->booleanColors('success', 'gray')
```

---

### ButtonColumn

Renders an action button in the column.

```php
use NyonCode\WireTable\Columns\ButtonColumn;

ButtonColumn::make('download')
    ->buttonLabel('Download')
    ->buttonIcon('download')
    ->buttonColor('primary')           // primary, danger, success, warning, info, secondary
    ->buttonSize('sm')                 // xs, sm, md, lg
    ->buttonVariant('filled')          // filled, outlined, link
    ->action(fn (Model $record) => $record->generateDownload())

// Shortcuts
ButtonColumn::make('delete')
    ->buttonLabel('Delete')
    ->danger()                         // Red color
    ->requiresConfirmation(
        title: 'Delete record?',
        description: 'This action cannot be undone.',
    )
    ->action(fn (Model $record) => $record->delete())

// Link button
ButtonColumn::make('view')
    ->buttonLabel('View')
    ->url(fn (Model $record) => route('records.show', $record))
    ->outlined()

// Livewire method call
ButtonColumn::make('approve')
    ->buttonLabel('Approve')
    ->success()
    ->livewireAction('approveRecord')

// Dynamic per-record customization (all accept Closures)
ButtonColumn::make('action')
    ->buttonLabel(fn (Model $record) => $record->is_draft ? 'Publish' : 'Unpublish')
    ->buttonColor(fn (Model $record) => $record->is_draft ? 'success' : 'warning')
    ->visibleWhen(fn (Model $record) => $record->canBeToggled())
    ->disabled(fn (Model $record) => $record->is_locked)
    ->loading()                        // Show spinner during action
```

---

### StackedColumn

Displays multiple values in a stacked layout with an optional avatar.

```php
use NyonCode\WireTable\Columns\StackedColumn;

StackedColumn::make('user_info')
    ->primary('name')
    ->secondary('email')
    ->avatar('avatar_url')
    ->circular()                       // Round avatar
    ->avatarSize('md')                 // xs, sm, md, lg, xl, 2xl
    ->searchable(['name', 'email'])

// Custom stack items
StackedColumn::make('details')
    ->stack([
        ['column' => 'name', 'class' => 'font-bold text-gray-900'],
        ['column' => 'email', 'class' => 'text-sm text-gray-500'],
        ['column' => 'phone', 'class' => 'text-xs text-gray-400', 'prefix' => 'Tel: '],
    ])
```

---

### SplitColumn

Arranges multiple columns side by side in a single cell.

```php
use NyonCode\WireTable\Columns\SplitColumn;

SplitColumn::split([
    TextColumn::make('first_name'),
    TextColumn::make('last_name'),
])

SplitColumn::make('address')
    ->columns([
        TextColumn::make('city'),
        TextColumn::make('country'),
    ])
    ->vertical()                       // Stack vertically instead
    ->gap('4')                         // Spacing (1-12)
    ->alignCenter()
```

---

### PollColumn

Column that auto-refreshes to track status or progress.

```php
use NyonCode\WireTable\Columns\PollColumn;

// Job status polling
PollColumn::make('status')
    ->forJobStatus()                   // Preset: polls until completed/failed
    ->badge()
    ->colors([
        'pending' => 'warning',
        'processing' => 'info',
        'completed' => 'success',
        'failed' => 'danger',
    ])

// Progress bar polling
PollColumn::make('progress')
    ->forProgress('progress', 100)     // Polls until progress reaches 100

// Custom polling
PollColumn::make('sync_status')
    ->intervalSeconds(5)
    ->stopWhen(fn (Model $record) => in_array($record->status, ['done', 'error']))
    ->maxPolls(60)
    ->badge()
    ->stateIcons([
        'syncing' => 'refresh',
        'done' => 'check',
        'error' => 'x',
    ])
    ->stateColors([
        'syncing' => 'info',
        'done' => 'success',
        'error' => 'danger',
    ])
    ->loadingIndicator(position: 'after')
    ->animateTransitions()
    ->rowLevelPolling()                // Refresh only this row
    ->onComplete(fn (Model $record) => logger("Sync done: {$record->id}"))
```

---

## Available Icons

All columns and actions can use built-in icons:

`pencil`, `trash`, `eye`, `plus`, `download`, `upload`, `duplicate`, `copy`, `check`, `x`, `cog`, `mail`, `exclamation`, `warning`, `user`, `users`, `archive`, `refresh`, `key`, `shield`, `lock`, `link`, `dots-vertical`, `filter`, `chevron-down`, `calendar`, `star`, `heart`, `document`, `folder`, `check-circle`, `x-circle`, `clock`

Custom icons can be registered:

```php
// In your column/action class
$this->registerIcons([
    'custom-icon' => 'M12 2L2 22h20L12 2z',  // SVG path
]);
```
