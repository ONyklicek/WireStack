# MorphToSelect

`MorphToSelect` renders a type selector plus a record selector for polymorphic relationships.

## Basic Usage

```php
use App\Models\Post;
use App\Models\Video;
use NyonCode\WireForms\Components\MorphToSelect;
use NyonCode\WireForms\Components\MorphToSelect\Type;

MorphToSelect::make('commentable')
    ->types([
        Type::make(Post::class)->titleAttribute('title'),
        Type::make(Video::class)->titleAttribute('name'),
    ])
```

This is useful when a form field may point to more than one model type.

## How State Is Stored

By default the component writes two values:

- `commentable_type`
- `commentable_id`

You can customize the suffixes if your schema uses different column names.

```php
MorphToSelect::make('subject')
    ->typeColumnSuffix('_model')
    ->idColumnSuffix('_key')
```

## When to Use It

Use `MorphToSelect` when:

- the model uses a `morphTo` relationship
- users must choose both the target type and the target record
- a plain `Select` would hide too much context

## Related Docs

- [BelongsToSelect](belongs-to-select.md)
- [Select](select.md)
- [Forms Overview](../overview.md)
