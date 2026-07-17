# BelongsToSelect

`BelongsToSelect` is a relationship-aware select field for `belongsTo` associations.

## Basic Usage

```php
use NyonCode\WireForms\Components\BelongsToSelect;

BelongsToSelect::make('company_id')
    ->relationship('company', 'name')
    ->label('Company')
    ->searchable()
```

This resolves options from the related model instead of requiring a manual `options()` array.

A `searchable()` select without `preload()` searches the related table on the server as the
user types (matching the title attribute, limited to 50 results) and resolves the selected
value's label with a single keyed lookup — the full option list never ships to the client.
Add `preload()` to load the whole list up front and filter client-side instead. An explicit
`getSearchResultsUsing()` callback (inherited from `Select`) overrides the built-in search.

## Common Options

```php
BelongsToSelect::make('company_id')
    ->relationship('company', 'name')
    ->searchable()
    ->preload()
    ->required()
```

| Method | Purpose |
|--------|---------|
| `relationship('company', 'name')` | Resolve options from the relation and title column |
| `searchable()` | Search the related table on the server as the user types |
| `preload()` | Load the full option list immediately and filter client-side |
| `modifyOptionsQueryUsing()` | Scope or sort the related model query |
| `createOptionForm()` | Show an inline create form for a new related record (auto-creates via the relation) |
| `createOptionUsing()` | Customize how a new option is persisted |
| `editOptionForm()` | Edit the selected related record — auto-fills and writes back through its relationships |
| `fillEditOptionUsing()` / `updateOptionUsing()` | Take full control of the edit fill / save (each receives the resolved `$record`) |

## Scoped Options

```php
BelongsToSelect::make('company_id')
    ->relationship('company', 'name')
    ->modifyOptionsQueryUsing(fn ($query) => $query->where('active', true))
```

## Inline Create

```php
use NyonCode\WireForms\Components\TextInput;

BelongsToSelect::make('company_id')
    ->relationship('company', 'name')
    ->createOptionForm([
        TextInput::make('name')->required(),
    ])
```

Inline create uses the same modal flow as [Select](select.md#create-edit-options): a "+ Create" button in the combobox panel opens a modal form, and on save the new record is created on the relationship (or via `createOptionUsing()`), selected, and merged into the dropdown without a page refresh. `editOptionForm()` from `Select` works here too.

With a relationship set, `createOptionForm()` **auto-creates the related model** even without `createOptionUsing()` — it introspects the `belongsTo` relation to find the related class and creates a record from the modal data. The field is handed the form's parent record by the runtime, which is what makes this (and every write-back below) work with no wiring.

## Relationship write-back

`editOptionForm()` doesn't just show a modal — it reads from and writes to the **related record through its own relationships**. What the related model *owns* is written automatically; what it *shares* is left to an explicit callback. Every callback (`fillEditOptionUsing`, `updateOptionUsing`) receives the resolved related **`$record`**, so you can read or write through any relation without re-loading it.

### Auto-fill

The edit modal is filled from the selected related record, pulling **only the columns the edit form declares** — never the id, timestamps, or unrelated columns:

```php
BelongsToSelect::make('author_id')
    ->relationship('author', 'name')
    ->editOptionForm([
        TextInput::make('name'),
        TextInput::make('email'),
    ])
```

A **dotted field name** walks a nested relation on fill (an empty relation yields `null`):

```php
TextInput::make('company.name')   // fills from $author->company->name
```

### The matrix

On save, the **kind of relation** a dotted or repeater field targets decides how it is written:

| Relation | Field shape | Automatic write-back | Explicit alternative via `updateOptionUsing($record)` |
|---|---|---|---|
| `belongsTo`, `morphTo` | dotted `origin.name` | fill only (shared parent) | `$record->origin?->update(...)`, `associate()`, `dissociate()` |
| `hasOne`, `morphOne` | dotted `profile.bio` | `updateOrCreate` (creates if missing) | `$record->profile()->save($model)` |
| `hasMany`, `morphMany` | `Repeater->relationship()` | sync: create / update / delete | `$record->books()->saveMany([...])` |
| `belongsToMany`, `morphToMany`, `morphedByMany` | `Repeater->relationship()` + pivot columns | pivot sync: attach / detach / update | `syncWithoutDetaching()`, `toggle()`, `updateExistingPivot()` |
| `hasManyThrough`, `hasOneThrough` | dotted / repeater | fill only (read-through) | write the intermediate yourself |

Polymorphic variants (`morphOne`, `morphMany`, `morphTo`, `morphToMany`, `morphedByMany`) behave exactly like their non-polymorphic base and set the polymorphic `*_type`/`*_id` on their own.

### Owned 1:1 — `hasOne` / `morphOne`

A dotted field is written back through the relation, updated in place or **created if missing**:

```php
BelongsToSelect::make('author_id')
    ->relationship('author', 'name')
    ->editOptionForm([
        TextInput::make('name'),
        TextInput::make('profile.bio'),   // hasOne
        TextInput::make('avatar.url'),    // morphOne
    ])
```

To keep control, persist it yourself with the relation's `save()`:

```php
->updateOptionUsing(function ($record, array $data) {
    $avatar = $record->avatar ?? new Avatar;
    $avatar->url = data_get($data, 'avatar.url');
    $record->avatar()->save($avatar);   // stamps the morph type on a new record
    $record->update(['name' => $data['name']]);
})
```

### Owned 1:N — `hasMany` / `morphMany`

Edit the collection through a relationship-backed **Repeater**; the write-back syncs it (create new, update matched, delete removed):

```php
use NyonCode\WireForms\Components\Repeater;

BelongsToSelect::make('author_id')
    ->relationship('author', 'name')
    ->editOptionForm([
        TextInput::make('name'),
        Repeater::make('books')
            ->relationship('books')
            ->schema([TextInput::make('title')]),
    ])
```

For a non-destructive write (create/update, never delete), use `saveMany()` in the callback:

```php
->updateOptionUsing(function ($record, array $data) {
    $books = collect($data['books'])->map(function (array $row) {
        $book = ! empty($row['id']) ? Book::find($row['id']) : new Book;
        $book->title = $row['title'];
        return $book;
    })->all();
    $record->books()->saveMany($books);
})
```

### Many-to-many with pivot — `belongsToMany` / `morphToMany` / `morphedByMany`

Each repeater item carries the **related model's key plus pivot columns**. The fill reads the pivot off each loaded row; the write-back `sync()`s it (attach / detach / update pivot columns):

```php
BelongsToSelect::make('author_id')
    ->relationship('author', 'name')
    ->editOptionForm([
        TextInput::make('name'),
        Repeater::make('tags')
            ->relationship('tags')
            ->schema([
                TextInput::make('id'),     // the related tag key
                TextInput::make('role'),   // a pivot column (declared with ->withPivot('role'))
            ]),
    ])
```

Both a **custom pivot model** (`->using(...)`, so its casts apply) and an **ad-hoc pivot** (just `->withPivot(...)`, the framework's plain `Pivot`/`MorphPivot`) work unchanged, because the flow runs at the relation level. For a different pivot strategy, drop into the callback:

```php
->updateOptionUsing(function ($record, array $data) {
    $sync = collect($data['tags'])
        ->mapWithKeys(fn ($row) => [$row['id'] => ['role' => $row['role']]])
        ->all();

    $record->tags()->syncWithoutDetaching($sync);       // attach/update, never detach
    // $record->tags()->toggle([$id1, $id2]);           // flip membership of the listed ids
    // $record->tags()->updateExistingPivot($id, [...]); // pivot columns of an attached row only
})
```

### Shared parent — `belongsTo` / `morphTo`

A `belongsTo`/`morphTo` points at a record the related model doesn't own (many authors can share one company), so it is **never mutated automatically** — the fill loads it for display and you write it explicitly:

```php
BelongsToSelect::make('author_id')
    ->relationship('author', 'name')
    ->editOptionForm([
        TextInput::make('name'),
        TextInput::make('company.name'),
    ])
    ->updateOptionUsing(function ($record, array $data) {
        $record->update(['name' => $data['name']]);
        $record->company?->update(['name' => data_get($data, 'company.name')]);
    })
```

**Re-pointing** the association is either an own-column edit (the foreign key is a real column, so the auto write-back handles it) or an idiomatic `associate()` / `dissociate()`:

```php
->updateOptionUsing(function ($record, array $data) {
    $record->company()->associate(Company::find($data['company_id']));  // re-point
    // $record->company()->dissociate();                                // detach
    $record->save();
})
```

For a `morphTo`, `associate()` re-points a **different type** (it sets both `*_type` and `*_id`); `dissociate()` nulls both.

### Read-through — `hasManyThrough` / `hasOneThrough`

A read-through relation has no unambiguous write-back (which intermediate parent would own a new distant row is unknowable), so the fill still loads it for display but it is **never auto-written** — no orphaned or deleted rows. Write it explicitly through the intermediate in `updateOptionUsing($record)` when you need to.

### Inside a Repeater

Because the runtime propagates the parent record to relationship fields everywhere, all of the above works the same for a `BelongsToSelect` nested **inside a `Repeater` item**, per item.

## Related Docs

- [Select](select.md)
- [Forms Overview](../overview.md)
