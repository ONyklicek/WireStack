# Columns

Wire Table provides 13 column types. All extend the base `Column` class (1793 lines) which itself extends `DataComponent` from wire-core.

---

## Table of Contents

1. [Shared Column API](#shared-column-api)
2. [Relation Paths & Dot Notation](#relation-paths--dot-notation)
3. [TextColumn](#textcolumn)
4. [BadgeColumn](#badgecolumn)
5. [BooleanColumn](#booleancolumn)
6. [IconColumn](#iconcolumn)
7. [ImageColumn](#imagecolumn)
8. [ButtonColumn](#buttoncolumn)
9. [ToggleColumn](#togglecolumn)
10. [SelectColumn](#selectcolumn)
11. [TextInputColumn](#textinputcolumn)
12. [StackedColumn](#stackedcolumn)
13. [SplitColumn](#splitcolumn)
14. [PollColumn](#pollcolumn)
15. [Column-Level Filtering](#column-level-filtering)
16. [Inline Editing](#inline-editing)
17. [Patterns & Recipes](#patterns--recipes)

---

## Shared Column API

Every column inherits these capabilities from the base `Column` class.

### Factory & Identity

```php
Column::make(string $name)           // static factory — $name is dot-notation path
->label(string|Closure $label)        // display label in <th> (auto-generated from name)
->getName(): string                   // get column name
->getLabel(): string                  // get resolved label
```

### Sorting

```php
->sortable(bool|Closure $sortable = true)
->isSortable(): bool

// Custom sort logic
->sortUsing(Closure $fn)
```

```php
TextColumn::make('full_name')
    ->sortable()
    ->sortUsing(function (Builder $query, string $direction) {
        $query->orderBy('last_name', $direction)
              ->orderBy('first_name', $direction);
    })
```

### Searching

```php
->searchable(bool|array $searchable = true)
->isSearchable(): bool

// Explicit DB columns to search (when column name is virtual)
->searchColumns(array $columns)

// Custom search logic
->searchUsing(Closure $fn)

// Get resolved search columns
->getSearchColumns(): array
```

```php
// Search across multiple DB columns
TextColumn::make('user')
    ->searchable()
    ->searchColumns(['first_name', 'last_name', 'email'])

// Custom search logic
TextColumn::make('full_name')
    ->searchable()
    ->searchUsing(function (Builder $query, string $search) {
        $query->where(DB::raw("CONCAT(first_name, ' ', last_name)"), 'like', "%{$search}%");
    })
```

### Visibility & Toggleability

```php
->hidden(bool|Closure $hidden = true)        // hide column
->visible(bool|Closure $visible = true)      // show column (inverse of hidden)
->isHidden(): bool

// User-toggleable (column picker)
->toggleable(bool $toggleable = true)

// Permission-based
->permission(string $permission)             // visible only if user has permission
->visible(Closure $fn)                       // custom visibility callback
```

### Responsive Breakpoints

```php
->visibleFrom(string $breakpoint)      // hidden below this breakpoint
->hiddenFrom(string $breakpoint)       // hidden from this breakpoint up
->onlyOnMobile()                       // visible only on mobile (<md)
->onlyOnDesktop()                      // visible only on desktop (≥lg)
->onlyOnTabletAndUp()                  // visible from md up
->onlyOnLargeScreens()                 // visible from xl up
```

```php
TextColumn::make('phone')
    ->visibleFrom('md')          // hidden on mobile, visible from md

TextColumn::make('notes')
    ->onlyOnLargeScreens()       // only visible on xl+
```

### Responsive Display Variants

```php
// Custom render for mobile vs desktop
->mobileDisplayUsing(Closure $fn)
->desktopDisplayUsing(Closure $fn)
->hasResponsiveDisplay(): bool
```

```php
TextColumn::make('user')
    ->mobileDisplayUsing(fn ($record) => $record->name)
    ->desktopDisplayUsing(fn ($record) => "{$record->name} <{$record->email}>")
```

### Value Formatting

```php
->formatStateUsing(Closure $fn)        // transform value for display
->displayUsing(Closure $fn)            // alias for formatStateUsing
->default(mixed $value)                // value when state is null
->placeholder(string $text)            // text shown when value is null/empty
->limit(int $chars)                    // truncate to N characters
->words(int $words)                    // truncate to N words
->prefix(string $prefix)              // prepend text
->suffix(string $suffix)              // append text
->html(bool $html = true)             // render value as raw HTML
->wrap(bool $wrap = true)             // allow text wrapping (default: nowrap)
```

```php
TextColumn::make('price')
    ->prefix('$')
    ->suffix(' USD')
    ->placeholder('N/A')

TextColumn::make('bio')
    ->limit(100)
    ->tooltip(fn ($record) => $record->bio)   // show full on hover

TextColumn::make('content')
    ->html()
    ->wrap()
    ->limit(200)
```

### Text Styling

```php
->size(string $size)                   // 'xs', 'sm', 'md', 'lg', 'xl'
->weight(string $weight)              // 'thin', 'light', 'normal', 'medium', 'semibold', 'bold', 'extrabold'
->textColor(string $color)            // Tailwind color name or 'gray', 'primary', etc.
->fontFamily(string $family)          // 'sans', 'serif', 'mono' (TextColumn only)
```

```php
TextColumn::make('name')
    ->weight('bold')
    ->size('lg')

TextColumn::make('subtitle')
    ->size('sm')
    ->textColor('gray')
    ->weight('light')
```

### Width & Alignment

```php
->width(string $width)                 // CSS width: '200px', '20%', 'auto'
->alignment(string $alignment)         // 'left', 'center', 'right'
->alignLeft()                          // shortcut
->alignCenter()                        // shortcut
->alignRight()                         // shortcut
```

### Icons

```php
->icon(string|Closure $icon)
->iconPosition(string $position)       // 'before' (default), 'after'
->color(string|Closure $color)         // icon/text color
```

```php
TextColumn::make('email')
    ->icon('mail')
    ->iconPosition('before')
    ->color('primary')
```

### URL (Clickable Cell)

```php
->url(string|Closure $url)             // make cell a link
->openUrlInNewTab(bool $newTab = true)
->actionUrl(string|Closure $url)       // alias
```

```php
TextColumn::make('name')
    ->url(fn ($record) => route('users.show', $record))
    ->openUrlInNewTab()
    ->color('primary')
```

### Copyable

```php
->copyable(bool $copyable = true)      // click-to-copy icon
->copyMessage(string $msg)             // feedback text after copy
```

### Tooltip & Description

```php
->tooltip(string|Closure $tooltip)     // hover tooltip
->description(string|Closure $desc)    // secondary text below value
```

```php
TextColumn::make('title')
    ->description(fn ($record) => Str::limit($record->body, 50))
    ->tooltip(fn ($record) => "Created: {$record->created_at->format('d.m.Y')}")
```

### Summary (Aggregate Footer)

```php
->summarize(string $aggregate, ?string $label = null)
```

Available aggregates: `'sum'`, `'avg'`, `'count'`, `'min'`, `'max'`, `'range'`

See [Advanced — Summary](advanced.md#summary-footer) for details.

### Extra HTML Attributes

```php
->extraAttributes(array $attrs)        // on <td>
->extraHeaderAttributes(array $attrs)  // on <th>
```

```php
TextColumn::make('notes')
    ->extraAttributes(['data-testid' => 'notes-cell'])
    ->extraHeaderAttributes(['class' => 'bg-gray-100'])
```

### Pivot Columns

```php
->pivot(bool $isPivot = true)          // marks as pivot table column
->isPivot(): bool
```

For many-to-many relationships with pivot data:
```php
TextColumn::make('roles.pivot.assigned_at')
    ->pivot()
    ->dateTime('d.m.Y')
```

### State Access

```php
->state(mixed $value)                  // override state value
->getState(Model $record): mixed       // resolve state from record
```

---

## Relation Paths & Dot Notation

Column names support deep dot-notation for relations, aggregates, pivots, and morphs. The Core Relation AST parser automatically determines JOINs, eager loads, and subqueries.

### Simple Relation

```php
TextColumn::make('author.name')       // belongsTo or hasOne
TextColumn::make('category.title')
```

### Nested Relations

```php
TextColumn::make('author.country.name')        // 3 levels deep
TextColumn::make('order.customer.company.name') // 4 levels deep
```

### Aggregates

```php
TextColumn::make('orders.count')               // withCount
TextColumn::make('items.sum.amount')           // withSum
TextColumn::make('ratings.avg.score')          // withAvg
TextColumn::make('bids.min.amount')            // withMin
TextColumn::make('bids.max.amount')            // withMax
```

### Pivot Data

```php
TextColumn::make('tags.pivot.sort_order')
TextColumn::make('roles.pivot.assigned_at')->dateTime()
```

### Morph Relations

```php
TextColumn::make('commentable.title')          // polymorphic
```

### How It Works

1. `RelationPath::parse('author.country.name')` produces `[RelationSegment('author'), RelationSegment('country'), ColumnSegment('name')]`
2. `QueryPlanner` builds a `RelationGraph` determining optimal access strategy
3. Simple belongsTo relations → LEFT JOIN (enables sort/filter)
4. HasMany/morphMany → eager load (display only)
5. Aggregates → `withCount()` / `withSum()` subqueries
6. Pivot → intermediate table JOIN

---

## TextColumn

General-purpose text column with formatting presets.

```php
use NyonCode\WireTable\Columns\TextColumn;
```

### Basic Usage

```php
TextColumn::make('name')
    ->sortable()
    ->searchable()

TextColumn::make('email')
    ->searchable()
    ->copyable()
    ->copyMessage('Copied!')
    ->icon('mail')
```

### Date/Time Formatting

```php
// PHP date format
TextColumn::make('created_at')
    ->dateTime('d.m.Y H:i')
    ->sortable()

// Date only
TextColumn::make('birth_date')
    ->date('j. F Y')

// Relative time
TextColumn::make('last_login')
    ->since()                    // "2 hours ago", "3 days ago"
    ->sortable()
    ->tooltip(fn ($r) => $r->last_login?->format('d.m.Y H:i:s'))
```

### Money Formatting

```php
TextColumn::make('price')
    ->money('CZK')              // "1 234,50 CZK"
    ->sortable()
    ->alignRight()

TextColumn::make('salary')
    ->money('USD')              // "$1,234.50"
    ->summarize('sum', 'Total')
```

### Numeric Formatting

```php
TextColumn::make('quantity')
    ->numeric(
        decimalPlaces: 0,
        thousandsSeparator: ' '
    )
    ->alignRight()
    ->sortable()

TextColumn::make('percentage')
    ->numeric(decimalPlaces: 1)
    ->suffix('%')
```

### Font Family

```php
TextColumn::make('code')
    ->fontFamily('mono')         // monospace font

TextColumn::make('quote')
    ->fontFamily('serif')
```

### Complete TextColumn API

```php
->date(?string $format = null)       // date formatting
->dateTime(?string $format = null)   // datetime formatting
->since()                            // relative time (diffForHumans)
->money(string $currency)            // currency formatting
->numeric(int $decimalPlaces = 0, string $decimalSeparator = ',', string $thousandsSeparator = ' ')
->fontFamily(string $family)         // 'sans', 'serif', 'mono'
->isMoney(): bool
->getCurrency(): ?string
->isNumeric(): bool
```

---

## BadgeColumn

Colored badge/tag display with state-based color and icon mapping.

```php
use NyonCode\WireTable\Columns\BadgeColumn;
```

### Basic Usage

```php
BadgeColumn::make('status')
    ->colors([
        'success' => 'active',      // green badge for 'active'
        'danger' => 'banned',       // red badge for 'banned'
        'warning' => 'pending',     // yellow badge for 'pending'
        'gray' => 'draft',          // gray badge for 'draft'
        'primary' => 'featured',    // blue badge for 'featured'
        'info' => 'processing',     // cyan badge for 'processing'
    ])
```

### With Icons

```php
BadgeColumn::make('priority')
    ->colors([
        'danger' => 'critical',
        'warning' => 'high',
        'info' => 'medium',
        'gray' => 'low',
    ])
    ->icons([
        'exclamation' => 'critical',
        'arrow-up' => 'high',
        'minus' => 'medium',
        'arrow-down' => 'low',
    ])
```

### Dynamic Colors

```php
// Closure-based color resolution
BadgeColumn::make('score')
    ->colorUsing(fn (int $state) => match(true) {
        $state >= 90 => 'success',
        $state >= 70 => 'info',
        $state >= 50 => 'warning',
        default => 'danger',
    })
    ->iconUsing(fn (int $state) => $state >= 90 ? 'star' : null)
```

### Custom Label + Badge

```php
BadgeColumn::make('role')
    ->formatStateUsing(fn (string $state) => match($state) {
        'super_admin' => 'Super Admin',
        'admin' => 'Administrator',
        'editor' => 'Editor',
        default => ucfirst($state),
    })
    ->colors([
        'danger' => 'super_admin',
        'primary' => 'admin',
        'success' => 'editor',
    ])
```

### Size

```php
BadgeColumn::make('tag')
    ->size('xs')     // xs, sm, md, lg
```

### BadgeColumn API

```php
->colors(array $map)                 // ['color_name' => 'state_value', ...]
->colorUsing(Closure $fn)            // fn($state) => 'color_name'
->icons(array $map)                  // ['icon_name' => 'state_value', ...]
->iconUsing(Closure $fn)             // fn($state) => 'icon_name'
->size(string $size)                 // 'xs', 'sm', 'md', 'lg'
->getSize(): string
->getColorForState($state): string
->getIconForState($state): ?string
```

---

## BooleanColumn

Displays true/false values as colored icons with optional text labels.

```php
use NyonCode\WireTable\Columns\BooleanColumn;
```

### Basic Usage

```php
BooleanColumn::make('is_active')
BooleanColumn::make('email_verified_at')   // null = false, non-null = true
```

### Custom Icons & Colors

```php
BooleanColumn::make('is_verified')
    ->trueIcon('check-circle')
    ->falseIcon('x-circle')
    ->trueColor('success')
    ->falseColor('danger')
```

### With Labels

```php
BooleanColumn::make('is_published')
    ->trueLabel('Published')
    ->falseLabel('Draft')
```

### BooleanColumn API

```php
->trueIcon(string $icon)             // default: 'check-circle'
->falseIcon(string $icon)            // default: 'x-circle'
->trueColor(string $color)           // default: 'success'
->falseColor(string $color)          // default: 'danger'
->trueLabel(?string $label)          // text beside icon (true state)
->falseLabel(?string $label)         // text beside icon (false state)
->labels(string $trueLabel, string $falseLabel)  // set both
```

---

## IconColumn

Displays state-mapped icons with colors and sizes.

```php
use NyonCode\WireTable\Columns\IconColumn;
```

### State-Based Icons

```php
IconColumn::make('status')
    ->icons([
        'check-circle' => 'active',
        'clock' => 'pending',
        'x-circle' => 'inactive',
        'exclamation' => 'error',
    ])
    ->colors([
        'success' => 'active',
        'warning' => 'pending',
        'danger' => ['inactive', 'error'],  // multiple states → one color
    ])
```

### Dynamic Resolution

```php
IconColumn::make('health')
    ->iconUsing(fn ($state) => match(true) {
        $state > 80 => 'check-circle',
        $state > 40 => 'minus',
        default => 'exclamation',
    })
    ->colorUsing(fn ($state) => match(true) {
        $state > 80 => 'success',
        $state > 40 => 'warning',
        default => 'danger',
    })
```

### Boolean Mode

```php
IconColumn::make('has_subscription')
    ->boolean()
    ->trueIcon('star')
    ->trueColor('warning')
    ->falseIcon('minus')
    ->falseColor('gray')
```

### Icon Size

```php
IconColumn::make('rating')
    ->iconSize('lg')    // xs, sm, md, lg, xl
```

### IconColumn API

```php
->icons(array $map)                  // ['icon_name' => 'state_value', ...]
->iconUsing(Closure $fn)             // fn($state) => 'icon_name'
->colors(array $map)                 // ['color_name' => 'state_value'|['values'], ...]
->colorUsing(Closure $fn)            // fn($state) => 'color_name'
->iconSize(string $size)             // 'xs', 'sm', 'md', 'lg', 'xl'
->boolean(bool $boolean = true)      // enable boolean mode
->trueIcon(string $icon)
->falseIcon(string $icon)
->trueColor(string $color)
->falseColor(string $color)
->booleanColors(string $true, string $false)
```

---

## ImageColumn

Displays images/avatars in table cells.

```php
use NyonCode\WireTable\Columns\ImageColumn;
```

### Basic Usage

```php
ImageColumn::make('avatar_url')
    ->circular()
    ->size(40)

ImageColumn::make('photo')
    ->size(60)
    ->defaultImageUrl('/images/placeholder.png')
```

### ImageColumn API

```php
->size(int $pixels)                  // width & height in px
->circular(bool $circular = true)    // rounded-full
->square(bool $square = true)        // rounded-none
->defaultImageUrl(string $url)       // fallback image
```

---

## ButtonColumn

Fully-featured interactive button with actions, confirmation, loading states, and multiple variants.

```php
use NyonCode\WireTable\Columns\ButtonColumn;
```

### Link Button

```php
ButtonColumn::make('view')
    ->buttonLabel('View')
    ->buttonIcon('eye')
    ->buttonColor('primary')
    ->url(fn ($record) => route('users.show', $record))
    ->openUrlInNewTab()
```

### Action Button (Livewire)

```php
ButtonColumn::make('approve')
    ->buttonLabel('Approve')
    ->buttonIcon('check')
    ->buttonColor('success')
    ->action(fn ($record) => $record->approve())
```

### Livewire Method Call

```php
ButtonColumn::make('download')
    ->buttonLabel('Download')
    ->buttonIcon('download')
    ->livewireAction('downloadPdf')  // calls $this->downloadPdf($recordKey)
```

### With Confirmation

```php
ButtonColumn::make('delete')
    ->buttonLabel('Delete')
    ->buttonIcon('trash')
    ->buttonColor('danger')
    ->requiresConfirmation()
    ->confirmationTitle('Delete this record?')
    ->confirmationDescription('This action cannot be undone.')
    ->confirmButtonText('Yes, delete')
    ->cancelButtonText('Cancel')
    ->action(fn ($record) => $record->delete())
```

### Button Variants

```php
// Solid (default)
ButtonColumn::make('save')->buttonColor('primary')

// Outlined
ButtonColumn::make('cancel')->buttonColor('gray')->outlined()

// Link style
ButtonColumn::make('details')->link()

// Danger shortcut
ButtonColumn::make('remove')->danger()

// Success shortcut
ButtonColumn::make('confirm')->success()
```

### Icon Only

```php
ButtonColumn::make('edit')
    ->buttonIcon('pencil')
    ->iconOnly()                     // no label, just icon
    ->tooltip('Edit record')
```

### Sizes

```php
ButtonColumn::make('action')
    ->buttonSize('xs')   // xs, sm, md, lg
```

### Conditional State

```php
ButtonColumn::make('publish')
    ->buttonLabel(fn ($r) => $r->is_published ? 'Unpublish' : 'Publish')
    ->buttonColor(fn ($r) => $r->is_published ? 'gray' : 'success')
    ->buttonIcon(fn ($r) => $r->is_published ? 'x' : 'check')
    ->visibleWhen(fn ($r) => $r->status !== 'draft')
    ->disabled(fn ($r) => $r->is_locked)
    ->disabledTooltip('Record is locked')
```

### Loading State

```php
ButtonColumn::make('process')
    ->buttonLabel('Process')
    ->loading()                      // show spinner during execution
    ->loadingText('Processing...')
```

### ButtonColumn API

```php
->buttonLabel(string|Closure $label)
->buttonIcon(string|Closure $icon)
->buttonColor(string|Closure $color)       // 'primary', 'danger', 'success', 'warning', 'info', 'gray'
->buttonSize(string $size)                 // 'xs', 'sm', 'md', 'lg'
->buttonVariant(string $variant)           // 'solid', 'outlined', 'link'
->iconOnly(bool $iconOnly = true)
->iconPosition(string $position)           // 'before', 'after'
->outlined()                               // shortcut for variant('outlined')
->link()                                   // shortcut for variant('link')
->danger()                                 // shortcut for color('danger')
->success()                                // shortcut for color('success')
->action(Closure $fn)                      // inline action callback
->livewireAction(string $method)           // call Livewire method
->url(string|Closure $url)                 // URL (instead of action)
->openUrlInNewTab(bool $newTab = true)
->requiresConfirmation(bool $requires = true)
->confirmationTitle(string $title)
->confirmationDescription(string $desc)
->confirmButtonText(string $text)
->cancelButtonText(string $text)
->disabled(bool|Closure $disabled = true)
->disabledTooltip(string $tooltip)
->visibleWhen(Closure $fn)
->enabledWhen(Closure $fn)
->loading(bool $loading = true)
->loadingText(string $text)
->extraButtonAttributes(array $attrs)
```

---

## ToggleColumn

Inline toggle switch — saves immediately on click. Dispatches `CellUpdating` / `CellUpdated` events.

```php
use NyonCode\WireTable\Columns\ToggleColumn;
```

### Basic Usage

```php
ToggleColumn::make('is_active')
ToggleColumn::make('is_featured')
```

### Custom Colors

```php
ToggleColumn::make('is_published')
    ->onColor('success')       // green when on
    ->offColor('danger')       // red when off
```

### Custom Icons

```php
ToggleColumn::make('notifications_enabled')
    ->onIcon('bell')
    ->offIcon('bell-slash')
```

### Disabled State

```php
ToggleColumn::make('is_admin')
    ->disabled(fn ($record) => $record->id === auth()->id())  // can't toggle yourself

ToggleColumn::make('is_locked')
    ->disabled()               // always disabled (display only)
```

### ToggleColumn API

```php
->onColor(string $color)             // default: 'primary'
->offColor(string $color)            // default: 'gray'
->onIcon(?string $icon)              // icon when on
->offIcon(?string $icon)             // icon when off
->disabled(bool|Closure $disabled = true)
->isDisabled(?Model $record = null): bool
```

---

## SelectColumn

Inline select dropdown — saves immediately on change. Validates against defined rules.

```php
use NyonCode\WireTable\Columns\SelectColumn;
```

### Basic Usage

```php
SelectColumn::make('status')
    ->options([
        'draft' => 'Draft',
        'review' => 'In Review',
        'published' => 'Published',
        'archived' => 'Archived',
    ])
```

### With Validation

```php
SelectColumn::make('priority')
    ->options([
        'low' => 'Low',
        'medium' => 'Medium',
        'high' => 'High',
        'critical' => 'Critical',
    ])
    ->rules(['required', 'in:low,medium,high,critical'])
```

### Native vs Styled

```php
// Native HTML <select> (default)
SelectColumn::make('type')->options([...])->native()

// Custom styled dropdown
SelectColumn::make('type')->options([...])->native(false)
```

### Conditional Disabled

```php
SelectColumn::make('role')
    ->options(['admin' => 'Admin', 'editor' => 'Editor', 'viewer' => 'Viewer'])
    ->disabled(fn ($record) => $record->is_super_admin)  // can't change super admin
```

### SelectColumn API

```php
->options(array $options)            // ['value' => 'Label', ...]
->native(bool $native = true)       // use native <select> element
->isNative(): bool
->disabled(bool|Closure $disabled = true)
->isDisabled(?Model $record = null): bool
->rules(array $rules)               // validation rules
```

---

## TextInputColumn

Inline text input — validates and saves on blur (or enter). Supports `type` attribute.

```php
use NyonCode\WireTable\Columns\TextInputColumn;
```

### Basic Usage

```php
TextInputColumn::make('name')
    ->rules(['required', 'string', 'max:255'])
    ->saveOnBlur()
```

### Number Input

```php
TextInputColumn::make('quantity')
    ->type('number')
    ->rules(['required', 'integer', 'min:0', 'max:9999'])
```

### Email Input

```php
TextInputColumn::make('email')
    ->type('email')
    ->rules(['required', 'email', 'max:255'])
```

### TextInputColumn API

```php
->type(string $type)                 // 'text', 'number', 'email', 'tel', 'url'
->rules(array|string $rules)         // Laravel validation rules
->saveOnBlur(bool $saveOnBlur = true)
->editableUsing(Closure $fn)         // custom save callback
```

---

## StackedColumn

Vertically stacks content — avatar + primary text + secondary text. Perfect for "user" cells.

```php
use NyonCode\WireTable\Columns\StackedColumn;
```

### Avatar + Name + Email Pattern

```php
StackedColumn::make('user')
    ->avatar('avatar_url')
    ->primary('name')
    ->secondary('email')
    ->circular()
    ->avatarSize('md')
```

### Without Avatar

```php
StackedColumn::make('details')
    ->primary('title')
    ->secondary('subtitle')
```

### Avatar from Name (Generated)

```php
StackedColumn::make('user')
    ->avatarUrl(fn ($record) => null)  // no URL → generates color from name
    ->primary('name')
    ->secondary('role')
    ->circular()
```

### Custom Stack

```php
StackedColumn::make('info')
    ->stack([
        ['column' => 'name', 'weight' => 'bold'],
        ['column' => 'department.name', 'size' => 'sm', 'color' => 'gray'],
        ['column' => 'email', 'size' => 'xs', 'color' => 'gray', 'icon' => 'mail'],
    ])
```

### Search Through Stacked

```php
StackedColumn::make('user')
    ->primary('name')
    ->secondary('email')
    ->searchable()
    ->searchColumns(['name', 'email'])  // search both fields
```

### StackedColumn API

```php
->primary(string $column)            // primary (bold) text column
->secondary(string $column)          // secondary (muted) text column
->avatar(string $column)             // avatar image URL column
->avatarUrl(string|Closure $url)     // explicit avatar URL
->circular(bool $circular = true)    // round avatar
->square(bool $square = true)        // square avatar
->avatarSize(string $size)           // 'xs', 'sm', 'md', 'lg', 'xl'
->avatarBackground(string $color)    // fallback background color
->stack(array $items)                // custom stack items
->searchColumns(array $columns)      // columns to include in search
```

---

## SplitColumn

Horizontally splits space between multiple child columns.

```php
use NyonCode\WireTable\Columns\SplitColumn;
```

### Basic Split

```php
SplitColumn::make('name_status')
    ->columns([
        TextColumn::make('name')->weight('bold'),
        BadgeColumn::make('status')->colors([...]),
    ])
```

### Vertical Layout

```php
SplitColumn::make('address')
    ->columns([
        TextColumn::make('street'),
        TextColumn::make('city'),
        TextColumn::make('country'),
    ])
    ->vertical()
```

### With Gap & Alignment

```php
SplitColumn::make('user_info')
    ->columns([
        ImageColumn::make('avatar')->circular()->size(32),
        TextColumn::make('name'),
    ])
    ->gap('sm')          // 'xs', 'sm', 'md', 'lg'
    ->alignCenter()      // vertical center alignment
```

### SplitColumn API

```php
->columns(array $columns)            // Column[] child columns
->vertical(bool $vertical = true)    // vertical layout
->horizontal(bool $horizontal = true) // horizontal layout (default)
->gap(string $gap)                   // 'xs', 'sm', 'md', 'lg'
->alignCenter(bool $center = true)   // vertical center
->alignStart(bool $start = true)     // vertical top
->getColumns(): array
->isSearchable(): bool               // true if any child is searchable
->getSearchColumns(): array          // merged from children
->isSortable(): bool                 // true if first child is sortable
->getSortColumn(): ?string           // from first sortable child
```

---

## PollColumn

Advanced auto-refreshing column with state machines, progress tracking, and condition-based polling. Ideal for background jobs, live status, progress bars.

```php
use NyonCode\WireTable\Columns\PollColumn;
```

### Basic Polling

```php
PollColumn::make('status')
    ->interval('5s')
    ->stateDisplays([
        'pending' => 'Waiting...',
        'processing' => 'In Progress',
        'completed' => 'Done',
        'failed' => 'Failed',
    ])
    ->stateColors([
        'pending' => 'gray',
        'processing' => 'info',
        'completed' => 'success',
        'failed' => 'danger',
    ])
    ->stateIcons([
        'pending' => 'clock',
        'processing' => 'refresh',
        'completed' => 'check',
        'failed' => 'x',
    ])
```

### Job Status Preset

```php
PollColumn::make('job_status')
    ->forJobStatus()           // preconfigured for Laravel Job states
    ->interval('3s')
    ->stopWhen(fn ($state) => in_array($state, ['completed', 'failed']))
```

### Progress Bar Preset

```php
PollColumn::make('progress')
    ->forProgress()            // progress bar UI (0-100)
    ->interval('2s')
    ->stopWhen(fn ($state) => $state >= 100)
```

### Conditional Polling

```php
PollColumn::make('sync_status')
    ->interval('5s')
    ->pollWhile(fn ($state) => $state === 'syncing')   // poll only while syncing
    ->pollForever(false)                                // stop when condition fails
    ->maxPolls(60)                                      // safety limit
```

### Custom State Resolution

```php
PollColumn::make('deployment')
    ->resolveStateUsing(fn ($record) => $record->fresh()->deployment_status)
    ->interval('10s')
```

### Badge Mode

```php
PollColumn::make('status')
    ->badge()
    ->colors([
        'success' => 'online',
        'danger' => 'offline',
        'warning' => 'degraded',
    ])
    ->interval('30s')
```

### Loading Indicator

```php
PollColumn::make('data')
    ->loadingIndicator('spinner')    // show during fetch
    ->keepContentWhileLoading()       // don't flash blank
    ->animateTransitions()            // smooth state changes
```

### Callbacks

```php
PollColumn::make('batch_progress')
    ->interval('3s')
    ->onComplete(fn ($record) => Notification::success("Batch {$record->id} done"))
    ->stopWhen(fn ($state) => $state === 'done')
```

### PollColumn API

```php
// Polling control
->interval(string $interval)             // '1s', '2s', '3s', '5s', '10s', '30s', '60s'
->intervalSeconds(int $seconds)          // numeric seconds
->pollForever(bool $forever = true)      // don't stop
->maxPolls(int $max)                     // safety limit
->stopWhen(Closure $fn)                  // fn($state) => bool
->pollWhile(Closure $fn)                 // fn($state) => bool
->pollWhilePending()                     // shortcut: poll while 'pending'

// State display
->stateDisplays(array $map)              // ['state' => 'display text', ...]
->displayForState(string $state, string $display)
->defaultState(string $state)
->stateClasses(array $map)               // ['state' => 'css classes', ...]
->stateIcons(array $map)                 // ['state' => 'icon name', ...]
->stateColors(array $map)                // ['state' => 'color name', ...]
->resolveStateUsing(Closure $fn)         // custom state resolver

// Presets
->forJobStatus()                         // job lifecycle preset
->forProgress()                          // progress bar preset

// UI options
->badge(bool $badge = true)              // render as badge
->colors(array $map)                     // badge color map
->colorUsing(Closure $fn)                // dynamic color
->size(string $size)                     // badge size
->loadingIndicator(?string $type)        // 'spinner', 'dots', 'pulse'
->withoutLoadingIndicator()
->keepContentWhileLoading(bool $keep = true)
->animateTransitions(bool $animate = true)

// Row-level
->rowLevelPolling(bool $rowLevel = true) // poll per row (not whole table)

// Callbacks
->onComplete(Closure $fn)
->refreshMethod(string $method)          // Livewire method on refresh
```

---

## Column-Level Filtering

Beyond the dedicated Filter classes, any column can have an inline filter in its header.

```php
// Select filter in column header
TextColumn::make('status')
    ->filterable()
    ->filterAsSelect(['active' => 'Active', 'inactive' => 'Inactive'])

// Boolean filter
BooleanColumn::make('is_active')
    ->filterable()
    ->filterAsBoolean()

// Date range filter
TextColumn::make('created_at')
    ->filterable()
    ->filterAsDateRange()

// Number range filter
TextColumn::make('price')
    ->filterable()
    ->filterAsNumberRange()
    ->filterMinValue(0)
    ->filterMaxValue(10000)

// Custom filter logic
TextColumn::make('name')
    ->filterable()
    ->filterUsing(fn (Builder $query, mixed $value) => $query->where('name', 'like', "%{$value}%"))
    ->filterDebounce(500)

// Filter with operator
TextColumn::make('age')
    ->filterable()
    ->filterOperator('>=')
```

### Column-Level Filter API

```php
->filterable(bool|Closure $filterable = true)
->isFilterable(): bool
->filterAsSelect(array $options)
->filterAsDate()
->filterAsDateRange()
->filterAsNumberRange()
->filterAsBoolean()
->filterOperator(string $operator)     // '=', '!=', '>', '<', '>=', '<=', 'like'
->filterDebounce(int $ms)
->filterMinDate(string $date)
->filterMaxDate(string $date)
->filterMinValue(float $value)
->filterMaxValue(float $value)
->filterUsing(Closure $fn)
->filterOptions(array $options)
->applyFilter(Builder $query, mixed $value): Builder
```

---

## Inline Editing

Columns can also use the generic `editable()` API (in addition to dedicated TextInputColumn/SelectColumn/ToggleColumn):

```php
TextColumn::make('name')
    ->editable()                              // enables inline editing
    ->editableType('text')                    // 'text', 'select', 'toggle'
    ->editableRules(['required', 'max:255'])
    ->editableUsing(function ($record, $column, $value) {
        $record->update([$column => $value]);
    })

TextColumn::make('category')
    ->editable()
    ->editableType('select')
    ->editableOptions(['a' => 'Category A', 'b' => 'Category B'])
    ->editableRules(['required', 'in:a,b'])
```

---

## Patterns & Recipes

### User Table with Avatar

```php
$table->columns([
    StackedColumn::make('user')
        ->avatar('avatar_url')
        ->primary('name')
        ->secondary('email')
        ->circular()
        ->searchable()
        ->searchColumns(['name', 'email']),

    BadgeColumn::make('role')
        ->colors(['primary' => 'admin', 'success' => 'editor', 'gray' => 'viewer']),

    TextColumn::make('department.name')
        ->sortable()
        ->searchable(),

    TextColumn::make('posts.count')
        ->label('Posts')
        ->sortable()
        ->alignCenter(),

    TextColumn::make('last_login')
        ->since()
        ->sortable()
        ->size('sm')
        ->textColor('gray'),

    BooleanColumn::make('is_active'),
]);
```

### Financial Table

```php
$table->columns([
    TextColumn::make('number')
        ->searchable()
        ->fontFamily('mono'),

    TextColumn::make('client.name')
        ->searchable()
        ->sortable(),

    TextColumn::make('issued_at')
        ->date('d.m.Y')
        ->sortable(),

    TextColumn::make('due_at')
        ->date('d.m.Y')
        ->color(fn ($record) => $record->is_overdue ? 'danger' : null),

    TextColumn::make('total')
        ->money('CZK')
        ->sortable()
        ->alignRight()
        ->weight('bold')
        ->summarize('sum', 'Total'),

    BadgeColumn::make('status')
        ->colors([
            'gray' => 'draft',
            'warning' => 'sent',
            'success' => 'paid',
            'danger' => 'overdue',
        ]),

    PollColumn::make('payment_status')
        ->interval('30s')
        ->badge()
        ->colors(['success' => 'received', 'warning' => 'pending', 'gray' => 'none'])
        ->pollWhile(fn ($state) => $state === 'pending'),
]);
```

### Task Board Table

```php
$table->columns([
    SelectColumn::make('status')
        ->options([
            'todo' => '📋 To Do',
            'in_progress' => '🔄 In Progress',
            'review' => '👀 Review',
            'done' => '✅ Done',
        ]),

    TextColumn::make('title')
        ->searchable()
        ->weight('semibold')
        ->description(fn ($r) => Str::limit($r->body, 60))
        ->url(fn ($r) => route('tasks.show', $r)),

    StackedColumn::make('assignee')
        ->avatar('assignee.avatar_url')
        ->primary('assignee.name')
        ->circular()
        ->avatarSize('sm'),

    BadgeColumn::make('priority')
        ->colors(['danger' => 'high', 'warning' => 'medium', 'gray' => 'low'])
        ->icons(['arrow-up' => 'high', 'minus' => 'medium', 'arrow-down' => 'low']),

    TextColumn::make('due_at')
        ->date('d.m.')
        ->color(fn ($r) => $r->is_overdue ? 'danger' : 'gray')
        ->size('sm'),
]);
```
