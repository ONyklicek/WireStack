---
order: 65
---

# Relation Managers

A relation manager is a relationship-scoped table rendered as a standalone Livewire component — the counterpart to Filament's relation managers. A subclass names an owner relationship and defines the table exactly like any `WithTable` component; the base class pins the query to the owner record's relationship, so the list only ever shows the related rows.

## Define a Manager

```php
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\RelationManagers\RelationManager;
use NyonCode\WireTable\Table;

class PostsRelationManager extends RelationManager
{
    protected string $relationship = 'posts';

    protected ?string $title = 'Posts';

    public function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('title')->searchable()->sortable(),
        ]);
    }
}
```

Everything a `WithTable` component can do works here — columns, filters, actions, exports, search, sorting. The `query()` is forced to the owner relationship, regardless of what the subclass sets.

## Render It

Pass the owner record from the parent view:

```blade
@livewire(App\Livewire\PostsRelationManager::class, ['ownerRecord' => $author])
```

The component renders an optional heading (`$title`) above the scoped table.

## Supported Relationships

Any Eloquent relationship works for **listing**. For belongs-to-many, the query selects the related table's columns (`related.*`), so pivot columns never overwrite related attributes or the row key.

## Persistence Helpers

The base class exposes relationship-aware helpers for create/attach/detach actions to call:

```php
// has-one / has-many: sets the foreign key; belongs-to-many: creates + attaches
$this->createRelatedRecord(['title' => 'Hello']);

// belongs-to-many only
$this->attachRelated($tag->id, ['note' => 'pivot attribute']);
$this->detachRelated($tag->id);   // null detaches all
```

Unsupported relationship types throw a clear `RuntimeException` (e.g. `createRelatedRecord()` on a belongs-to, or `attachRelated()` on a has-many).

Typical wiring from a header action:

```php
->headerActions([
    HeaderAction::make('add_post')
        ->form([TextInput::make('title')->required()])
        ->action(fn (array $data) => $this->createRelatedRecord($data)),
])
```

## Accessors

| Method | Description |
|--------|-------------|
| `getOwnerRecord()` | The bound owner model (throws when missing) |
| `getRelationshipName()` | The configured relationship name |
| `getRelationship()` | A fresh relationship instance on the owner |
| `getTitle()` | The optional heading |

## Related Docs

- [Table Overview](overview.md)
- [Actions](actions.md)
