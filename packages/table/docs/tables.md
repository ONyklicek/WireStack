# Tables

## Basic Setup

Every table is a Livewire component that implements `HasTable` and uses the `WithTable` trait:

```php
use Livewire\Component;
use NyonCode\WireTable\Contracts\HasTable;
use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireTable\Table;

class UserTable extends Component implements HasTable
{
    use WithTable;

    public function table(Table $table): Table
    {
        return $table
            ->model(User::class)
            ->columns([/* ... */]);
    }

    public function render()
    {
        return view('livewire.user-table');
    }
}
```

In your Blade view:

```blade
<div>
    {{ $this->table }}
</div>
```

## Data Source

### Eloquent Model

```php
$table->model(User::class)
```

### Custom Query

```php
$table->query(User::where('active', true)->with('roles'))
```

### Modify Query

Add conditions, eager loading, or joins to the base query:

```php
$table->model(User::class)
    ->modifyQueryUsing(fn (Builder $query) => $query
        ->where('active', true)
        ->with(['roles', 'permissions'])
        ->whereHas('orders')
    )
```

## Search

### Global Search

```php
$table->searchable()        // Enable search bar
$table->searchable(false)   // Disable search bar
```

Search is applied to columns that have `->searchable()` enabled. See [Columns](columns.md) for details.

## Sorting

```php
$table->sortable()                         // Enable column sorting
$table->defaultSort('name')                // Default sort column
$table->defaultSort('created_at', 'desc')  // Default sort with direction
```

## Pagination

```php
$table->paginated()                         // Enable pagination (default)
$table->paginated(false)                    // Disable pagination
$table->perPage(25)                         // Items per page
$table->perPageOptions([10, 25, 50, 100])   // Available options
```

## Row Selection

```php
$table->selectable()  // Enable checkboxes for row selection
```

Row selection is automatically enabled when bulk actions are defined.

## Record URL

Make entire rows clickable:

```php
// Static URL with {id} placeholder
$table->recordUrl('/users/{id}/edit')

// Dynamic URL with closure
$table->recordUrl(fn (Model $record) => route('users.edit', $record))
```

## Primary Key

```php
$table->primaryKey('uuid')  // Default: 'id'
```

## Empty State

```php
$table->emptyState(
    heading: 'No users found',
    description: 'Try adjusting your search or filters.',
    icon: 'users'
)
```

## Styling

### Table Appearance

```php
$table->striped()           // Alternating row colors
$table->hoverable()         // Hover effect on rows (default: true)
$table->compact()           // Reduced padding
$table->bordered()          // Add borders
```

### Custom CSS Classes

```php
$table->tableClass('shadow-lg rounded-xl')
$table->headerClass('bg-gray-50')
$table->rowClass('transition-colors')
```

### Actions Column

```php
$table->actionsPosition('end')          // 'start' or 'end' (default: 'end')
$table->actionsAlignment('right')       // 'left', 'center', 'right'
$table->actionsColumnLabel('Actions')   // Column header text
$table->actionsColumnWidth('w-20')      // Column width
```

## Responsive Design

Enable card layout on mobile devices:

```php
$table->stackedOnMobile()               // Card layout below 'md' breakpoint
$table->stackedOnMobile(true, 'lg')     // Card layout below 'lg' breakpoint
```

## Lazy Loading

Defer table rendering until after the page loads:

```php
$table->lazy()
$table->lazy()->lazyPlaceholder('Loading table...')
```

## Polling

Auto-refresh the table at intervals:

```php
$table->poll('5s')                       // Refresh every 5 seconds
$table->poll('30s')->pollKeepAlive()     // Keep connection alive
$table->poll('10s')->pollOnlyVisible()   // Only poll when tab is visible

// Conditional polling
$table->poll('5s')->pollWhen(fn ($component) => $component->hasActiveJobs)

// Polling method
$table->poll('10s')->pollMethod('refresh')  // Soft refresh (default)
$table->poll('10s')->pollMethod('reload')   // Full page reload
```

## Livewire Component Reference

Bind the parent Livewire component:

```php
$table->livewireComponent($this)
```

## Debugging

```php
$table->toSql()               // SQL with bindings replaced
$table->toRawSql()            // ['sql' => ..., 'bindings' => [...]]
$table->dump()                // Dump SQL info and continue
$table->dd()                  // Dump SQL info and halt

$table->getColumnsInfo()      // Column metadata
$table->getDatabaseColumns()  // Schema column listing
$table->dumpColumns()         // Dump column info
$table->ddColumns()           // Dump column info and halt

$table->debug()               // Full debug array (model, SQL, columns, filters, config)
```
