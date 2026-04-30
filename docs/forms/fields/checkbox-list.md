# CheckboxList

Multiple checkboxes from an options array.

```php
use NyonCode\WireForms\Components\CheckboxList;
```

## Usage

```php
CheckboxList::make('permissions')
    ->options([
        'create' => 'Create',
        'read' => 'Read',
        'update' => 'Update',
        'delete' => 'Delete',
    ])
    ->columns(2)
    ->searchable()
    ->bulkToggleable()
```

## Multi-Column Layout

```php
CheckboxList::make('features')
    ->options([...])
    ->columns(3)       // display in 3 columns
```

## Search

```php
CheckboxList::make('permissions')
    ->options([...])
    ->searchable()
    ->searchPrompt('Filter permissions...')
```

## Bulk Toggle

```php
CheckboxList::make('permissions')
    ->bulkToggleable()
    ->selectAllLabel('Select All')
    ->deselectAllLabel('Deselect All')
```

## Grouped Options

```php
CheckboxList::make('permissions')
    ->grouped()
    ->groups([
        'Posts' => ['create_post', 'edit_post', 'delete_post'],
        'Users' => ['create_user', 'edit_user'],
    ])
```
