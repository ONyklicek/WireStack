## wire-table

Build a data table inside a Livewire component using the `WithTable` trait and a `table()` method:

    use NyonCode\WireTable\Concerns\WithTable;
    use NyonCode\WireTable\Table;

    class Users extends Component
    {
        use WithTable;

        public function table(Table $table): Table
        {
            return $table
                ->query(User::query())
                ->columns([
                    TextColumn::make('name')->sortable()->searchable(),
                    BadgeColumn::make('status')->color(fn ($state) => $state === 'active' ? 'success' : 'gray'),
                    BooleanColumn::make('is_admin'),
                ])
                ->filters([
                    SelectFilter::make('status')->options(Status::class),
                ])
                ->actions([EditAction::make(), DeleteAction::make()])
                ->headerActions([HeaderAction::make('create')])
                ->bulkActions([DeleteBulkAction::make()]);
        }
    }

### Columns

`TextColumn`, `BadgeColumn`, `BooleanColumn`, `IconColumn`, `ImageColumn`, `ButtonColumn`, `ToggleColumn`,
`PollColumn`, `SelectColumn`, `TextInputColumn`, `SplitColumn`, `StackedColumn`.

### Filters

`SelectFilter`, `DateFilter`, `NumberRangeFilter`, `TernaryFilter`. A filter query callback must return the
Builder. Use `->indicator()` for filter chips and `->subRows()` to scope sub-row filtering.

### More

- Summaries: per-column `->summarize(...)` with footer scope toggles; grand totals computed in SQL.
- Sub-rows, grouping with subtotals, and exports (`withSummaries`).
- Inline editing via `TextInputColumn` / `ToggleColumn` / `SelectColumn`.
- `Table::queryString()` persists state to the URL.

Use `describe-table` on an existing component to see its resolved columns, filters and actions.
